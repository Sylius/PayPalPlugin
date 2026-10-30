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

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Hook;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalHttpClientWithExpectations;
use Tests\Sylius\PayPalPlugin\Service\DummyAuthorizeClientApi;

final readonly class PayPalHttpClientContext implements Context
{
    public function __construct(private PayPalHttpClientWithExpectations $client)
    {
    }

    #[Given('every PayPal request is authorized by the PayPal account :clientId')]
    public function everyPayPalRequestIsAuthorizedByThePayPalAccount(string $clientId): void
    {
        $this->client->expectAccessToken(DummyAuthorizeClientApi::accessTokenOf($clientId));
    }

    #[BeforeScenario]
    public function resetExpectations(): void
    {
        $this->client->resetExpectations();
    }

    #[AfterScenario]
    public function assertNoExpectationsLeft(): void
    {
        if ($this->client->hasExpectations()) {
            throw new \RuntimeException(sprintf(
                'The PayPal HTTP client still expects: %s',
                json_encode($this->client->getExpectations(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
            ));
        }
    }
}
