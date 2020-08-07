<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

interface DownloadPayPalReportElementInterface
{
    public function downloadReport(string $paymentMethod): void;

    public function isCsvReportDownloaded(): bool;
}
