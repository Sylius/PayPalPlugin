<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Admin;

use Behat\Behat\Context\Context;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElementInterface;
use Webmozart\Assert\Assert;

final class ManagingPaymentMethodsContext implements Context
{
    /** @var DownloadPayPalReportElementInterface */
    private $downloadPayPalReportElement;

    public function __construct(DownloadPayPalReportElementInterface $downloadPayPalReportElement)
    {
        $this->downloadPayPalReportElement = $downloadPayPalReportElement;
    }

    /**
     * @When I download report for :paymentMethodName payment method
     */
    public function iDownloadPayPalReport(string $paymentMethodName): void
    {
        $this->downloadPayPalReportElement->downloadReport($paymentMethodName);
    }

    /**
     * @Then yesterday report's CSV file should be successfully downloaded
     */
    public function yesterdayReportCsvFileShouldBeSuccessfullyDownloaded(): void
    {
        Assert::true($this->downloadPayPalReportElement->isCsvReportDownloaded());
    }
}
