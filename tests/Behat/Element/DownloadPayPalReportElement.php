<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

use FriendsOfBehat\PageObjectExtension\Element\Element;

final class DownloadPayPalReportElement extends Element implements DownloadPayPalReportElementInterface
{
    public function downloadReport(string $paymentMethod): void
    {
        $row = $this->getDocument()->find('css', sprintf('tbody tr:contains("%s")', $paymentMethod));
        $row->clickLink('Report');
    }

    public function isCsvReportDownloaded(): bool
    {
        $session = $this->getSession();
        $headers = $session->getResponseHeaders();

        return
            200 === $session->getStatusCode() &&
            strpos($headers['content-type'][0], 'text/csv') !== false
        ;
    }
}
