<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\PayPalPlugin\Downloader\ReportDownloaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadPayoutsReportAction
{
    /** @var ReportDownloaderInterface */
    protected $reportDownloader;

    public function __construct(ReportDownloaderInterface $reportDownloader)
    {
        $this->reportDownloader = $reportDownloader;
    }

    public function __invoke(Request $request): Response
    {
        $reportContent = $this->reportDownloader->downloadLatest();

        $response = new Response($reportContent, Response::HTTP_OK, ['Content-Type' => 'text/csv']);
        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition('attachment', 'report.csv'),
        ]);

        return $response;
    }
}
