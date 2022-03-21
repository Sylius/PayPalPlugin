<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Monolog\Logger;
use Sylius\PayPalPlugin\Provider\PayPalWebhookDataProviderInterface;
use Sylius\PayPalPlugin\Service\WebhookService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentCaptureDeniedAction extends AbstractWebhookAction
{
    protected string $webhookEvent = 'PAYMENT.CAPTURE.DENIED';

    public function __construct(
        WebhookService $webhookService,
        PayPalWebhookDataProviderInterface $payPalWebhookDataProvider,
        Logger $logger
    )
    {
        parent::__construct($webhookService, $payPalWebhookDataProvider, $logger);
    }

    public function __invoke(Request $request): Response
    {
        return parent::__invoke($request);
    }
}
