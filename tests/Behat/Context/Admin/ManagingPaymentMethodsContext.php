<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ElementNotFoundException;
use Sylius\Behat\Exception\NotificationExpectationMismatchException;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Page\Admin\PaymentMethod\CreatePageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Tests\Sylius\PayPalPlugin\Behat\Element\DownloadPayPalReportElementInterface;
use Webmozart\Assert\Assert;

final class ManagingPaymentMethodsContext implements Context
{
    private DownloadPayPalReportElementInterface $downloadPayPalReportElement;

    private NotificationCheckerInterface $notificationChecker;

    private CreatePageInterface $createPage;

    public function __construct(
        DownloadPayPalReportElementInterface $downloadPayPalReportElement,
        NotificationCheckerInterface $notificationChecker,
        CreatePageInterface $createPage
    ) {
        $this->downloadPayPalReportElement = $downloadPayPalReportElement;
        $this->notificationChecker = $notificationChecker;
        $this->createPage = $createPage;
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
     * @When I try to create a new payment method with "PayPal" gateway factory
     */
    public function iTryToCreateANewPaymentMethodWithGatewayFactory(): void
    {
        $this->createPage->tryToOpen(['factory' => 'sylius.pay_pal']);
    }

    /**
     * @Then I should be notified that I cannot onboard more than one PayPal seller
     */
    public function iShouldBeNotifiedThatICannotOnboardMoreThanOnePayPalSeller(): void
    {
        $this->notificationChecker->checkNotification(
            'You cannot onboard more than one PayPal seller!',
            NotificationType::failure()
        );
    }

    /**
     * @Then I should not be notified that I cannot onboard more than one PayPal seller
     */
    public function iShouldNotBeNotifiedThatICannotOnboardMoreThanOnePayPalSeller(): void
    {
        try {
            $this->notificationChecker->checkNotification(
                'You cannot onboard more than one PayPal seller!',
                NotificationType::failure()
            );
        } catch (NotificationExpectationMismatchException|ElementNotFoundException $exception) {
            return;
        }

        throw new \DomainException('Step should fail');
    }
}
