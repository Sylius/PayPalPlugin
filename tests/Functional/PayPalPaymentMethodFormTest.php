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

use ApiTestCase\JsonApiTestCase;
use Sylius\Bundle\AdminBundle\Form\Type\PaymentMethodType;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormInterface;

final class PayPalPaymentMethodFormTest extends JsonApiTestCase
{
    public function test_it_lets_the_admin_switch_off_an_enabled_paypal_payment_method(): void
    {
        $enabled = $this->form(enabled: true)->get('enabled');

        self::assertInstanceOf(CheckboxType::class, $enabled->getConfig()->getType()->getInnerType());
        self::assertFalse($enabled->isDisabled());
        self::assertTrue($enabled->getData());
    }

    public function test_it_leaves_enabling_a_disabled_paypal_payment_method_to_the_enable_action(): void
    {
        $enabled = $this->form(enabled: false)->get('enabled');

        self::assertInstanceOf(CheckboxType::class, $enabled->getConfig()->getType()->getInnerType());
        self::assertTrue($enabled->isDisabled());
        self::assertFalse($enabled->getData());
    }

    private function form(bool $enabled): FormInterface
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = self::getContainer()->get('sylius.repository.payment_method')->findOneBy(['code' => 'PAYPAL']);
        $paymentMethod->setEnabled($enabled);

        return self::getContainer()->get('form.factory')->create(PaymentMethodType::class, $paymentMethod);
    }
}
