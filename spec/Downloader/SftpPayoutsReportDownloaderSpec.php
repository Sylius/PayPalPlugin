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

namespace spec\Sylius\PayPalPlugin\Downloader;

use Payum\Core\Model\GatewayConfigInterface;
use phpseclib\Net\SFTP;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;
use Sylius\PayPalPlugin\Model\Report;

final class SftpPayoutsReportDownloaderSpec extends ObjectBehavior
{
    function let(SFTP $sftp): void
    {
        $this->beConstructedWith($sftp);
    }

    function it_implements_payouts_report_downloader_interface(): void
    {
        $this->shouldImplement(PayoutsReportDownloaderInterface::class);
    }

    function it_returns_content_of_the_latest_pyt_report_from_pay_pal_sftp_server(
        SFTP $sftp,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(
            [
                'partner_attribution_id' => 'PARTNER-ID',
                'reports_sftp_username' => 'SFTP-USERNAME',
                'reports_sftp_password' => 'SFTP-PASSWORD',
            ]
        );

        $sftp->login('SFTP-USERNAME', 'SFTP-PASSWORD')->willReturn(true);

        $yesterday = new \DateTime('-1 day');
        $sftp
            ->get(sprintf('ppreports/outgoing/PYT.%s.PARTNER-ID.R.0.2.0.CSV', $yesterday->format('Ymd')))
            ->willReturn('REPORT-CONTENT')
        ;

        $this
            ->downloadFor($yesterday, $paymentMethod)
            ->shouldBeLike(new Report('REPORT-CONTENT', sprintf('PYT%s.csv', $yesterday->format('Ymd'))))
        ;
    }

    function it_throws_an_exception_if_payment_method_has_no_partner_attribution_id(
        SFTP $sftp,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn([]);

        $this
            ->shouldThrow(PayPalReportDownloadException::class)
            ->during('downloadFor', [new \DateTime(), $paymentMethod])
        ;
    }

    function it_throws_an_exception_if_credentials_are_invalid(
        SFTP $sftp,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(
            [
                'partner_attribution_id' => 'PARTNER-ID',
                'reports_sftp_username' => 'SFTP-USERNAME',
                'reports_sftp_password' => 'SFTP-PASSWORD',
            ]
        );

        $sftp->login('SFTP-USERNAME', 'SFTP-PASSWORD')->willReturn(false);

        $this
            ->shouldThrow(PayPalReportDownloadException::class)
            ->during('downloadFor', [new \DateTime(), $paymentMethod])
        ;
    }

    function it_throws_an_exception_if_there_is_no_report_with_given_name(
        SFTP $sftp,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(
            [
                'partner_attribution_id' => 'PARTNER-ID',
                'reports_sftp_username' => 'SFTP-USERNAME',
                'reports_sftp_password' => 'SFTP-PASSWORD',
            ]
        );

        $sftp->login('SFTP-USERNAME', 'SFTP-PASSWORD')->willReturn(true);

        $yesterday = new \DateTime('-1 day');
        $sftp
            ->get(sprintf('ppreports/outgoing/PYT.%s.PARTNER-ID.R.0.2.0.CSV', $yesterday->format('Ymd')))
            ->willReturn(false)
        ;

        $this
            ->shouldThrow(PayPalReportDownloadException::class)
            ->during('downloadFor', [$yesterday, $paymentMethod])
        ;
    }
}
