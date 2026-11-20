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

namespace Tests\Sylius\PayPalPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WebhookBaseUrlParameterTest extends KernelTestCase
{
    private const ENV = 'SYLIUS_PAYPAL_WEBHOOK_BASE_URL';

    protected function tearDown(): void
    {
        unset($_SERVER[self::ENV], $_ENV[self::ENV]);

        parent::tearDown();
    }

    public function test_it_reads_the_webhook_base_url_from_the_environment(): void
    {
        $_SERVER[self::ENV] = $_ENV[self::ENV] = 'https://shop.example.com';

        self::bootKernel();

        self::assertSame('https://shop.example.com', self::getContainer()->getParameter('sylius_paypal.webhook_base_url'));
    }

    public function test_it_leaves_the_webhook_base_url_empty_without_the_environment_variable(): void
    {
        self::bootKernel();

        self::assertSame('', self::getContainer()->getParameter('sylius_paypal.webhook_base_url'));
    }
}
