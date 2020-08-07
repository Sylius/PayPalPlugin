<?php

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
