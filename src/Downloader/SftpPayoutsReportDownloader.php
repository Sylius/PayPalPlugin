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

namespace Sylius\PayPalPlugin\Downloader;

use phpseclib\Net\SFTP;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;
use Sylius\PayPalPlugin\Model\Report;

final class SftpPayoutsReportDownloader implements PayoutsReportDownloaderInterface
{
    private SFTP $sftp;

    public function __construct(SFTP $sftp)
    {
        $this->sftp = $sftp;
    }

    public function downloadFor(\DateTimeInterface $day, PaymentMethodInterface $paymentMethod): Report
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        if (!isset($config['partner_attribution_id'])) {
            throw new PayPalReportDownloadException();
        }

        /** @var string $partnerAttributionId */
        $partnerAttributionId = $config['partner_attribution_id'];

        /** @var string $reportsSftpUsername */
        $reportsSftpUsername = $config['reports_sftp_username'];

        /** @var string $reportsSftpPassword */
        $reportsSftpPassword = $config['reports_sftp_password'];

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
