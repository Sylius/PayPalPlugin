<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PaymentPayPalContext implements Context
{
    /** @var SharedStorageInterface */
    private $sharedStorage;

    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    /** @var ExampleFactoryInterface */
    private $paymentMethodExampleFactory;

    /** @var FactoryInterface */
    private $paymentMethodTranslationFactory;

    /** @var ObjectManager */
    private $paymentMethodManager;

    /** @var array */
    private $gatewayFactories;

    /** @var TranslatorInterface */
    private $translator;

    public function __construct(
        SharedStorageInterface $sharedStorage,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ExampleFactoryInterface $paymentMethodExampleFactory,
        FactoryInterface $paymentMethodTranslationFactory,
        ObjectManager $paymentMethodManager,
        array $gatewayFactories,
        TranslatorInterface $translator
    ) {
        $this->sharedStorage = $sharedStorage;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentMethodExampleFactory = $paymentMethodExampleFactory;
        $this->paymentMethodTranslationFactory = $paymentMethodTranslationFactory;
        $this->paymentMethodManager = $paymentMethodManager;
        $this->gatewayFactories = $gatewayFactories;
        $this->translator = $translator;
    }

    /**
     * @Given /^the store allows paying with "([^"]*)" with "([^"]*)" factory name at position (\d+)$/
     */
    public function theStoreAllowsPayingWithWithFactoryNameAtPosition(string $paymentMethodName, string $gatewayFactory, int $position)
    {
        $this->createPaymentMethod($paymentMethodName, 'PM_' . $paymentMethodName, $gatewayFactory, 'Payment method', $position);
    }

    private function createPaymentMethod(
        string $name,
        string $code,
        string $gatewayFactory,
        string $description,
        int $position
    ): void {
        $gatewayFactory = $this->findGatewayNameByTranslation($gatewayFactory, $this->gatewayFactories);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodExampleFactory->create([
            'name' => ucfirst($name),
            'code' => $code,
            'description' => $description,
            'gatewayName' => $gatewayFactory,
            'gatewayFactory' => $gatewayFactory,
            'enabled' => true,
            'channels' => ($this->sharedStorage->has('channel')) ? [$this->sharedStorage->get('channel')] : [],
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
