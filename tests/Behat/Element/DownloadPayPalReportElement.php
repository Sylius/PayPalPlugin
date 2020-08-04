<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

use FriendsOfBehat\PageObjectExtension\Element\Element;

final class DownloadPayPalReportElement extends Element implements DownloadPayPalReportElementInterface
{
    public function downloadReport(): void
    {
        $this->getDocument()->clickLink('Download PayPal report');
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
