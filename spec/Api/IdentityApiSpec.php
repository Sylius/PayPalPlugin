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
use Sylius\PayPalPlugin\Api\IdentityApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class IdentityApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $payPalClient): void
    {
        $this->beConstructedWith($payPalClient);
    }

    function it_implements_identity_api_interface(): void
    {
        $this->shouldImplement(IdentityApiInterface::class);
    }

    function it_generates_identity_token(PayPalClientInterface $payPalClient): void
    {
        $payPalClient->post('v1/identity/generate-token', 'TOKEN')->willReturn(['client_token' => 'CLIENT-TOKEN']);

        $this->generateToken('TOKEN')->shouldReturn('CLIENT-TOKEN');
    }
}
