<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\AuthorizePaymentOrderApiInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Exception\PayPalWrongWebhookException;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebhookDataProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class PaymentCaptureCompletedAction
{
    const WEBHOOK_EVENT = 'PAYMENT.CAPTURE.COMPLETED';

    private FactoryInterface $stateMachineFactory;
    private PaymentProviderInterface $paymentProvider;
    private ObjectManager $paymentManager;
    private OrderDetailsApiInterface $orderDetailsApi;
    private PayPalWebhookDataProviderInterface $payPalWebhookDataProvider;
    private CacheAuthorizeClientApiInterface $authorizeClientApi;

    public function __construct(
        FactoryInterface                   $stateMachineFactory,
        PaymentProviderInterface           $paymentProvider,
        ObjectManager                      $paymentManager,
        OrderDetailsApiInterface           $orderDetailsApi,
        PayPalWebhookDataProviderInterface $payPalWebhookDataProvider,
        CacheAuthorizeClientApiInterface   $authorizeClientApi
    )
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->payPalWebhookDataProvider = $payPalWebhookDataProvider;
        $this->authorizeClientApi = $authorizeClientApi;
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
                if ($this->getDetailsStatus($details) === 'COMPLETED') {
                    $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

                    if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                    }

                    $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);
                    if ($stateMachine->can(OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
                        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);
                    }

                    $paymentDetails = $payment->getDetails();
                    $paymentDetails['status'] = StatusAction::STATUS_COMPLETED;

                    $payment->setDetails($paymentDetails);
                }

                $this->paymentManager->flush();
            } catch (RequestException $requestException) {
                return new JsonResponse(['error' => $requestException->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        return new JsonResponse([], Response::HTTP_OK);
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
