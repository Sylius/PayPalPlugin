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

namespace Tests\Sylius\PayPalPlugin\Unit;

use Payum\Core\Bridge\Spl\ArrayObject;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Payum\Factory\PayPalGatewayFactory;
use Sylius\PayPalPlugin\Payum\Model\PayPalApi;

final class PayPalGatewayFactoryTest extends TestCase
{
    /** @test */
    public function it_populates_pay_pal_configuration(): void
    {
        $factory = new PayPalGatewayFactory();

        $config = $factory->createConfig(['api_key' => 'KEY']);

        $this->assertEquals('Pay Pal', $config['payum.factory_title']);
        $this->assertEquals('pay_pal', $config['payum.factory_name']);
        $this->assertEquals(new StatusAction(), $config['payum.action.status']);

        $this->assertTrue($config['payum.api'] instanceof \Closure);

        $api = $config['payum.api'](new ArrayObject($config));
        $this->assertTrue($api instanceof PayPalApi);
        $this->assertSame($api->token(), 'KEY');
    }
}
