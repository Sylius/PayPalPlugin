<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Processor;

use PhpSpec\ObjectBehavior;

final class LocaleProcessorSpec extends ObjectBehavior
{
    public function it_processes_locale_to_match_locale_regex(): void
    {
        $this->process('et')->shouldReturn('et_EE');
        $this->process('pl')->shouldReturn('pl_PL');
        $this->process('ja')->shouldReturn('ja_JP');
    }

    public function it_returns_same_locale_if_already_matches_regex(): void
    {
        $this->process('it_IT')->shouldReturn('it_IT');
    }
}
