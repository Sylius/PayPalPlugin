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

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Sylius\PayPalPlugin\Behat\Page\Shop\Checkout\PayPalSelectPaymentPageInterface;
use Webmozart\Assert\Assert;

final class PaymentPayPalContext implements Context
{
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ExampleFactoryInterface $paymentMethodExampleFactory,
        private readonly array $gatewayFactories,
        private readonly TranslatorInterface $translator,
        private readonly PayPalSelectPaymentPageInterface $selectPaymentPage,
        private readonly string $clientId,
    ) {
    }

    /**
     * @Given /^the store allows paying with "([^"]*)" with "([^"]*)" factory name at position (\d+)$/
     * @Given /^the store allows paying with "([^"]*)" with "([^"]*)" factory name$/
     */
    public function theStoreAllowsPayingWithWithFactoryNameAtPosition(string $paymentMethodName, string $gatewayFactory, ?int $position = 0): void
    {
        $this->createPaymentMethod($paymentMethodName, 'PM_' . str_replace(' ', '_', strtoupper($paymentMethodName)), $gatewayFactory, 'Payment method', $position, true);
    }

    /**
     * @Given /^the store has a disabled "([^"]*)" payment method with "([^"]*)" gateway factory$/
     */
    public function theStoreHasADisabledPaymentMethodWithGatewayFactory(string $paymentMethodName, string $gatewayFactory): void
    {
        $this->createPaymentMethod($paymentMethodName, 'PM_' . str_replace(' ', '_', strtoupper($paymentMethodName)), $gatewayFactory, 'Payment method', 0, false);
    }

    /**
     * @Given /^I should have "([^"]*)" payment method selected$/
     */
    public function iShouldHavePaymentMethodSelected(string $paymentMethodName): void
    {
        Assert::true($this->selectPaymentPage->hasPaymentMethodSelected($paymentMethodName));
    }

    private function createPaymentMethod(
        string $name,
        string $code,
        string $gatewayFactory,
        string $description,
        int $position,
        bool $enabled = true,
    ): void {
        $gatewayFactory = $this->findGatewayNameByTranslation($gatewayFactory, $this->gatewayFactories);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodExampleFactory->create([
            'name' => ucfirst($name),
            'code' => $code,
            'description' => $description,
            'gatewayName' => $gatewayFactory,
            'gatewayFactory' => $gatewayFactory,
            'enabled' => $enabled,
            'channels' => ($this->sharedStorage->has('channel')) ? [$this->sharedStorage->get('channel')] : [],
        ]);

        /** we need to send real client_id to paypal so we dont get errors while loading javascripts */
        $paymentMethod->getGatewayConfig()->setConfig([
            'client_id' => $this->clientId,
            'client_secret' => 'SECRET',
            'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
            'merchant_id' => 'MERCHANT-ID',
            'reports_sftp_username' => 'USERNAME',
            'reports_sftp_password' => 'PASSWORD',
        ]);

        $paymentMethod->setPosition((int) $position);

        $this->sharedStorage->set('payment_method', $paymentMethod);
        $this->paymentMethodRepository->add($paymentMethod);
    }

    private function findGatewayNameByTranslation($translation, $gateways): ?string
    {
        foreach ($gateways as $key => $value) {
            if ($this->translator->trans($value) === $translation) {
                return $key;
            }
        }

        return null;
    }
}
