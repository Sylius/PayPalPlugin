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

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\Client;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;

final class AuthorizeClientApi implements AuthorizeClientApiInterface
{
    /** @var Client */
    private $client;

    /** @var string */
    private $baseUrl;

    public function __construct(Client $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function authorize(string $clientId, string $clientSecret): string
    {
        $response = $this->client->request(
            'POST',
            $this->baseUrl . 'v1/oauth2/token',
            [
                'auth' => [$clientId, $clientSecret],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new PayPalAuthorizationException();
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);

        return $content['access_token'];
    }
}