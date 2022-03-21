<?php

namespace Sylius\PayPalPlugin\Service;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Payment\Model\PaymentInterface as PaymentInterfaceAlias;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class WebhookService
{
    const INSTRUMENT_DECLINED = 'INSTRUMENT_DECLINED';
    const PAYER_ACTION_REQUIRED = 'PAYER_ACTION_REQUIRED';
    const DUPLICATE_INVOICE_ID = 'DUPLICATE_INVOICE_ID';

    private FactoryInterface $stateMachineFactory;
    private PaymentProviderInterface $paymentProvider;
    private ObjectManager $paymentManager;
    private CacheAuthorizeClientApiInterface $authorizeClientApi;
    private CompleteOrderApiInterface $completeOrderApi;
    private OrderDetailsApiInterface $orderDetailsApi;
    private PropertyAccessor $propertyAccessor;

    public function __construct(
        FactoryInterface                 $stateMachineFactory,
        PaymentProviderInterface         $paymentProvider,
        ObjectManager                    $paymentManager,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        CompleteOrderApiInterface        $completeOrderApi,
        OrderDetailsApiInterface         $orderDetailsApi
    )
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->completeOrderApi = $completeOrderApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param string $paypalOrderID
     * @param string $payerId
     * @return bool
     */
    public function isValidPaypalOrder(string $paypalOrderID, string $payerId): bool
    {
        try {
            $payment = $this->paymentProvider->getByPayPalOrderId($paypalOrderID);
        } catch (PaymentNotFoundException $e) {
            unset($e);
            return false;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        // Retrieve order details
        $details = $this->orderDetailsApi->get($token, $paypalOrderID);

        return $this->propertyAccessor->getValue($details, '[payer][payer_id]') === $payerId;
    }

    /**
     * @param string $paypalOrderID
     * @return void
     * @throws \SM\SMException
     */
    public function handlePaypalOrder(string $paypalOrderID): void
    {
        try {
            $payment = $this->paymentProvider->getByPayPalOrderId($paypalOrderID);
        } catch (PaymentNotFoundException $e) {
            unset($e);
            return;
        }

        if (!is_null($payment) && isset($payment->getDetails()['status'])
            && in_array($payment->getDetails()['status'], [StatusAction::STATUS_CREATED, StatusAction::STATUS_PROCESSING])
            && in_array($payment->getState(), [PaymentInterfaceAlias::STATE_NEW, PaymentInterfaceAlias::STATE_PROCESSING])
        ) {
            /** @var OrderInterface $order */
            $order = $payment->getOrder();

            // Try to complete Order if not
            $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);
            if ($stateMachine->can(OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
                $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);
            }

            /** @var PaymentMethodInterface $paymentMethod */
            $paymentMethod = $payment->getMethod();
            $token = $this->authorizeClientApi->authorize($paymentMethod);

            // Retrieve Paypal order details
            $details = $this->orderDetailsApi->get($token, $paypalOrderID);

            switch ($this->propertyAccessor->getValue($details, '[status]')) {
                case 'APPROVED':
                    $this->_captureOrder($paypalOrderID, $payment, $token);
                    break;
                case 'COMPLETED':
                    $this->_markOrderStatus($details, $payment, StatusAction::STATUS_COMPLETED);
                    break;
                default:
                    $this->_markOrderStatus($details, $payment, StatusAction::STATUS_PROCESSING);
                    break;
            }
        }
    }

    /**
     * @param string $paypalOrderID
     * @param PaymentInterface $payment
     * @param string $token
     * @return void
     */
    private function _captureOrder(string $paypalOrderID, PaymentInterface $payment, string $token): void
    {
        // Call to capture Paypal order
        $detailsComplete = $this->completeOrderApi->complete($token, $paypalOrderID);

        // Retrieve Paypal order details
        $details = $this->orderDetailsApi->get($token, $paypalOrderID);
        $orderDetailstatus = $this->propertyAccessor->getValue($details, '[status]');

        if ($orderDetailstatus === StatusAction::STATUS_COMPLETED
            || $orderDetailstatus === StatusAction::STATUS_PROCESSING) {
            $this->_markOrderStatus($details, $payment,$orderDetailstatus);
        } else {
            if (isset($detailsComplete['debug_id'])) {
                $this->_processError($detailsComplete, $payment);
            }
        }
    }

    /**
     * @param array $orderDetails
     * @param PaymentInterface $payment
     * @param string $status
     * @return void
     * @throws \SM\SMException
     */
    private function _markOrderStatus(array $orderDetails, PaymentInterface $payment, string $status): void
    {
        $detailsPayment = [
            'status' => $status,
            'paypal_order_id' => $this->propertyAccessor->getValue($orderDetails, '[id]'),
            'reference_id' => $this->propertyAccessor->getValue(
                $orderDetails, '[purchase_units][0][reference_id]'
            )
        ];

        if ($status === StatusAction::STATUS_COMPLETED) {
            $detailsPayment = array_merge([
                'transaction_id' => $this->propertyAccessor->getValue(
                    $orderDetails, '[purchase_units][0][payments][captures][0][id]'
                )
            ], $detailsPayment);
        }
        $payment->setDetails($detailsPayment);

        // Update state machine
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        if ($stateMachine->can(PaymentTransitions::TRANSITION_PROCESS)) {
            $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
        }

        if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE) && $status == StatusAction::STATUS_COMPLETED) {
            $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
        }

        $this->paymentManager->flush();
    }

    /**
     * @param array $err
     * @param PaymentInterface $payment
     * @return void
     * @throws \SM\SMException
     */
    private function _processError(array $err, PaymentInterface $payment): void
    {
        if ($this->_isProcessorDeclineError($err) || $this->_isUnprocessableEntityError($err)) {

            // Log error in payment details
            $payment->setDetails(array_merge([
                'status' => StatusAction::STATE_FAILED,
                'error' => $err
            ], $payment->getDetails()));

            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
            if ($stateMachine->can(PaymentTransitions::TRANSITION_PROCESS)) {
                $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
            }

            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
            if ($stateMachine->can(PaymentTransitions::TRANSITION_FAIL)) {
                $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);
            }

            $this->paymentManager->flush();
        }
    }

    /**
     * @param array $err
     * @return bool
     */
    private function _isProcessorDeclineError(array $err): bool
    {
        $issue = null;
        if (isset($err['details']) && is_array($err['details']) && isset($err['details'][0]))
            $issue = (string)$this->propertyAccessor->getValue($err, '[details][0][issue]');
        return $issue === self::INSTRUMENT_DECLINED || $issue === self::PAYER_ACTION_REQUIRED;
    }

    /**
     * @param array $err
     * @return bool
     */
    private function _isUnprocessableEntityError(array $err): bool
    {
        $issue = null;
        if (isset($err['details']) && is_array($err['details']) && isset($err['details'][0]))
            $issue = (string)$this->propertyAccessor->getValue($err, '[details][0][issue]');
        return $issue === self::DUPLICATE_INVOICE_ID;
    }
}
