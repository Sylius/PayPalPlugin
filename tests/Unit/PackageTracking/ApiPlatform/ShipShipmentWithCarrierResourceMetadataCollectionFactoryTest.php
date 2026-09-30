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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Command\Checkout\ShipShipment;
use Sylius\PayPalPlugin\PackageTracking\ApiPlatform\ShipShipmentWithCarrierResourceMetadataCollectionFactory;
use Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier;

final class ShipShipmentWithCarrierResourceMetadataCollectionFactoryTest extends TestCase
{
    #[Test]
    public function it_takes_the_carrier_as_input_of_the_ship_operation_only(): void
    {
        $ship = (new Patch(name: ShipShipmentWithCarrierResourceMetadataCollectionFactory::OPERATION_NAME))
            ->withInput(['class' => ShipShipment::class, 'name' => 'ShipShipment'])
            ->withOpenapi(new OpenApiOperation(summary: 'Ships a shipment'))
        ;
        $get = new Get(name: 'sylius_api_admin_shipment_get');

        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new ResourceMetadataCollection('Shipment', [
            (new ApiResource())->withOperations(new Operations([
                'sylius_api_admin_shipment_get' => $get,
                ShipShipmentWithCarrierResourceMetadataCollectionFactory::OPERATION_NAME => $ship,
            ])),
        ]));

        $operations = (new ShipShipmentWithCarrierResourceMetadataCollectionFactory($decorated))->create('Shipment')[0]->getOperations();
        self::assertNotNull($operations);

        $mutated = iterator_to_array($operations);
        self::assertSame($get, $mutated['sylius_api_admin_shipment_get']);

        $mutatedShip = $mutated[ShipShipmentWithCarrierResourceMetadataCollectionFactory::OPERATION_NAME];
        self::assertInstanceOf(Patch::class, $mutatedShip);
        self::assertSame(['class' => ShipShipmentWithCarrier::class, 'name' => 'ShipShipmentWithCarrier'], $mutatedShip->getInput());
        $openapi = $mutatedShip->getOpenapi();
        self::assertInstanceOf(OpenApiOperation::class, $openapi);
        self::assertSame('Ships a shipment', $openapi->getSummary());
        self::assertStringContainsString('carrierNameOther', (string) $openapi->getDescription());
    }
}
