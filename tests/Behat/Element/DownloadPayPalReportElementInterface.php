<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

interface DownloadPayPalReportElementInterface
{
    public function downloadReport(string $paymentMethod): void;

    public function isCsvReportDownloaded(): bool;
}
