<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PayPalApiTokenProvider implements ApiTokenProviderInterface
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $clientId;

    /** @var string */
    private $secret;

    public function __construct(HttpClientInterface $httpClient, string $clientId, string $secret)
    {
        $this->httpClient = $httpClient;
        $this->clientId = $clientId;
        $this->secret = $secret;
    }

    public function getToken(): string
    {
        $response = $this->httpClient->request('POST', 'https://api.sandbox.paypal.com/v1/oauth2/token', [
            'auth_basic' => [$this->clientId, $this->secret],
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new HttpException($response->getStatusCode(), 'PayPal API token could not be provided.');
        }

        /**
         * @psalm-var array{access_token: string} $arrayResponse
         */
        $arrayResponse = $response->toArray();

        return $arrayResponse['access_token'];
    }
}
