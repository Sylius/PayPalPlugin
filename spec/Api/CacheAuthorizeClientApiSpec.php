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

namespace spec\Sylius\PayPalPlugin\Api;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class CacheAuthorizeClientApiSpec extends ObjectBehavior
{
    function let(
        ObjectManager $payPalCredentialsManager,
        ObjectRepository $payPalCredentialsRepository,
        AuthorizeClientApiInterface $authorizeClientApi,
        UuidProviderInterface $uuidProvider
    ): void {
        $this->beConstructedWith(
            $payPalCredentialsManager,
            $payPalCredentialsRepository,
            $authorizeClientApi,
            $uuidProvider
        );
    }

    function it_implements_cache_authorize_client_api_interface(): void
    {
        $this->shouldImplement(CacheAuthorizeClientApiInterface::class);
    }

    function it_returns_cached_access_token_if_it_is_not_expired(
        ObjectRepository $payPalCredentialsRepository,
        PayPalCredentialsInterface $payPalCredentials,
        PaymentMethodInterface $paymentMethod
    ): void {
        $payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod])->willReturn($payPalCredentials);

        $payPalCredentials->isExpired()->willReturn(false);
        $payPalCredentials->accessToken()->willReturn('TOKEN');

        $this->authorize($paymentMethod)->shouldReturn('TOKEN');
    }

    function it_gets_access_token_from_api_caches_and_returns_it(
        ObjectManager $payPalCredentialsManager,
        ObjectRepository $payPalCredentialsRepository,
        AuthorizeClientApiInterface $authorizeClientApi,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        UuidProviderInterface $uuidProvider
    ): void {
        $payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod])->willReturn(null);

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => '$ECRET']);

        $authorizeClientApi->authorize('CLIENT_ID', '$ECRET')->willReturn('TOKEN');

        $uuidProvider->provide()->willReturn('UUID');

        $payPalCredentialsManager
            ->persist(Argument::that(function (PayPalCredentialsInterface $payPalCredentials) use ($paymentMethod): bool {
                return
                    $payPalCredentials->accessToken() === 'TOKEN' &&
                    $payPalCredentials->creationTime()->format('d-m-Y H:i') === (new \DateTime())->format('d-m-Y H:i') &&
                    $payPalCredentials->expirationTime()->format('d-m-Y H:i') === (new \DateTime())->modify('+3600 seconds')->format('d-m-Y H:i') &&
                    $payPalCredentials->paymentMethod() === $paymentMethod->getWrappedObject()
                ;
            }))
            ->shouldBeCalled()
        ;
        $payPalCredentialsManager->flush()->shouldBeCalled();

        $this->authorize($paymentMethod)->shouldReturn('TOKEN');
    }

    function it_returns_expired_token_and_ask_for_a_new_one(
        ObjectManager $payPalCredentialsManager,
        ObjectRepository $payPalCredentialsRepository,
        AuthorizeClientApiInterface $authorizeClientApi,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        PayPalCredentialsInterface $payPalCredentials,
        UuidProviderInterface $uuidProvider
    ): void {
        $payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod])->willReturn($payPalCredentials);
        $payPalCredentials->isExpired()->willReturn(true);

        $payPalCredentialsManager->remove($payPalCredentials)->shouldBeCalled();
        $payPalCredentialsManager->flush()->shouldBeCalledTimes(2);

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => '$ECRET']);

        $uuidProvider->provide()->willReturn('UUID');

        $authorizeClientApi->authorize('CLIENT_ID', '$ECRET')->willReturn('TOKEN');

        $payPalCredentialsManager
            ->persist(Argument::that(function (PayPalCredentialsInterface $payPalCredentials) use ($paymentMethod): bool {
                return
                    $payPalCredentials->accessToken() === 'TOKEN' &&
                    $payPalCredentials->creationTime()->format('d-m-Y H:i') == (new \DateTime())->format('d-m-Y H:i') &&
                    $payPalCredentials->expirationTime()->format('d-m-Y H:i') == (new \DateTime())->modify('+3600 seconds')->format('d-m-Y H:i') &&
                    $payPalCredentials->paymentMethod() === $paymentMethod->getWrappedObject()
                ;
            }))
            ->shouldBeCalled()
        ;
        $payPalCredentialsManager->flush()->shouldBeCalled();

        $this->authorize($paymentMethod)->shouldReturn('TOKEN');
    }
}
