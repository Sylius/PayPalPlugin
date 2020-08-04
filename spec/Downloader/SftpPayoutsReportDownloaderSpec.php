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

use phpseclib\Net\SFTP;
use PhpSpec\ObjectBehavior;
use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;

final class SftpPayoutsReportDownloaderSpec extends ObjectBehavior
{
    function let(SFTP $sftp): void
    {
        $this->beConstructedWith($sftp, 'login', 'password');
    }

    function it_implements_payouts_report_downloader_interface(): void
    {
        $this->shouldImplement(PayoutsReportDownloaderInterface::class);
    }

    function it_returns_content_of_the_latest_pyt_report_from_pay_pal_sftp_server(SFTP $sftp): void
    {
        $sftp->login('login', 'password')->willReturn(true);

        $yesterday = new \DateTime('-1 day');
        $sftp
            ->get(sprintf('ppreports/outgoing/PYT.%s.sylius-ppcp4p-bn-code.R.0.2.0.CSV', $yesterday->format('Ymd')))
            ->willReturn('REPORT-CONTENT')
        ;

        $this->downloadFor($yesterday)->shouldReturn('REPORT-CONTENT');
    }

    function it_throws_an_exception_if_credentials_are_invalid(SFTP $sftp): void
    {
        $sftp->login('login', 'password')->willReturn(false);

        $this
            ->shouldThrow(PayPalReportDownloadException::class)
            ->during('downloadFor', [new \DateTime()])
        ;
    }

    function it_throws_an_exception_if_there_is_no_report_with_given_name(SFTP $sftp): void
    {
        $sftp->login('login', 'password')->willReturn(true);

        $yesterday = new \DateTime('-1 day');
        $sftp
            ->get(sprintf('ppreports/outgoing/PYT.%s.sylius-ppcp4p-bn-code.R.0.2.0.CSV', $yesterday->format('Ymd')))
            ->willReturn(false)
        ;

        $this
            ->shouldThrow(PayPalReportDownloadException::class)
            ->during('downloadFor', [$yesterday])
        ;
    }
}
