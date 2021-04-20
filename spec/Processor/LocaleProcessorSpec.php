<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Processor;

use PhpSpec\ObjectBehavior;

final class LocaleProcessorSpec extends ObjectBehavior
{
    function it_always_processes_locale_to_version_with_region(): void
    {
        $this->process('et')->shouldReturn('et_EE');
        $this->process('pl')->shouldReturn('pl_PL');
        $this->process('ja')->shouldReturn('ja_JP');
    }

    function it_returns_same_locale_if_it_is_valid(): void
    {
        $this->process('it_IT')->shouldReturn('it_IT');
        $this->process('ja_JP_TRADITIONAL')->shouldReturn('ja_JP_TRADITIONAL');
        $this->process('sd_Arab_PK')->shouldReturn('sd_Arab_PK');
    }

    function it_returns_correct_locale_for_en_locale(): void
    {
        $this->process('en')->shouldReturn('en_US');
    }
}
