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

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\IdentityApiInterface;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class PayWithPayPalFormAction
{
    private PayerActionCheckerInterface $payerActionChecker;

    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private Environment $twig,
        private PaymentRepositoryInterface $paymentRepository,
        private ?AvailableCountriesProviderInterface $countriesProvider = null,
        private ?CacheAuthorizeClientApiInterface $authorizeClientApi = null,
        private ?IdentityApiInterface $identityApi = null,
        private ?LocaleProcessorInterface $localeProcessor = null,
        private ?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null,
        private ?PayPalPaymentPageContextProviderInterface $contextProvider = null,
        private ?UrlGeneratorInterface $router = null,
        ?PayerActionCheckerInterface $payerActionChecker = null,
    ) {
        $this->payerActionChecker = $payerActionChecker ?? new PayerActionChecker();

        $this->deprecateUnusedArgument($this->countriesProvider, AvailableCountriesProviderInterface::class);
        $this->deprecateUnusedArgument($this->authorizeClientApi, CacheAuthorizeClientApiInterface::class);
        $this->deprecateUnusedArgument($this->identityApi, IdentityApiInterface::class);
        $this->deprecateUnusedArgument($this->localeProcessor, LocaleProcessorInterface::class);
        $this->deprecateUnusedArgument($this->payPalConfigurationProvider, PayPalConfigurationProviderInterface::class);

        $this->deprecateMissingArgument($this->contextProvider, PayPalPaymentPageContextProviderInterface::class);
        $this->deprecateMissingArgument($this->router, UrlGeneratorInterface::class);
    }

    public function __invoke(Request $request): Response
    {
        $orderToken = (string) $request->attributes->get('orderToken');
        $payment = $this->paymentRepository->findOneByOrderToken($request->attributes->get('paymentId'), $orderToken);

        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('There is no PayPal payment for order "%s".', $orderToken));
        }

        if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
            return new RedirectResponse(
                $this->requireArgument($this->router, UrlGeneratorInterface::class)
                    ->generate('sylius_shop_order_thank_you'),
            );
        }

        if ($this->payerActionChecker->isAwaitingPayerAction($payment)) {
            return new RedirectResponse(
                $this->requireArgument($this->router, UrlGeneratorInterface::class)
                    ->generate('sylius_shop_order_show', ['tokenValue' => $orderToken]),
            );
        }

        $response = new Response($this->twig->render(
            '@SyliusPayPalPlugin/pay_with_paypal.html.twig',
            $this->requireArgument($this->contextProvider, PayPalPaymentPageContextProviderInterface::class)
                ->provide($payment, $request->getLocale()),
        ));

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function deprecateUnusedArgument(?object $argument, string $interface): void
    {
        if (null === $argument) {
            return;
        }

        trigger_deprecation(
            'sylius/paypal-plugin',
            '2.1',
            'Passing an instance of "%s" to "%s" constructor is deprecated and will be prohibited in 3.0.' .
            ' It is no longer used since the page moved to PayPal Web SDK v6.',
            $interface,
            self::class,
        );
    }

    private function deprecateMissingArgument(?object $argument, string $interface): void
    {
        if (null !== $argument) {
            return;
        }

        trigger_deprecation(
            'sylius/paypal-plugin',
            '2.1',
            'Not passing an instance of "%s" to "%s" constructor is deprecated and will be required in 3.0.',
            $interface,
            self::class,
        );
    }

    /**
     * @template T of object
     *
     * @param T|null $argument
     *
     * @return T
     */
    private function requireArgument(?object $argument, string $interface): object
    {
        if (null === $argument) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to render the PayPal payment page.',
                $interface,
            ));
        }

        return $argument;
    }
}
