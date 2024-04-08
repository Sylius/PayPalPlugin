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

namespace Sylius\PayPalPlugin\Downloader;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalReportDownloadException;
use Sylius\PayPalPlugin\Model\Report;

interface PayoutsReportDownloaderInterface
{
    /**
     * @throws PayPalReportDownloadException
     */
    public function downloadFor(\DateTimeInterface $day, PaymentMethodInterface $paymentMethod): Report;
}
