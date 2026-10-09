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

namespace Tests\Sylius\PayPalPlugin\Unit\Form\Extension;

use Sylius\Bundle\PaymentBundle\Form\Type\GatewayConfigType;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Bundle\ResourceBundle\Form\Registry\FormTypeRegistryInterface;
use Sylius\PayPalPlugin\Form\Extension\GatewayConfigTypeExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class GatewayConfigTypeExtensionTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [new PreloadedExtension(
            [new GatewayConfigType(GatewayConfig::class, [], $this->createStub(FormTypeRegistryInterface::class))],
            [GatewayConfigType::class => [new GatewayConfigTypeExtension()]],
        )];
    }

    public function test_it_keeps_a_paypal_gateway_on_payum_authorizing_first(): void
    {
        $gatewayConfig = $this->submit($this->gatewayConfig('sylius_paypal', usePayum: true, config: ['client_id' => 'CLIENT_ID']));

        self::assertSame(['client_id' => 'CLIENT_ID', 'use_authorize' => true], $gatewayConfig->getConfig());
    }

    public function test_it_lets_a_paypal_gateway_on_payment_requests_capture(): void
    {
        $gatewayConfig = $this->submit($this->gatewayConfig('sylius_paypal', usePayum: false, config: ['client_id' => 'CLIENT_ID', 'use_authorize' => true]));

        self::assertSame(['client_id' => 'CLIENT_ID'], $gatewayConfig->getConfig());
    }

    public function test_it_leaves_other_gateways_alone(): void
    {
        $gatewayConfig = $this->submit($this->gatewayConfig('stripe', usePayum: false, config: ['use_authorize' => true]));

        self::assertSame(['use_authorize' => true], $gatewayConfig->getConfig());
    }

    /** @param array<string, mixed> $config */
    private function gatewayConfig(string $factoryName, bool $usePayum, array $config): GatewayConfig
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $gatewayConfig->setUsePayum($usePayum);
        $gatewayConfig->setConfig($config);

        return $gatewayConfig;
    }

    private function submit(GatewayConfig $gatewayConfig): GatewayConfig
    {
        $form = $this->factory->create(GatewayConfigType::class, $gatewayConfig);
        $form->submit([]);

        return $form->getData();
    }
}
