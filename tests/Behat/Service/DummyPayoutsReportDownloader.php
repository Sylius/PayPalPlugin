<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Service;

use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;

final class DummyPayoutsReportDownloader implements PayoutsReportDownloaderInterface
{
    public function downloadFor(\DateTimeInterface $day): string
    {
        return 'LATEST DUMMY REPORT';
    }
}
