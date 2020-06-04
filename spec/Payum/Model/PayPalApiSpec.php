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

namespace spec\Sylius\PayPalPlugin\Payum\Model;

use PhpSpec\ObjectBehavior;

final class PayPalApiSpec extends ObjectBehavior
{
    function let(): void
    {
        $this->beConstructedWith('!@#TOKEN123');
    }

    function it_has_a_token(): void
    {
        $this->token()->shouldReturn('!@#TOKEN123');
    }
}
