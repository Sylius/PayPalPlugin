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
