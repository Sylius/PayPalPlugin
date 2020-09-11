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

namespace spec\Sylius\PayPalPlugin\Api;

use PhpSpec\ObjectBehavior;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class AuthorizeClientApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $payPalClient): void
    {
        $this->beConstructedWith($payPalClient);
    }

    function it_implements_authorize_client_api_interface(): void
    {
        $this->shouldImplement(AuthorizeClientApiInterface::class);
    }

    function it_returns_auth_token_for_given_client_data(PayPalClientInterface $payPalClient): void
    {
        $payPalClient->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn(['access_token' => 'TOKEN']);

        $this->authorize('CLIENT_ID', 'CLIENT_SECRET')->shouldReturn('TOKEN');
    }
}
