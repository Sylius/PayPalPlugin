<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Admin;

use Behat\Behat\Context\Context;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElementInterface;
use Webmozart\Assert\Assert;

final class ManagingPaymentsContext implements Context
{
    /** @var DownloadPayPalReportElementInterface */
    private $downloadPayPalReportElement;

    public function __construct(DownloadPayPalReportElementInterface $downloadPayPalReportElement)
    {
        $this->downloadPayPalReportElement = $downloadPayPalReportElement;
    }

    /**
     * @When I download PayPal report
     */
    public function iDownloadPayPalReport(): void
    {
        $this->downloadPayPalReportElement->downloadReport();
    }

    /**
     * @Then yesterday report csv file should be successfully downloaded
     */
    public function yesterdayReportCsvFileShouldBeSuccessfullyDownloaded(): void
    {
        Assert::true($this->downloadPayPalReportElement->isCsvReportDownloaded());
    }
}
