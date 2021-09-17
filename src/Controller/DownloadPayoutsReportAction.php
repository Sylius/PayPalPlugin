<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Downloader\PayoutsReportDownloaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class DownloadPayoutsReportAction
{
    private PayoutsReportDownloaderInterface $payoutsReportDownloader;

    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    public function __construct(
        PayoutsReportDownloaderInterface $payoutsReportDownloader,
        PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
        $this->payoutsReportDownloader = $payoutsReportDownloader;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function __invoke(Request $request): Response
    {
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->find($request->attributes->getInt('id'));
        Assert::notNull($paymentMethod);

        $report = $this->payoutsReportDownloader->downloadFor(new \DateTime('-1 day'), $paymentMethod);

        $response = new Response($report->content(), Response::HTTP_OK, ['Content-Type' => 'text/csv']);
        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition('attachment', $report->fileName()),
        ]);

        return $response;
    }
}
