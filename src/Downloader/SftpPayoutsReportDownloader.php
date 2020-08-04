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
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;
use Sylius\PayPalPlugin\Model\Report;

final class SftpPayoutsReportDownloader implements PayoutsReportDownloaderInterface
{
    /** @var SFTP */
    private $sftp;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    public function __construct(SFTP $sftp, string $username, string $password)
    {
        $this->sftp = $sftp;
        $this->username = $username;
        $this->password = $password;
    }

    public function downloadFor(\DateTimeInterface $day): Report
    {
        if (!$this->sftp->login($this->username, $this->password)) {
            throw new PayPalReportDownloadException();
        }

        $reportContent = $this
            ->sftp
            ->get(sprintf('ppreports/outgoing/PYT.%s.sylius-ppcp4p-bn-code.R.0.2.0.CSV', $day->format('Ymd')))
        ;

        if ($reportContent === false) {
            throw new PayPalReportDownloadException();
        }

        return new Report((string) $reportContent, sprintf('PYT%s.csv', $day->format('Ymd')));
    }
}
