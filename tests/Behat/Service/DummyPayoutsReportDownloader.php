<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Service;

use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Sylius\PayPalPlugin\Model\Report;

final class DummyPayoutsReportDownloader implements PayoutsReportDownloaderInterface
{
    public function downloadFor(\DateTimeInterface $day): Report
    {
        return new Report('DUMMY REPORT', 'report.csv');
    }
}
