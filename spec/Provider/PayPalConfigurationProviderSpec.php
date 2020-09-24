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

namespace spec\Sylius\PayPalPlugin\Provider;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;

final class PayPalConfigurationProviderSpec extends ObjectBehavior
{
    function let(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ChannelContextInterface $channelContext,
        ChannelInterface $channel
    ): void {
        $channelContext->getChannel()->willReturn($channel);

        $this->beConstructedWith($paymentMethodRepository, $channelContext);
    }

    function it_implements_pay_pal_configuration_provider_interface(): void
    {
        $this->shouldImplement(PayPalConfigurationProviderInterface::class);
    }

    function it_returns_client_id_from_payment_method_config(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$otherPaymentMethod, $payPalPaymentMethod])
        ;

        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $payPalGatewayConfig->getConfig()->willReturn(['client_id' => '123123']);

        $this->getClientId()->shouldReturn('123123');
    }

    function it_returns_api_base_url_from_payment_method_config(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$payPalPaymentMethod], [$payPalPaymentMethod])
        ;

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig, $payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal', 'sylius.pay_pal');

        $payPalGatewayConfig
            ->getConfig()
            ->willReturn(['sandbox' => true], ['sandbox' => false])
        ;

        $this->getApiBaseUrl()->shouldReturn('https://api.sandbox.paypal.com');
        $this->getApiBaseUrl()->shouldReturn('https://api.paypal.com');
    }

    function it_returns_facilitator_url_from_payment_method_config(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$payPalPaymentMethod], [$payPalPaymentMethod])
        ;

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig, $payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal', 'sylius.pay_pal');

        $payPalGatewayConfig
            ->getConfig()
            ->willReturn(['sandbox' => true], ['sandbox' => false])
        ;

        $this->getFacilitatorUrl()->shouldReturn('https://paypal.sylius.com');
        $this->getFacilitatorUrl()->shouldReturn('https://prod.paypal.sylius.com');
    }

    function it_returns_reports_sftp_host_from_payment_method_config(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$payPalPaymentMethod], [$payPalPaymentMethod])
        ;

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig, $payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal', 'sylius.pay_pal');

        $payPalGatewayConfig
            ->getConfig()
            ->willReturn(['sandbox' => true], ['sandbox' => false])
        ;

        $this->getReportsSftpHost()->shouldReturn('reports.sandbox.paypal.com');
        $this->getReportsSftpHost()->shouldReturn('reports.paypal.com');
    }

    function it_returns_partner_attribution_id_from_payment_method_config(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$otherPaymentMethod, $payPalPaymentMethod])
        ;

        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $payPalGatewayConfig->getConfig()->willReturn(['partner_attribution_id' => '123123']);

        $this->getPartnerAttributionId()->shouldReturn('123123');
    }

    function it_throws_an_exception_if_there_is_no_pay_pal_payment_method_defined(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository->findEnabledForChannel($channel)->willReturn([$otherPaymentMethod]);
        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('getClientID', [])
        ;

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('getPartnerAttributionId', [])
        ;
    }

    function it_throws_an_exception_if_there_is_no_client_id_on_pay_pal_payment_method(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$otherPaymentMethod, $payPalPaymentMethod])
        ;

        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $payPalGatewayConfig->getConfig()->willReturn([]);

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('getClientID', [])
        ;
    }

    function it_throws_an_exception_if_there_is_no_partner_attribution_id_on_pay_pal_payment_method(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$otherPaymentMethod, $payPalPaymentMethod])
        ;

        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $payPalGatewayConfig->getConfig()->willReturn([]);

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('getPartnerAttributionId', [])
        ;
    }
}
