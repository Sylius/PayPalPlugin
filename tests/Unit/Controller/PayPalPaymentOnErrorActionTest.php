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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Controller\PayPalPaymentOnErrorAction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class PayPalPaymentOnErrorActionTest extends TestCase
{
    private RequestStack&MockObject $requestStack;

    private FlashBagInterface&MockObject $flashBag;

    private LoggerInterface&MockObject $logger;

    private PayPalPaymentOnErrorAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $session = $this->createMock(SessionInterface::class);
        $session->method('getBag')->with('flashes')->willReturn($this->flashBag);
        $this->requestStack->method('getSession')->willReturn($session);

        $this->action = new PayPalPaymentOnErrorAction($this->requestStack, $this->logger);
    }

    #[Test]
    public function it_logs_short_content_unchanged(): void
    {
        $this->logger->expects(self::once())->method('error')->with('a short error message');

        ($this->action)(Request::create('/pay-pal-payment-error', 'POST', content: 'a short error message'));
    }

    #[Test]
    public function it_truncates_content_longer_than_the_maximum_logged_length(): void
    {
        $loggedContent = null;
        $this->logger->method('error')->willReturnCallback(function (string $message) use (&$loggedContent): void {
            $loggedContent = $message;
        });

        ($this->action)(Request::create('/pay-pal-payment-error', 'POST', content: str_repeat('a', 5000)));

        self::assertNotNull($loggedContent);
        self::assertSame(2000, mb_strlen($loggedContent));
    }

    #[Test]
    public function it_strips_newlines_so_the_content_cannot_forge_fake_log_lines(): void
    {
        $loggedContent = null;
        $this->logger->method('error')->willReturnCallback(function (string $message) use (&$loggedContent): void {
            $loggedContent = $message;
        });

        ($this->action)(Request::create('/pay-pal-payment-error', 'POST', content: "line one\nfake.ERROR: injected\r\nline three"));

        self::assertStringNotContainsString("\n", (string) $loggedContent);
        self::assertStringNotContainsString("\r", (string) $loggedContent);
    }

    #[Test]
    public function it_still_adds_the_generic_error_flash(): void
    {
        $this->flashBag->expects(self::once())->method('add')->with('error', 'sylius_paypal.something_went_wrong');

        ($this->action)(Request::create('/pay-pal-payment-error', 'POST', content: 'anything'));
    }
}
