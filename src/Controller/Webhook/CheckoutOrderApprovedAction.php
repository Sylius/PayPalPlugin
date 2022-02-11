<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
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

    public function __construct(
        FactoryInterface                   $stateMachineFactory,
        PaymentProviderInterface           $paymentProvider,
        ObjectManager                      $paymentManager,
        OrderDetailsApiInterface           $orderDetailsApi,
        PayPalWebhookDataProviderInterface $payPalWebhookDataProvider,
        CacheAuthorizeClientApiInterface   $authorizeClientApi,
        CompleteOrderApiInterface          $completeOrderApi
    )
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->payPalWebhookDataProvider = $payPalWebhookDataProvider;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->completeOrderApi = $completeOrderApi;
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
                    $this->completeOrderApi->complete($token, $data['id']);

                    // Retrieve order details
                    $details = $this->orderDetailsApi->get($token, $data['id']);
                    $payment->setDetails([
                        'status' => $details['status'] === 'COMPLETED' ? StatusAction::STATUS_COMPLETED : StatusAction::STATUS_PROCESSING,
                        'paypal_order_id' => $details['id'],
                        'reference_id' => $details['purchase_units'][0]['reference_id'],
                    ]);

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
}
