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
use Sylius\Behat\Exception\NotificationExpectationMismatchException;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Page\Admin\PaymentMethod\CreatePageInterface;
use Sylius\Behat\Page\Admin\PaymentMethod\UpdatePageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingPaymentMethodsContext implements Context
{
    public function __construct(
        private DownloadPayPalReportElementInterface $downloadPayPalReportElement,
        private NotificationCheckerInterface $notificationChecker,
        private CreatePageInterface $createPage,
        private UpdatePageInterface $updatePage,
        private IndexPageInterface $indexPage,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * @When I download report for :paymentMethodName payment method
     */
    public function iDownloadPayPalReport(string $paymentMethodName): void
    {
        $this->downloadPayPalReportElement->downloadReport($paymentMethodName);
    }

    /**
     * @Then yesterday report's CSV file should be successfully downloaded
     */
    public function yesterdayReportCsvFileShouldBeSuccessfullyDownloaded(): void
    {
        Assert::true($this->downloadPayPalReportElement->isCsvReportDownloaded());
    }

    /**
     * @When I create a new PayPal payment method :name and try to save it as enabled
     */
    public function iCreateANewPayPalPaymentMethodAndTryToSaveItAsEnabled(string $name): void
    {
        $code = $this->normalizeCode($name);

        $this->createPage->open(['factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);
        $this->createPage->nameIt($name, 'en_US');
        $this->createPage->specifyCode($code);
        $this->createPage->create();

        $this->sharedStorage->set('payment_method_name', $name);
        $this->sharedStorage->set('payment_method_code', $code);
    }

    /**
     * @When I try to enable the PayPal payment method :name
     */
    public function iTryToEnableThePayPalPaymentMethod(string $name): void
    {
        $code = $this->normalizeCode($name);
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);
        Assert::notNull($paymentMethod, sprintf('Payment method "%s" not found', $name));

        $this->updatePage->open(['id' => $paymentMethod->getId()]);
        $this->updatePage->enable();
        $this->updatePage->saveChanges();

        $this->sharedStorage->set('payment_method_name', $name);
        $this->sharedStorage->set('payment_method_code', $code);
    }

    /**
     * @When I create a new PayPal payment method :name and save it as enabled
     */
    public function iCreateANewPayPalPaymentMethodAndSaveItAsEnabled(string $name): void
    {
        $code = $this->normalizeCode($name);

        $this->createPage->open(['factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);
        $this->createPage->nameIt($name, 'en_US');
        $this->createPage->specifyCode($code);
        $this->createPage->create();

        $this->sharedStorage->set('payment_method_name', $name);
        $this->sharedStorage->set('payment_method_code', $code);
    }

    /**
     * @Then I should be notified that I cannot onboard more than one PayPal seller
     */
    public function iShouldBeNotifiedThatICannotOnboardMoreThanOnePayPalSeller(): void
    {
        $this->notificationChecker->checkNotification(
            'You cannot onboard more than one PayPal seller!',
            NotificationType::failure(),
        );
    }

    /**
     * @Then I should see a validation error that only one PayPal method can be enabled
     */
    public function iShouldSeeAValidationErrorThatOnlyOnePayPalMethodCanBeEnabled(): void
    {
        try {
            $message = $this->updatePage->getValidationMessage('enabled');
        } catch (ElementNotFoundException) {
            $message = $this->createPage->getValidationMessage('enabled');
        }

        Assert::contains(
            $message,
            'Only one PayPal payment method can be enabled at a time',
        );
    }

    /**
     * @Then the PayPal payment method :name should not exist
     */
    public function thePayPalPaymentMethodShouldNotExist(string $name): void
    {
        $this->indexPage->open();

        Assert::false(
            $this->indexPage->isSingleResourceOnPage(['name' => $name]),
            sprintf('Payment method "%s" should not exist', $name),
        );
    }

    /**
     * @Then the PayPal payment method :name should still be disabled
     */
    public function thePayPalPaymentMethodShouldStillBeDisabled(string $name): void
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $this->normalizeCode($name)]);
        Assert::notNull($paymentMethod, sprintf('Payment method "%s" not found', $name));

        $this->updatePage->open(['id' => $paymentMethod->getId()]);

        Assert::false(
            $this->updatePage->isPaymentMethodEnabled(),
            sprintf('Payment method "%s" should be disabled', $name),
        );
    }

    /**
     * @Then the new PayPal payment method should be in the list and enabled
     */
    public function theNewPayPalPaymentMethodShouldBeInTheListAndEnabled(): void
    {
        $name = $this->sharedStorage->get('payment_method_name');
        $code = $this->sharedStorage->get('payment_method_code');

        $this->indexPage->open();

        Assert::true(
            $this->indexPage->isSingleResourceOnPage(['name' => $name]),
            sprintf('Payment method "%s" should exist in the list', $name),
        );

        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);
        Assert::notNull($paymentMethod, sprintf('Payment method "%s" not found', $name));

        $this->updatePage->open(['id' => $paymentMethod->getId()]);

        Assert::true(
            $this->updatePage->isPaymentMethodEnabled(),
            sprintf('Payment method "%s" should be enabled', $name),
        );
    }

    private function normalizeCode(string $name): string
    {
        return 'PM_' . str_replace(' ', '_', strtoupper($name));
    }
}
