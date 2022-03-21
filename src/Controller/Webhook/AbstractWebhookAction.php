<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;
use SM\SMException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Provider\PayPalWebhookDataProviderInterface;
use Sylius\PayPalPlugin\Service\WebhookService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

abstract class AbstractWebhookAction implements WebhookActionInterface
{
    protected string $webhookEvent = '';
    private WebhookService $webhookService;
    private PayPalWebhookDataProviderInterface $payPalWebhookDataProvider;
    private Logger $logger;

    public function __construct(
        WebhookService                     $webhookService,
        PayPalWebhookDataProviderInterface $payPalWebhookDataProvider,
        Logger                             $logger
    )
    {
        $this->webhookService = $webhookService;
        $this->payPalWebhookDataProvider = $payPalWebhookDataProvider;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        if ($this->supports($request)) {
            try {
                $data = $this->payPalWebhookDataProvider->provide(
                    $this->getPayPalPaymentUrl($request),
                    $this->getRelWebhookDataProvider()
                );
                $this->webhookService->handlePaypalOrder((string)$data['id']);

            } catch (RequestException|PayPalWrongDataException|SMException $exception) {
                $this->logger->debug('[' . $this->webhookEvent . '] error: ' . $exception->getMessage());
                return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse([], Response::HTTP_OK);
        }

        return new JsonResponse([], Response::HTTP_BAD_REQUEST);
    }

    public function supports(Request $request): bool
    {
        Assert::notEmpty($this->webhookEvent, 'Paypal webhookEvent can not be empty');
        $content = (array)json_decode((string)$request->getContent(false), true);
        Assert::keyExists($content, 'event_type');

        return ($content['event_type'] === $this->webhookEvent);
    }

    /**
     * @param Request $request
     * @return string
     * @throws PayPalWrongDataException
     */
    public function getPayPalPaymentUrl(Request $request): string
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

    /**
     * @return string
     */
    public function getRelWebhookDataProvider(): string
    {
        return 'up';
    }
}
