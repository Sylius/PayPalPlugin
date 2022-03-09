<?php

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Payment\Provider\OrderPaymentProviderInterface;
use Sylius\Component\Payment\Model\PaymentInterface as PaymentInterfaceAlias;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebhookDataProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class PaymentCaptureDeniedAction
{
    const WEBHOOK_EVENT = 'PAYMENT.CAPTURE.DENIED';

    private FactoryInterface $stateMachineFactory;
    private PaymentProviderInterface $paymentProvider;
    private ObjectManager $paymentManager;
    private OrderDetailsApiInterface $orderDetailsApi;
    private PayPalWebhookDataProviderInterface $payPalWebhookDataProvider;
    private CacheAuthorizeClientApiInterface $authorizeClientApi;
    private OrderPaymentProviderInterface $orderPaymentProvider;
    private Logger $logger;

    public function __construct(
        FactoryInterface                   $stateMachineFactory,
        PaymentProviderInterface           $paymentProvider,
        ObjectManager                      $paymentManager,
        OrderDetailsApiInterface           $orderDetailsApi,
        PayPalWebhookDataProviderInterface $payPalWebhookDataProvider,
        CacheAuthorizeClientApiInterface   $authorizeClientApi,
        OrderPaymentProviderInterface  $orderPaymentProvider,
        Logger $logger
    )
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->payPalWebhookDataProvider = $payPalWebhookDataProvider;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->orderPaymentProvider = $orderPaymentProvider;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        if ($this->supports($request)) {
            try {
                $data = $this->payPalWebhookDataProvider->provide($this->getPayPalPaymentUrl($request), 'up');

                try {
                    /** @var PaymentInterface $payment */
                    $payment = $this->paymentProvider->getByPayPalOrderId((string)$data['id']);

                    /** @var OrderInterface $order */
                    $order = $payment->getOrder();

                } catch (PaymentNotFoundException $exception) {
                    return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
                }

                /** @var PaymentMethodInterface $paymentMethod */
                $paymentMethod = $payment->getMethod();
                $token = $this->authorizeClientApi->authorize($paymentMethod);

                // Retrieve order details
                $details = $this->orderDetailsApi->get($token, $data['id']);

                if ($this->getDetailsStatus($details) === 'DECLINED') {
                    $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                    if ($stateMachine->can(PaymentTransitions::TRANSITION_PROCESS)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
                    }

                    $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                    if ($stateMachine->can(PaymentTransitions::TRANSITION_FAIL)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);
                    }

                    $newPayment = $this->orderPaymentProvider->provideOrderPayment($order, PaymentInterfaceAlias::STATE_CART);
                    if($newPayment) {
                        $order->addPayment($newPayment);
                    }
                }

                $this->paymentManager->flush();
            } catch (RequestException $requestException) {
                $this->logger->error($requestException->getMessage());
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
