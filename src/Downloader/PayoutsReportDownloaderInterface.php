<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Downloader;

use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;

interface PayoutsReportDownloaderInterface
{
    /**
     * @throws PayPalReportDownloadException
     *
     * @return string Content of the latest report
     */
    public function downloadFor(\DateTimeInterface $day): string;
}
