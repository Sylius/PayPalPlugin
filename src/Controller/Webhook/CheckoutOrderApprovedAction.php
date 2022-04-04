<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebhookDataProviderInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class CheckoutOrderApprovedAction
{
    const WEBHOOK_EVENT = 'CHECKOUT.ORDER.APPROVED';

    private FactoryInterface $stateMachineFactory;
    private PaymentProviderInterface $paymentProvider;
    private ObjectManager $paymentManager;
    private OrderDetailsApiInterface $orderDetailsApi;
    private PayPalWebhookDataProviderInterface $payPalWebhookDataProvider;
    private CacheAuthorizeClientApiInterface $authorizeClientApi;
    private CompleteOrderApiInterface $completeOrderApi;
    private PaymentUpdaterInterface $payPalPaymentUpdater;
    private StateResolverInterface $orderPaymentStateResolver;
    private Logger $logger;

    public function __construct(
        FactoryInterface                   $stateMachineFactory,
        PaymentProviderInterface           $paymentProvider,
        ObjectManager                      $paymentManager,
        OrderDetailsApiInterface           $orderDetailsApi,
        PayPalWebhookDataProviderInterface $payPalWebhookDataProvider,
        CacheAuthorizeClientApiInterface   $authorizeClientApi,
        CompleteOrderApiInterface          $completeOrderApi,
        PaymentUpdaterInterface            $payPalPaymentUpdater,
        StateResolverInterface             $orderPaymentStateResolver,
        Logger                             $logger
    )
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->payPalWebhookDataProvider = $payPalWebhookDataProvider;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->completeOrderApi = $completeOrderApi;
        $this->payPalPaymentUpdater = $payPalPaymentUpdater;
        $this->orderPaymentStateResolver = $orderPaymentStateResolver;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        if ($this->supports($request)) {
            try {
                $data = $this->payPalWebhookDataProvider->provide($this->getPayPalPaymentUrl($request), 'self');

                try {
                    /** @var PaymentInterface $payment */
                    $payment = $this->paymentProvider->getByPayPalOrderId((string)$data['id']);
                } catch (PaymentNotFoundException $exception) {
                    return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
                }

                if ($payment->getDetails()['status'] === StatusAction::STATUS_CREATED) {
                    /** @var PaymentMethodInterface $paymentMethod */
                    $paymentMethod = $payment->getMethod();
                    $token = $this->authorizeClientApi->authorize($paymentMethod);

                    // Capture order to complete it
                    $detailsComplete = $this->completeOrderApi->complete($token, $data['id']);

                    // Retrieve order details
                    $details = $this->orderDetailsApi->get($token, $data['id']);

                    $order = $payment->getOrder();
                    $totalPaypal = (int) round($details['purchase_units'][0]['amount']['value'] * 100);

                    // Update payment total with the Paypal total (partially paid state will be triggered)
                    if ($totalPaypal != $order->getTotal()) {
                        $this->payPalPaymentUpdater->updateAmount($payment, $totalPaypal);
                        $this->orderPaymentStateResolver->resolve($order);
                    }

                    if ($this->getDetailsStatus($details)) {
                        $detailsPayment = array_merge($payment->getDetails(), [
                            'status' => $details['status'] === 'COMPLETED' ? StatusAction::STATUS_COMPLETED : StatusAction::STATUS_PROCESSING,
                            'paypal_order_details' => $details
                        ]);

                        if ($this->getDetailsStatus($detailsComplete) === 'COMPLETED') {
                            $detailsPayment['transaction_id'] = $details['purchase_units'][0]['payments']['captures'][0]['id'];
                        }

                        $payment->setDetails($detailsPayment);
                        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

                        switch ($details['status']) {
                            case 'COMPLETED':
                                if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                                    $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                                }
                                break;
                            case 'PROCESSING':
                                if ($stateMachine->can(PaymentTransitions::TRANSITION_PROCESS)) {
                                    $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
                                }
                                break;
                            default: // No implementation explicit
                                break;
                        }

                        $this->paymentManager->flush();
                    }
                }
            } catch (RequestException $requestException) {
                $this->logger->debug('error: ' . $requestException->getMessage());
                return new JsonResponse(['error' => $requestException->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse([], Response::HTTP_OK);
        }

        return new JsonResponse([], Response::HTTP_BAD_REQUEST);
    }

    public function supports(Request $request): bool
    {
        $content = (array)json_decode((string)$request->getContent(false), true);
        Assert::keyExists($content, 'event_type');

        return ($content['event_type'] === self::WEBHOOK_EVENT);
    }

    private function getPayPalPaymentUrl(Request $request): string
    {
        $content = (array)json_decode((string)$request->getContent(false), true);
        Assert::keyExists($content, 'resource');
        $resource = (array)$content['resource'];
        Assert::keyExists($resource, 'links');

        /** @var string[] $link */
        foreach ($resource['links'] as $link) {
            if ($link['rel'] === 'self') {
                return (string)$link['href'];
            }
        }

        throw new PayPalWrongDataException();
    }

    private function getDetailsStatus($orderDetails)
    {
        if (!isset($orderDetails['status']))
            return false;
        return $orderDetails['status'];
    }
}
