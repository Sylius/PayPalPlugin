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
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Step\Then;
use Behat\Step\When;
use Sylius\Behat\Exception\NotificationExpectationMismatchException;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\PaymentMethod\CreatePageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElementInterface;
use Tests\Sylius\PayPalPlugin\Behat\Element\PaymentMethodListElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingPaymentMethodsContext implements Context
{
    public function __construct(
        private DownloadPayPalReportElementInterface $downloadPayPalReportElement,
        private NotificationCheckerInterface $notificationChecker,
        private CreatePageInterface $createPage,
        private PaymentMethodListElementInterface $paymentMethodListElement,
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

    #[Then('I should be notified that I cannot onboard more than one PayPal seller')]
    public function iShouldBeNotifiedThatICannotOnboardMoreThanOnePayPalSeller(): void
    {
        $this->notificationChecker->checkNotification(
            'You cannot onboard more than one PayPal seller!',
            NotificationType::failure(),
        );
    }

    #[Then('I should not be notified that I cannot onboard more than one PayPal seller')]
    public function iShouldNotBeNotifiedThatICannotOnboardMoreThanOnePayPalSeller(): void
    {
        try {
            $this->notificationChecker->checkNotification(
                'You cannot onboard more than one PayPal seller!',
                NotificationType::failure(),
            );
        } catch (NotificationExpectationMismatchException|ElementNotFoundException $exception) {
            return;
        }

        throw new \DomainException('Step should fail');
    }

    #[Then('the :paymentMethodName payment method should offer :paymentSource')]
    public function thePaymentMethodShouldOffer(string $paymentMethodName, string $paymentSource): void
    {
        Assert::true($this->paymentMethodListElement->hasPaymentSource($paymentMethodName, $this->paymentSourceCode($paymentSource)));
    }

    #[Then('the :paymentMethodName payment method should not offer :paymentSource')]
    public function thePaymentMethodShouldNotOffer(string $paymentMethodName, string $paymentSource): void
    {
        Assert::false($this->paymentMethodListElement->hasPaymentSource($paymentMethodName, $this->paymentSourceCode($paymentSource)));
    }

    #[Then('the :paymentMethodName payment method should be marked as working in sandbox mode')]
    public function thePaymentMethodShouldBeMarkedAsWorkingInSandboxMode(string $paymentMethodName): void
    {
        Assert::same($this->paymentMethodListElement->getMode($paymentMethodName), 'sandbox');
    }

    #[Then('I should not see PayPal suggested as a payment method')]
    public function iShouldNotSeePayPalSuggestedAsAPaymentMethod(): void
    {
        Assert::false($this->paymentMethodListElement->hasSuggestedPayPalPaymentMethod());
    }

    private function paymentSourceCode(string $paymentSource): string
    {
        return str_replace(' ', '_', strtolower($paymentSource));
    }
}
