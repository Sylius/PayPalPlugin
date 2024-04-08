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
