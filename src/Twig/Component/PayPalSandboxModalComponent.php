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

namespace Sylius\PayPalPlugin\Twig\Component;

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Form\Type\PayPalSandboxCredentialsType;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('sylius_paypal_sandbox_modal', template: '@SyliusPayPalPlugin/admin/shared/paypal_sandbox_modal.html.twig')]
final class PayPalSandboxModalComponent
{
    private const INDEX_ROUTE = 'sylius_admin_payment_method_index';

    private const UPDATE_ROUTE = 'sylius_admin_payment_method_update';

    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp]
    public ?string $modalId = null;

    #[LiveProp]
    public ?string $type = null;

    #[LiveProp]
    public bool $opened = false;

    #[LiveProp]
    public ?PayPalSandboxCredentialsType $paypalSandboxCredentials = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly PayPalSandboxPaymentMethodCreatorInterface $sandboxPaymentMethodCreator,
        private readonly RequestStack $flashBagOrRequestStack,
        private readonly LoggerInterface $logger,
        private readonly RouterInterface $router,
    ) {
    }

    #[LiveAction]
    public function submit(): ?RedirectResponse
    {
        $this->opened = true;

        $this->submitForm();
        if (!$this->form->isSubmitted() || !$this->form->isValid()) {
            return null;
        }

        $credentials = $this->form->getData();

        $clientId = $credentials->getClientId();
        $clientSecret = $credentials->getClientSecret();
        $merchantId = $credentials->getMerchantId();

        try {
            $paymentMethod = $this->sandboxPaymentMethodCreator->create($clientId, $clientSecret, $merchantId);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Could not create the sandbox PayPal payment method: %s', $exception->getMessage()),
                ['exception' => $exception],
            );

            FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)->add('error', 'sylius_paypal.could_not_create_paypal_payment_method');

            return new RedirectResponse(
                $this->router->generate(self::INDEX_ROUTE),
            );
        }

        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)->add('success', 'sylius_paypal.sandbox_connected_successfully');

        return new RedirectResponse(
            $this->router->generate(
                self::UPDATE_ROUTE,
                ['id' => $paymentMethod->getId()],
            ),
        );
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(PayPalSandboxCredentialsType::class);
    }

    protected function getDataModelValue(): string
    {
        return 'norender|on(change)|*';
    }
}
