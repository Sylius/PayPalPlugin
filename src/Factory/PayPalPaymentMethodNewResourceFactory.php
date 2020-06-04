<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

final class PayPalPaymentMethodNewResourceFactory implements NewResourceFactoryInterface
{
    /**
     * @var NewResourceFactoryInterface
     */
    private $newResourceFactory;

    public function __construct(NewResourceFactoryInterface $newResourceFactory)
    {
        $this->newResourceFactory = $newResourceFactory;
    }

    public function create(RequestConfiguration $requestConfiguration, FactoryInterface $factory): ResourceInterface
    {
        $resource = $this->newResourceFactory->create($requestConfiguration, $factory);

        if (!$resource instanceof PaymentMethodInterface) {
            return $resource;
        }

        if ($resource->getGatewayConfig()->getFactoryName() !== 'sylius.pay_pal') {
            return $resource;
        }

        $request = $requestConfiguration->getRequest();

        if (!$request->query->has('merchantId')) {
            return $resource;
        }

        $resource->getGatewayConfig()->setConfig([
            'merchant_id' => $request->query->get('merchantId'),
            'merchant_id_in_paypal' => $request->query->get('merchantIdInPayPal'),
        ]);

        return $resource;
    }
}
