<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Sylius\PayPalPlugin\Model\Report;

final class DummyPayoutsReportDownloader implements PayoutsReportDownloaderInterface
{
    public function downloadFor(\DateTimeInterface $day, PaymentMethodInterface $paymentMethod): Report
    {
        return new Report('DUMMY REPORT', 'report.csv');
    }
}
