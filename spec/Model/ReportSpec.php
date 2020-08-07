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

namespace spec\Sylius\PayPalPlugin\Model;

use PhpSpec\ObjectBehavior;

final class ReportSpec extends ObjectBehavior
{
    function let(): void
    {
        $this->beConstructedWith('content', 'report.csv');
    }

    function it_has_content(): void
    {
        $this->content()->shouldReturn('content');
    }

    function it_has_a_file_name(): void
    {
        $this->fileName()->shouldReturn('report.csv');
    }
}
