<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadPayoutsReportAction
{
    /** @var PayoutsReportDownloaderInterface */
    private $payoutsReportDownloader;

    public function __construct(PayoutsReportDownloaderInterface $payoutsReportDownloader)
    {
        $this->payoutsReportDownloader = $payoutsReportDownloader;
    }

    public function __invoke(Request $request): Response
    {
        $reportContent = $this->payoutsReportDownloader->downloadFor(new \DateTime('-1 day'));

        $response = new Response($reportContent, Response::HTTP_OK, ['Content-Type' => 'text/csv']);
        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition('attachment', 'report.csv'),
        ]);

        return $response;
    }
}
