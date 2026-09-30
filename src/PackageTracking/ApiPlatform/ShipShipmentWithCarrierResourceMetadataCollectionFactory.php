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

namespace Sylius\PayPalPlugin\PackageTracking\ApiPlatform;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier;

final readonly class ShipShipmentWithCarrierResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public const OPERATION_NAME = 'sylius_api_admin_shipment_patch_ship';

    private const DESCRIPTION = 'Ships the shipment. For an order paid with PayPal, `carrier` (one of the codes configured in `sylius_paypal.tracking.carriers`, or `OTHER` with `carrierNameOther`) is required together with `trackingCode`, and the tracking is sent to PayPal. For other orders `carrier` and `carrierNameOther` are ignored.';

    public function __construct(private ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        foreach ($resourceMetadataCollection as $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();
            if (null === $operations) {
                continue;
            }

            /** @var Operation $operation */
            foreach ($operations as $name => $operation) {
                if (self::OPERATION_NAME !== $name) {
                    continue;
                }

                $operations->remove($name);
                $operations->add($name, $this->withCarrierInput($operation));
            }
        }

        return $resourceMetadataCollection;
    }

    private function withCarrierInput(Operation $operation): Operation
    {
        $operation = $operation->withInput(['class' => ShipShipmentWithCarrier::class, 'name' => 'ShipShipmentWithCarrier']);

        if (!$operation instanceof HttpOperation) {
            return $operation;
        }

        $openapi = $operation->getOpenapi();

        return $operation->withOpenapi(
            ($openapi instanceof OpenApiOperation ? $openapi : new OpenApiOperation())->withDescription(self::DESCRIPTION),
        );
    }
}
