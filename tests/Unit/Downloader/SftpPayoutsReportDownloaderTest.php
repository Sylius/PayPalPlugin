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

namespace Tests\Sylius\PayPalPlugin\Unit\Downloader;

use phpseclib3\Net\SFTP;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Sylius\PayPalPlugin\Downloader\SftpPayoutsReportDownloader;
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;
use Sylius\PayPalPlugin\Factory\SftpClientFactoryInterface;
use Sylius\PayPalPlugin\Model\Report;
use Sylius\PayPalPlugin\Provider\PayPalHostProviderInterface;

final class SftpPayoutsReportDownloaderTest extends TestCase
{
    private SFTP&MockObject $sftp;

    private SftpClientFactoryInterface&MockObject $sftpClientFactory;

    private PayPalHostProviderInterface&MockObject $hostProvider;

    private SftpPayoutsReportDownloader $sftpPayoutsReportDownloader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sftp = $this->createMock(SFTP::class);
        $this->sftpClientFactory = $this->createMock(SftpClientFactoryInterface::class);
        $this->hostProvider = $this->createMock(PayPalHostProviderInterface::class);
        $this->hostProvider->method('getReportsSftpHostForMode')->willReturn('reports.paypal.com');
        $this->sftpClientFactory->method('createForHost')->willReturn($this->sftp);
        $this->sftpPayoutsReportDownloader = new SftpPayoutsReportDownloader(
            $this->sftpClientFactory,
            $this->hostProvider,
        );
    }

    #[Test]
    public function it_implements_payouts_report_downloader_interface(): void
    {
        self::assertInstanceOf(PayoutsReportDownloaderInterface::class, $this->sftpPayoutsReportDownloader);
    }

    #[Test]
    public function it_returns_content_of_the_latest_pyt_report_from_paypal_sftp_server(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'sandbox' => false,
                'partner_attribution_id' => 'PARTNER-ID',
                'reports_sftp_username' => 'SFTP-USERNAME',
                'reports_sftp_password' => 'SFTP-PASSWORD',
            ]);

        $this->sftp
            ->expects(self::once())
            ->method('login')
            ->with('SFTP-USERNAME', 'SFTP-PASSWORD')
            ->willReturn(true);

        $yesterday = new \DateTime('-1 day');
        $this->sftp
            ->expects(self::once())
            ->method('get')
            ->with(sprintf('ppreports/outgoing/PYT.%s.PARTNER-ID.R.0.2.0.CSV', $yesterday->format('Ymd')))
            ->willReturn('REPORT-CONTENT');

        $result = $this->sftpPayoutsReportDownloader->downloadFor($yesterday, $paymentMethod);
        $expected = new Report('REPORT-CONTENT', sprintf('PYT%s.csv', $yesterday->format('Ymd')));

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function it_throws_an_exception_if_payment_method_has_no_partner_attribution_id(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn([]);

        $this->expectException(PayPalReportDownloadException::class);

        $this->sftpPayoutsReportDownloader->downloadFor(new \DateTime(), $paymentMethod);
    }

    #[Test]
    public function it_throws_an_exception_if_credentials_are_invalid(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'sandbox' => false,
                'partner_attribution_id' => 'PARTNER-ID',
                'reports_sftp_username' => 'SFTP-USERNAME',
                'reports_sftp_password' => 'SFTP-PASSWORD',
            ]);

        $this->sftp
            ->expects(self::once())
            ->method('login')
            ->with('SFTP-USERNAME', 'SFTP-PASSWORD')
            ->willReturn(false);

        $this->expectException(PayPalReportDownloadException::class);

        $this->sftpPayoutsReportDownloader->downloadFor(new \DateTime(), $paymentMethod);
    }

    #[Test]
    public function it_throws_an_exception_if_there_is_no_report_with_given_name(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'sandbox' => false,
                'partner_attribution_id' => 'PARTNER-ID',
                'reports_sftp_username' => 'SFTP-USERNAME',
                'reports_sftp_password' => 'SFTP-PASSWORD',
            ]);

        $this->sftp
            ->expects(self::once())
            ->method('login')
            ->with('SFTP-USERNAME', 'SFTP-PASSWORD')
            ->willReturn(true);

        $yesterday = new \DateTime('-1 day');
        $this->sftp
            ->expects(self::once())
            ->method('get')
            ->with(sprintf('ppreports/outgoing/PYT.%s.PARTNER-ID.R.0.2.0.CSV', $yesterday->format('Ymd')))
            ->willReturn(false);

        $this->expectException(PayPalReportDownloadException::class);

        $this->sftpPayoutsReportDownloader->downloadFor($yesterday, $paymentMethod);
    }
}
