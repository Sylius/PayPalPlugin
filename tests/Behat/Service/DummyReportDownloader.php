<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Service;

use Sylius\PayPalPlugin\Downloader\ReportDownloaderInterface;

final class DummyReportDownloader implements ReportDownloaderInterface
{
    public function downloadLatest(): string
    {
        return 'LATEST DUMMY REPORT';
    }
}
