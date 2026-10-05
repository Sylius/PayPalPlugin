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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Tests\Sylius\PayPalPlugin\Behat\Context\Admin\ManagingOrdersContext;
use Tests\Sylius\PayPalPlugin\Behat\Context\Admin\ManagingPaymentMethodsContext;
use Tests\Sylius\PayPalPlugin\Behat\Context\Hook\PayPalHttpClientContext;
use Tests\Sylius\PayPalPlugin\Behat\Context\Setup\PaymentPayPalContext;
use Tests\Sylius\PayPalPlugin\Behat\Context\Ui\Shop\PayingWithPayPalContext;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElement;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalApiMocker;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalHttpClientWithExpectations;
use Tests\Sylius\PayPalPlugin\Behat\Page\Shop\Checkout\PayPalSelectPaymentPage;
use Tests\Sylius\PayPalPlugin\Behat\Page\Shop\PayWithPayPalPage;

return static function (ContainerConfigurator $container) {
    $container->extension('framework', [
        'cache' => [
            'pools' => [
                'cache.test_paypal_http_client_expectations' => ['adapter' => 'cache.adapter.filesystem'],
            ],
        ],
    ]);

    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set(PayPalHttpClientWithExpectations::class)
        ->decorate('sylius_paypal.http_client')
        ->args([
            service('cache.test_paypal_http_client_expectations'),
            service(ResponseFactoryInterface::class),
            service(StreamFactoryInterface::class),
        ]);

    $services->set(PayPalApiMocker::class)
        ->args([service(PayPalHttpClientWithExpectations::class)]);

    $services->set(PayPalHttpClientContext::class)
        ->args([service(PayPalHttpClientWithExpectations::class)]);

    $services->set(DownloadPayPalReportElement::class)
        ->private()
        ->parent('sylius.behat.element');

    $services->set(ManagingOrdersContext::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.order'),
            service('test.client'),
            service('sylius.behat.page.admin.order.show'),
        ]);

    $services->set(ManagingPaymentMethodsContext::class)
        ->args([
            service(DownloadPayPalReportElement::class),
            service('sylius.behat.notification_checker.admin'),
            service('sylius.behat.page.admin.payment_method.create'),
        ]);

    $services->set('sylius.behat.context.ui.admin.managing_payment_methods', \Sylius\Behat\Context\Ui\Admin\ManagingPaymentMethodsContext::class)
        ->args([
            service('sylius.behat.page.admin.payment_method.create'),
            service('sylius.behat.page.admin.payment_method.index'),
            service('sylius.behat.page.admin.payment_method.update'),
            service('sylius.behat.current_page_resolver'),
            ['offline' => 'Offline', 'paypal_express_checkout' => 'Paypal Express Checkout', 'sylius_paypal' => 'PayPal', 'stripe_checkout' => 'Stripe Checkout'],
        ]);

    $services->set(PaymentPayPalContext::class)
        ->args([
            service('sylius.behat.shared_storage'),
            service('sylius.repository.payment_method'),
            service('sylius.fixture.example_factory.payment_method'),
            '%sylius.gateway_factories%',
            service('translator'),
            service(PayPalSelectPaymentPage::class),
            '%env(resolve:TEST_CLIENT_ID)%',
        ]);

    $services->set(PayPalSelectPaymentPage::class)
        ->private()
        ->parent('sylius.behat.page.shop.checkout.select_payment');

    $services->set(PayWithPayPalPage::class, PayWithPayPalPage::class)
        ->private()
        ->parent('sylius.behat.symfony_page');

    $services->set(PayingWithPayPalContext::class)
        ->args([
            service('sylius.behat.shared_storage'),
            service(PayWithPayPalPage::class),
            service('test.client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.processor.payment_complete'),
            service(PayPalApiMocker::class),
        ]);
};
