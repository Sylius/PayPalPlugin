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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApi;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutorInterface;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;

final class MerchantOnboardingStatusApiTest extends TestCase
{
    private PayPalOnboardingRequestExecutorInterface&MockObject $requestExecutor;

    private RequestFactoryInterface&MockObject $requestFactory;

    private MerchantOnboardingStatusApi $merchantOnboardingStatusApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestExecutor = $this->createMock(PayPalOnboardingRequestExecutorInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);

        $this->merchantOnboardingStatusApi = new MerchantOnboardingStatusApi(
            $this->requestExecutor,
            $this->requestFactory,
            $this->hostProvider(),
        );
    }

    #[Test]
    public function it_returns_a_complete_onboarding_status(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'http://sandbox-base-url.com/v1/customer/partners/PARTNER-ID/merchant-integrations/MERCHANT-ID')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);

        $this->requestExecutor
            ->expects(self::once())
            ->method('execute')
            ->with($request, 'Onboarding status')
            ->willReturn(['payments_receivable' => true, 'primary_email_confirmed' => true]);

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true);

        self::assertTrue($status->arePaymentsReceivable());
        self::assertTrue($status->isPrimaryEmailConfirmed());
        self::assertTrue($status->isComplete());
    }

    #[Test]
    public function it_returns_an_incomplete_status_when_flags_are_missing(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn(['payments_receivable' => true]);

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', false);

        self::assertTrue($status->arePaymentsReceivable());
        self::assertFalse($status->isPrimaryEmailConfirmed());
        self::assertFalse($status->isComplete());
    }

    #[Test]
    public function it_reads_the_vaulting_capability_status(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn([
            'payments_receivable' => true,
            'primary_email_confirmed' => true,
            'capabilities' => [
                ['name' => 'APPLE_PAY', 'status' => 'ACTIVE'],
                ['name' => 'PAYPAL_WALLET_VAULTING_ADVANCED', 'status' => 'ACTIVE'],
            ],
        ]);

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true);

        self::assertSame('ACTIVE', $status->getVaultingCapabilityStatus());
        self::assertTrue($status->isVaultingEnabled());
    }

    #[Test]
    public function it_treats_an_inactive_vaulting_capability_as_disabled(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn([
            'capabilities' => [['name' => 'PAYPAL_WALLET_VAULTING_ADVANCED', 'status' => 'NEED_DATA']],
        ]);

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true);

        self::assertSame('NEED_DATA', $status->getVaultingCapabilityStatus());
        self::assertFalse($status->isVaultingEnabled());
    }

    #[Test]
    public function it_has_no_vaulting_capability_status_when_the_capability_is_missing(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn([
            'capabilities' => [['name' => 'APPLE_PAY', 'status' => 'ACTIVE']],
        ]);

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true);

        self::assertNull($status->getVaultingCapabilityStatus());
        self::assertFalse($status->isVaultingEnabled());
    }

    private function hostProvider(): HostProviderInterface
    {
        $hostProvider = $this->createStub(HostProviderInterface::class);
        $hostProvider->method('getApiBaseUrlForMode')->willReturnMap([
            [true, 'http://sandbox-base-url.com/'],
            [false, 'http://base-url.com/'],
        ]);

        return $hostProvider;
    }
}
