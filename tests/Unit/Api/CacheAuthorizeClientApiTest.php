<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApi;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class CacheAuthorizeClientApiTest extends TestCase
{
    private ObjectManager&MockObject $payPalCredentialsManager;

    /** @var ObjectRepository<PayPalCredentialsInterface>&MockObject */
    private ObjectRepository&MockObject $payPalCredentialsRepository;

    private AuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private UuidProviderInterface&MockObject $uuidProvider;

    private CacheAuthorizeClientApi $cacheAuthorizeClientApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalCredentialsManager = $this->createMock(ObjectManager::class);
        $this->payPalCredentialsRepository = $this->createMock(ObjectRepository::class);
        $this->authorizeClientApi = $this->createMock(AuthorizeClientApiInterface::class);
        $this->uuidProvider = $this->createMock(UuidProviderInterface::class);

        $this->cacheAuthorizeClientApi = new CacheAuthorizeClientApi(
            $this->payPalCredentialsManager,
            $this->payPalCredentialsRepository,
            $this->authorizeClientApi,
            $this->uuidProvider,
        );
    }

    #[Test]
    public function it_implements_cache_authorize_client_api_interface(): void
    {
        self::assertInstanceOf(CacheAuthorizeClientApiInterface::class, $this->cacheAuthorizeClientApi);
    }

    #[Test]
    public function it_returns_cached_access_token_if_it_is_not_expired(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payPalCredentials = $this->createMock(PayPalCredentialsInterface::class);

        $this->payPalCredentialsRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['paymentMethod' => $paymentMethod])
            ->willReturn($payPalCredentials);

        $payPalCredentials
            ->expects(self::once())
            ->method('isExpired')
            ->willReturn(false);

        $payPalCredentials
            ->expects(self::once())
            ->method('accessToken')
            ->willReturn('TOKEN');

        $result = $this->cacheAuthorizeClientApi->authorize($paymentMethod);

        self::assertEquals('TOKEN', $result);
    }

    #[Test]
    public function it_gets_access_token_from_api_caches_and_returns_it(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->payPalCredentialsRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['paymentMethod' => $paymentMethod])
            ->willReturn(null);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => '$ECRET']);

        $this->authorizeClientApi
            ->expects(self::once())
            ->method('authorize')
            ->with('CLIENT_ID', '$ECRET', false)
            ->willReturn('TOKEN');

        $this->uuidProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn('UUID');

        $this->payPalCredentialsManager
            ->expects(self::once())
            ->method('persist')
            ->with($this->callback(function (PayPalCredentialsInterface $payPalCredentials) use ($paymentMethod): bool {
                return
                    $payPalCredentials->accessToken() === 'TOKEN' &&
                    $payPalCredentials->creationTime()->format('d-m-Y H:i') === (new \DateTime())->format('d-m-Y H:i') &&
                    $payPalCredentials->expirationTime()->format('d-m-Y H:i') === (new \DateTime())->modify('+3600 seconds')->format('d-m-Y H:i') &&
                    $payPalCredentials->paymentMethod() === $paymentMethod
                ;
            }));

        $this->payPalCredentialsManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->cacheAuthorizeClientApi->authorize($paymentMethod);

        self::assertEquals('TOKEN', $result);
    }

    #[Test]
    public function it_returns_expired_token_and_ask_for_a_new_one(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $payPalCredentials = $this->createMock(PayPalCredentialsInterface::class);

        $this->payPalCredentialsRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['paymentMethod' => $paymentMethod])
            ->willReturn($payPalCredentials);

        $payPalCredentials
            ->method('isExpired')
            ->willReturn(true);

        $this->payPalCredentialsManager
            ->expects(self::once())
            ->method('remove')
            ->with($payPalCredentials);

        $this->payPalCredentialsManager
            ->expects($this->exactly(2))
            ->method('flush');

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => '$ECRET']);

        $this->uuidProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn('UUID');

        $this->authorizeClientApi
            ->expects(self::once())
            ->method('authorize')
            ->with('CLIENT_ID', '$ECRET', false)
            ->willReturn('TOKEN');

        $this->payPalCredentialsManager
            ->expects(self::once())
            ->method('persist')
            ->with($this->callback(function (PayPalCredentialsInterface $payPalCredentials) use ($paymentMethod): bool {
                return
                    $payPalCredentials->accessToken() === 'TOKEN' &&
                    $payPalCredentials->creationTime()->format('d-m-Y H:i') == (new \DateTime())->format('d-m-Y H:i') &&
                    $payPalCredentials->expirationTime()->format('d-m-Y H:i') == (new \DateTime())->modify('+3600 seconds')->format('d-m-Y H:i') &&
                    $payPalCredentials->paymentMethod() === $paymentMethod
                ;
            }));

        $result = $this->cacheAuthorizeClientApi->authorize($paymentMethod);

        self::assertEquals('TOKEN', $result);
    }
}
