<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Downloader;

use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;

interface ReportDownloaderInterface
{
    /**
     * @throws PayPalReportDownloadException
     *
     * @return string Content of the latest report
     */
    public function downloadLatest(): string;
}
