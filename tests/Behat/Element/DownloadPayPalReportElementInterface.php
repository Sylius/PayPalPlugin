<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

interface DownloadPayPalReportElementInterface
{
    public function downloadReport(): void;

    public function isCsvReportDownloaded(): bool;
}
