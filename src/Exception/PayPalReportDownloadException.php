<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalReportDownloadException extends \Exception
{
    public function __construct()
    {
        parent::__construct('PayPal report could not be downloaded');
    }
}
