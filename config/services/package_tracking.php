<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Bundle\AdminBundle\Form\Type\ShipmentShipType;
use Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApi;
use Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApiInterface;
use Sylius\PayPalPlugin\PackageTracking\ApiPlatform\ShipShipmentWithCarrierResourceMetadataCollectionFactory;
use Sylius\PayPalPlugin\PackageTracking\CommandHandler\ShipShipmentWithCarrierHandler;
use Sylius\PayPalPlugin\PackageTracking\Console\Command\SendShipmentTrackingCommand;
use Sylius\PayPalPlugin\PackageTracking\Dispatcher\ShipmentTrackingDispatcher;
use Sylius\PayPalPlugin\PackageTracking\Dispatcher\ShipmentTrackingDispatcherInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Factory\ShipmentTrackingFactory;
use Sylius\PayPalPlugin\PackageTracking\Factory\ShipmentTrackingFactoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Form\Extension\ShipmentShipTypeExtension;
use Sylius\PayPalPlugin\PackageTracking\Form\Type\ShipmentTrackingType;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManager;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Message\Handler\SendShipmentTrackingHandler;
use Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessor;
use Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessorInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProvider;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProvider;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\ShipmentTrackingItemsProvider;
use Sylius\PayPalPlugin\PackageTracking\Provider\ShipmentTrackingItemsProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepository;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Twig\Component\ShipmentShipFormComponent;
use Sylius\PayPalPlugin\PackageTracking\Twig\ShipmentTrackingExtension;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipmentTrackingCarrierValidator;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipShipmentCarrierValidator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.api.add_tracking', AddTrackingApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->alias(AddTrackingApiInterface::class, 'sylius_paypal.api.add_tracking');

    $services->set('sylius_paypal.repository.shipment_tracking', ShipmentTrackingRepository::class)
        ->args([ShipmentTracking::class])
        ->factory([service('doctrine.orm.entity_manager'), 'getRepository']);

    $services->alias(ShipmentTrackingRepositoryInterface::class, 'sylius_paypal.repository.shipment_tracking');

    $services->set('sylius_paypal.provider.carrier', CarrierProvider::class)
        ->args(['%sylius_paypal.tracking.carriers%']);

    $services->alias(CarrierProviderInterface::class, 'sylius_paypal.provider.carrier');

    $services->set('sylius_paypal.factory.shipment_tracking', ShipmentTrackingFactory::class);

    $services->alias(ShipmentTrackingFactoryInterface::class, 'sylius_paypal.factory.shipment_tracking');

    $services->set('sylius_paypal.provider.shipment_tracking_items', ShipmentTrackingItemsProvider::class);

    $services->alias(ShipmentTrackingItemsProviderInterface::class, 'sylius_paypal.provider.shipment_tracking_items');

    $services->set('sylius_paypal.provider.order_paypal_payment', OrderPayPalPaymentProvider::class);

    $services->alias(OrderPayPalPaymentProviderInterface::class, 'sylius_paypal.provider.order_paypal_payment');

    $services->set('sylius_paypal.processor.shipment_tracking', ShipmentTrackingProcessor::class)
        ->args([
            service('sylius_paypal.repository.shipment_tracking'),
            service('sylius_paypal.provider.order_paypal_payment'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.api.add_tracking'),
            service('sylius_paypal.provider.shipment_tracking_items'),
            service('sylius_paypal.provider.carrier'),
            service('doctrine.orm.entity_manager'),
            service('monolog.logger.paypal'),
        ]);

    $services->alias(ShipmentTrackingProcessorInterface::class, 'sylius_paypal.processor.shipment_tracking');

    $services->set('sylius_paypal.manager.shipment_tracking', ShipmentTrackingManager::class)
        ->args([
            service('sylius_paypal.repository.shipment_tracking'),
            service('sylius_paypal.factory.shipment_tracking'),
            service('sylius_paypal.provider.carrier'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->alias(ShipmentTrackingManagerInterface::class, 'sylius_paypal.manager.shipment_tracking');

    $services->set('sylius_paypal.dispatcher.shipment_tracking', ShipmentTrackingDispatcher::class)
        ->public()
        ->args([
            service('sylius_paypal.package_tracking_bus'),
            service('monolog.logger.paypal'),
        ]);

    $services->alias(ShipmentTrackingDispatcherInterface::class, 'sylius_paypal.dispatcher.shipment_tracking');

    $services->set('sylius_paypal.message_handler.send_shipment_tracking', SendShipmentTrackingHandler::class)
        ->args([
            service('sylius.repository.shipment'),
            service('sylius_paypal.processor.shipment_tracking'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius_paypal.package_tracking_bus']);

    $services->set('sylius_paypal.console.command.send_shipment_tracking', SendShipmentTrackingCommand::class)
        ->args([
            service('sylius_paypal.repository.shipment_tracking'),
            service('sylius_paypal.processor.shipment_tracking'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.validator.shipment_tracking_carrier', ShipmentTrackingCarrierValidator::class)
        ->args([service('sylius_paypal.provider.carrier')])
        ->tag('validator.constraint_validator');

    $services->set('sylius_paypal.validator.ship_shipment_carrier', ShipShipmentCarrierValidator::class)
        ->args([
            service('sylius.repository.shipment'),
            service('sylius_paypal.provider.order_paypal_payment'),
        ])
        ->tag('validator.constraint_validator');

    $services->set('sylius_paypal.api_platform.metadata.resource.metadata_collection_factory.ship_shipment_with_carrier', ShipShipmentWithCarrierResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args([service('.inner')]);

    $services->set('sylius_paypal.command_handler.ship_shipment_with_carrier', ShipShipmentWithCarrierHandler::class)
        ->decorate('sylius_api.command_handler.checkout.ship_shipment')
        ->args([
            service('.inner'),
            service('sylius.repository.shipment'),
            service('sylius_paypal.provider.order_paypal_payment'),
            service('sylius_paypal.manager.shipment_tracking'),
        ]);

    $services->set('sylius_paypal.form.type.shipment_tracking', ShipmentTrackingType::class)
        ->args([
            service('sylius_paypal.provider.carrier'),
            service('translator'),
        ])
        ->tag('form.type');

    $services->set('sylius_paypal.form.extension.shipment_ship', ShipmentShipTypeExtension::class)
        ->args([
            service('sylius_paypal.repository.shipment_tracking'),
            service('sylius_paypal.provider.order_paypal_payment'),
        ])
        ->tag('form.type_extension');

    $services->set('sylius_paypal.twig.component.shipment_ship_form', ShipmentShipFormComponent::class)
        ->args([
            service('sylius.repository.shipment'),
            service('form.factory'),
            '%sylius.model.shipment.class%',
            ShipmentShipType::class,
            service('sylius.resource_registry'),
            service('sylius.resource_controller.request_configuration_factory'),
            service('sylius.resource_controller.authorization_checker'),
            service('sylius.resource_controller.event_dispatcher'),
            service('sylius.resource_controller.resource_update_handler'),
            service('sylius.resource_controller.flash_helper'),
            service('doctrine.orm.entity_manager'),
            service('sylius_paypal.manager.shipment_tracking'),
            service('router'),
        ])
        ->tag('sylius.live_component.admin', ['key' => 'sylius_paypal_admin:shipment:ship_form']);

    $services->set('sylius_paypal.twig.extension.shipment_tracking', ShipmentTrackingExtension::class)
        ->args([
            service('sylius_paypal.repository.shipment_tracking'),
            service('sylius_paypal.provider.order_paypal_payment'),
        ])
        ->tag('twig.extension');
};
