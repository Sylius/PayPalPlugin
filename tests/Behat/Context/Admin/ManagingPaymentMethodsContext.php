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

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\PaymentMethod\CreatePageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Tests\Sylius\PayPalPlugin\Behat\Element\ConnectPayPalAccountElementInterface;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingPaymentMethodsContext implements Context
{
    public function __construct(
        private DownloadPayPalReportElementInterface $downloadPayPalReportElement,
        private NotificationCheckerInterface $notificationChecker,
        private CreatePageInterface $createPage,
        private ConnectPayPalAccountElementInterface $connectPayPalAccountElement,
    ) {
    }

    #[When('I download report for :paymentMethodName payment method')]
    public function iDownloadPayPalReport(string $paymentMethodName): void
    {
        $this->downloadPayPalReportElement->downloadReport($paymentMethodName);
    }

    #[Then('yesterday report\'s CSV file should be successfully downloaded')]
    public function yesterdayReportCsvFileShouldBeSuccessfullyDownloaded(): void
    {
        Assert::true($this->downloadPayPalReportElement->isCsvReportDownloaded());
    }

    #[When('I try to create a new payment method with "PayPal" gateway factory')]
    public function iTryToCreateANewPaymentMethodWithGatewayFactory(): void
    {
        $this->createPage->tryToOpen(['factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);
    }

    #[Then('I should be told to create the PayPal payment method through the onboarding')]
    public function iShouldBeToldToCreateThePayPalPaymentMethodThroughTheOnboarding(): void
    {
        $this->notificationChecker->checkNotification(
            'Please, use the PayPal onboarding available on the payment methods list to create the PayPal payment method.',
            NotificationType::info(),
        );
    }

    #[Then('I should be able to connect another PayPal account')]
    public function iShouldBeAbleToConnectAnotherPayPalAccount(): void
    {
        Assert::true($this->connectPayPalAccountElement->canConnectAnotherPayPalAccount());
    }
}
