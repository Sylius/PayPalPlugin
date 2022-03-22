<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Command;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ObjectManager;
use Gedmo\References\LazyCollection;
use SM\Factory\FactoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Webmozart\Assert\Assert;

final class CheckAwaitingPaymentCommand extends Command
{
    const INSTRUMENT_DECLINED = 'INSTRUMENT_DECLINED';
    const PAYER_ACTION_REQUIRED = 'PAYER_ACTION_REQUIRED';
    const DUPLICATE_INVOICE_ID = 'DUPLICATE_INVOICE_ID';

    /** @var string */
    protected static $defaultName = 'sylius:pay-pal-plugin:check-payment';
    /** @var string */
    protected static $defaultDescription = 'Vérification des commandes Paypal en attente';

    private FactoryInterface $stateMachineFactory;
    private CacheAuthorizeClientApiInterface $authorizeClientApi;
    private OrderDetailsApiInterface $orderDetailsApi;
    private CompleteOrderApiInterface $completeOrderApi;
    private ObjectManager $paymentManager;

    private PaymentRepositoryInterface $paymentRepository;
    private PayPalConfigurationProviderInterface $payPalConfigurationProvider;
    private ChannelContextInterface $channelContext;
    private SymfonyStyle $io;
    private PropertyAccessor $accessor;

    private bool $isDry = false;

    public function __construct(
        FactoryInterface                     $stateMachineFactory,
        CacheAuthorizeClientApiInterface     $authorizeClientApi,
        OrderDetailsApiInterface             $orderDetailsApi,
        CompleteOrderApiInterface            $completeOrderApi,
        ObjectManager                        $paymentManager,
        PaymentRepositoryInterface           $paymentRepository,
        PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        ChannelContextInterface              $channelContext
    )
    {
        parent::__construct(self::$defaultName);

        $this->stateMachineFactory = $stateMachineFactory;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->completeOrderApi = $completeOrderApi;
        $this->paymentManager = $paymentManager;

        $this->paymentRepository = $paymentRepository;
        $this->payPalConfigurationProvider = $payPalConfigurationProvider;
        $this->channelContext = $channelContext;
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
            ->addOption('dry')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->isDry = (bool)$input->getOption('dry');
        $this->io = new SymfonyStyle($input, $output);

        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        try {
            $paypalMethod = $this->payPalConfigurationProvider->getPayPalPaymentMethod($channel);
        } catch (\InvalidArgumentException $e) {
            $this->io->error($e->getMessage());
            return 0;
        }

        /** @var LazyCollection $payments */
        $payments = $this->paymentRepository->matching(
            Criteria::create()
                ->where(Criteria::expr()->in('state', [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_NEW]))
                ->andWhere(Criteria::expr()->eq('method', $paypalMethod))
        );

        $this->io->info('Commande Paypal en attente de paiement : ' . $payments->count());

        /** @var PaymentInterface $payment */
        foreach ($payments as $payment) {
            /** @var string|null $paymentPaypalOrderStatus */
            $paymentPaypalOrderStatus = $this->accessor->getValue($payment->getDetails(), '[status]');
            /** @var string|null $paypalOrderID */
            $paypalOrderID = $this->accessor->getValue($payment->getDetails(), '[paypal_order_id]');

            if (in_array($paymentPaypalOrderStatus, [StatusAction::STATUS_CREATED, StatusAction::STATUS_PROCESSING])
                && !is_null($paypalOrderID)) {
                /** @var OrderInterface $order */
                $order = $payment->getOrder();

                // Try to complete Order if not
                $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);
                if ($stateMachine->can(OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
                    if (!$this->isDry) {
                        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);
                    } else {
                        $this->io->note('[DRY] Payment id:' . $payment->getId() . ' passage de l\'order à complete.');
                    }
                }

                /** @var PaymentMethodInterface $paymentMethod */
                $paymentMethod = $payment->getMethod();
                $token = $this->authorizeClientApi->authorize($paymentMethod);

                // Retrieve Paypal order details
                $orderDetails = $this->orderDetailsApi->get($token, $paypalOrderID);

                switch ($this->accessor->getValue($orderDetails, '[status]')) {
                    case 'APPROVED':
                        $this->_captureOrder($paypalOrderID, $payment, $token);
                        break;
                    case 'COMPLETED':
                        $this->_markOrderStatus($orderDetails, $payment, StatusAction::STATUS_COMPLETED);
                        break;
                    default:
                        $this->_markOrderStatus($orderDetails, $payment, StatusAction::STATUS_PROCESSING);
                        break;
                }
            } else {
                $this->io->note('Payment id:' . $payment->getId() . ' non traitée, conditions non remplies.');
            }
        }

        return 0;
    }

    /**
     * @param string $paypalOrderID
     * @param PaymentInterface $payment
     * @param string $token
     * @return void
     */
    private function _captureOrder(string $paypalOrderID, PaymentInterface $payment, string $token): void
    {
        if (!$this->isDry) {
            // Call to capture Paypal order
            $detailsComplete = $this->completeOrderApi->complete($token, $paypalOrderID);

            // Retrieve Paypal order details
            $details = $this->orderDetailsApi->get($token, $paypalOrderID);

            /** @var string|null $orderDetailstatus */
            $orderDetailstatus = $this->accessor->getValue($details, '[status]');

            if ($orderDetailstatus === StatusAction::STATUS_COMPLETED
                || $orderDetailstatus === StatusAction::STATUS_PROCESSING) {
                $this->_markOrderStatus($details, $payment, $orderDetailstatus);
            } else {
                if (isset($detailsComplete['debug_id'])) {
                    $this->_processError($detailsComplete, $payment);
                }
            }
        } else {
            $this->io->note('[DRY] Payment id:' . $payment->getId() . ' Tentative de capture de l\'order Paypal[' . $paypalOrderID . ']');
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
        if (!$this->isDry) {
            $detailsPayment = array_merge([
                'status' => $status,
                'paypal_order_details' => $orderDetails
            ], $payment->getDetails());

            if ($status === StatusAction::STATUS_COMPLETED) {
                $detailsPayment = array_merge([
                    'transaction_id' => $this->accessor->getValue(
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
        } else {
            $this->io->note('[DRY] Payment id:' . $payment->getId() . ' passage au status: ' . $status);
        }
    }

    /**
     * @param array $err
     * @param PaymentInterface $payment
     * @return void
     * @throws \SM\SMException
     */
    private function _processError(array $err, PaymentInterface $payment): void
    {
        /** @var string|null $errorName */
        $errorName = $this->accessor->getValue($err, '[name]');
        if (in_array($errorName, ['RESOURCE_NOT_FOUND', 'UNPROCESSABLE_ENTITY']) && !$this->isDry) {

            // Log error in payment details
            $payment->setDetails(array_merge([
                'status' => 'FAILED',
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
        } else {
            $this->io->caution('Exception pour le paiement[id:' . $payment->getId() . '] de la commande[id:' . $payment->getOrder()->getId() . ']');
            $this->io->caution('Détails de l\'erreur :');
            $this->io->caution($err);
        }
    }
}
