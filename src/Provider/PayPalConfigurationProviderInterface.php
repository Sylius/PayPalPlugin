<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

interface PayPalConfigurationProviderInterface
{
    public function getClientId(): string;

    public function getPartnerAttributionId(): string;

    public function getApiBaseUrl(): string;
}
