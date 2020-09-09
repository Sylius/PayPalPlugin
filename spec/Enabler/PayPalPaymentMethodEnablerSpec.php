<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Enabler;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Client;
use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;

final class PayPalPaymentMethodEnablerSpec extends ObjectBehavior
{
    function let(
        Client $client,
        ObjectManager $paymentMethodManager
    ): void {
        $this->beConstructedWith($client, 'http://base-url.com', $paymentMethodManager);
    }

    function it_implements_payment_method_enabler_interface(): void
    {
        $this->shouldImplement(PaymentMethodEnablerInterface::class);
    }

    function it_enables_payment_method_if_it_has_proper_credentials_set(
        Client $client,
        ObjectManager $paymentMethodManager,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['merchant_id' => '123123']);

        $client->request('GET', 'http://base-url.com/seller-permissions/check/123123')->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "permissionsGranted": true }');

        $paymentMethod->setEnabled(true)->shouldBeCalled();
        $paymentMethodManager->flush()->shouldBeCalled();

        $this->enable($paymentMethod);
    }

    function it_throws_exception_if_payment_method_credentials_are_not_granted(
        Client $client,
        ObjectManager $paymentMethodManager,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['merchant_id' => '123123']);

        $client->request('GET', 'http://base-url.com/seller-permissions/check/123123')->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "permissionsGranted": false }');

        $paymentMethod->setEnabled(true)->shouldNotBeCalled();
        $paymentMethodManager->flush()->shouldNotBeCalled();

        $this
            ->shouldThrow(PaymentMethodCouldNotBeEnabledException::class)
            ->during('enable', [$paymentMethod])
        ;
    }
}
