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

namespace Sylius\PayPalPlugin\Downloader;

use phpseclib3\Net\SFTP;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\Report;

final readonly class SftpPayoutsReportDownloader implements PayoutsReportDownloaderInterface
{
    public function __construct(private SFTP $sftp)
    {
    }

    public function downloadFor(\DateTimeInterface $day, PaymentMethodInterface $paymentMethod): Report
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        if (!isset($config[PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID])) {
            throw new PayPalReportDownloadException();
        }

        /** @var string $partnerAttributionId */
        $partnerAttributionId = $config[PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID];

        /** @var string $reportsSftpUsername */
        $reportsSftpUsername = $config[PayPalGatewayConfig::REPORTS_SFTP_USERNAME];

        /** @var string $reportsSftpPassword */
        $reportsSftpPassword = $config[PayPalGatewayConfig::REPORTS_SFTP_PASSWORD];

        if (!$this->sftp->login($reportsSftpUsername, $reportsSftpPassword)) {
            throw new PayPalReportDownloadException();
        }

        $reportContent = $this
            ->sftp
            ->get(sprintf('ppreports/outgoing/PYT.%s.%s.R.0.2.0.CSV', $day->format('Ymd'), $partnerAttributionId))
        ;

        if ($reportContent === false) {
            throw new PayPalReportDownloadException();
        }

        return new Report((string) $reportContent, sprintf('PYT%s.csv', $day->format('Ymd')));
    }
}
