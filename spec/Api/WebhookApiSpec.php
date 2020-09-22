<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Api;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class WebhookApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_registers_webhook(PayPalClientInterface $client): void
    {
        $client->post(
            'v1/notifications/webhooks',
            'TOKEN',
            Argument::that(function ($data): bool {
                return
                    $data['url'] === 'https://webhook.com' &&
                    $data['event_types'][0]['name'] === 'PAYMENT.CAPTURE.REFUNDED';
            })
        )->shouldBeCalled();

        $this->register('TOKEN', 'https://webhook.com');
    }

    function it_registers_webhook_without_https(PayPalClientInterface $client): void
    {
        $client->post(
            'v1/notifications/webhooks',
            'TOKEN',
            Argument::that(function ($data): bool {
                return
                    $data['url'] === 'https://webhook.com' &&
                    $data['event_types'][0]['name'] === 'PAYMENT.CAPTURE.REFUNDED';
            })
        )->shouldBeCalled();

        $this->register('TOKEN', 'http://webhook.com');
    }
}
