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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Controller\AddToCartAction;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\Resource\Metadata\MetadataInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;

final class AddToCartActionTest extends TestCase
{
    private OrderInterface $cart;

    private PaymentMethodInterface $payPalPaymentMethod;

    private EntityManagerInterface&MockObject $cartManager;

    private CartStorageInterface&MockObject $cartStorage;

    private PayPalVaultRequestProviderInterface&MockObject $vaultRequestProvider;

    protected function setUp(): void
    {
        $this->cart = $this->createStub(OrderInterface::class);
        $this->cart->method('getChannel')->willReturn($this->createStub(ChannelInterface::class));

        $this->payPalPaymentMethod = $this->createStub(PaymentMethodInterface::class);
        $this->payPalPaymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, [
            'getConfig' => ['venmo_enabled' => true, 'pay_later_enabled' => true],
        ]));

        $this->cartManager = $this->createMock(EntityManagerInterface::class);
        $this->cartStorage = $this->createMock(CartStorageInterface::class);
        $this->vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $this->vaultRequestProvider->method('isVaultingRequirementMet')->willReturn(true);
    }

    #[DataProvider('provideWalletOnlyButtons')]
    public function test_it_refuses_a_button_other_than_the_paypal_wallet_for_a_cart_that_needs_vaulting_without_saving_the_cart(string $paymentSource): void
    {
        $this->vaultRequestProvider
            ->expects(self::once())
            ->method('provide')
            ->with($this->cart, $this->payPalPaymentMethod)
            ->willReturn(new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID))
        ;
        $this->expectNothingSaved();

        $session = new Session(new MockArraySessionStorage());

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->action()($this->request($paymentSource, $session))->getStatusCode());
        self::assertSame(['sylius_paypal.paypal_wallet_required'], $session->getFlashBag()->get('error'));
    }

    /** @return iterable<string, array{string}> */
    public static function provideWalletOnlyButtons(): iterable
    {
        yield 'venmo' => ['venmo'];
        yield 'pay later' => ['paylater'];
    }

    #[DataProvider('provideWalletOnlyButtons')]
    public function test_it_adds_the_product_to_a_cart_that_does_not_need_vaulting(string $paymentSource): void
    {
        $this->vaultRequestProvider->method('provide')->willReturn(null);
        $this->cartManager->expects(self::once())->method('flush');

        $response = $this->action()($this->request($paymentSource, new Session(new MockArraySessionStorage())));

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function test_it_adds_the_product_to_a_cart_that_needs_vaulting_for_the_paypal_wallet(): void
    {
        $this->vaultRequestProvider->expects(self::never())->method('provide');
        $this->cartManager->expects(self::once())->method('flush');

        $response = $this->action()($this->request('paypal', new Session(new MockArraySessionStorage())));

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[DataProvider('provideAllButtons')]
    public function test_it_refuses_every_button_for_a_cart_that_needs_vaulting_the_merchant_cannot_do_without_saving_the_cart(string $paymentSource): void
    {
        $this->vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $this->vaultRequestProvider
            ->expects(self::once())
            ->method('isVaultingRequirementMet')
            ->with($this->cart, $this->payPalPaymentMethod)
            ->willReturn(false)
        ;
        $this->expectNothingSaved();

        $session = new Session(new MockArraySessionStorage());

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->action()($this->request($paymentSource, $session))->getStatusCode());
        self::assertSame(['sylius_paypal.vaulting_not_available'], $session->getFlashBag()->get('error'));
    }

    /** @return iterable<string, array{string}> */
    public static function provideAllButtons(): iterable
    {
        yield 'paypal' => ['paypal'];
        yield 'venmo' => ['venmo'];
        yield 'pay later' => ['paylater'];
    }

    public function test_it_refuses_pay_later_when_it_is_not_enabled(): void
    {
        $this->payPalPaymentMethod = $this->createStub(PaymentMethodInterface::class);
        $this->payPalPaymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, [
            'getConfig' => ['pay_later_enabled' => false],
        ]));
        $this->expectNothingSaved();

        $session = new Session(new MockArraySessionStorage());

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->action()($this->request('paylater', $session))->getStatusCode());
        self::assertSame(['sylius_paypal.payment_source_not_available'], $session->getFlashBag()->get('error'));
    }

    private function expectNothingSaved(): void
    {
        $this->cartManager->expects(self::never())->method('persist');
        $this->cartManager->expects(self::never())->method('flush');
        $this->cartStorage->expects(self::never())->method('setForChannel');
    }

    private function action(): AddToCartAction
    {
        $cartItem = $this->createStub(OrderItemInterface::class);

        $addToCartCommand = $this->createStub(AddToCartCommandInterface::class);
        $addToCartCommand->method('getCart')->willReturn($this->cart);
        $addToCartCommand->method('getCartItem')->willReturn($cartItem);

        $form = $this->createStub(FormInterface::class);
        $form->method('getName')->willReturn('sylius_shop_add_to_cart');
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($addToCartCommand);

        $requestConfiguration = $this->createStub(RequestConfiguration::class);
        $requestConfiguration->method('getFormType')->willReturn('add_to_cart');
        $requestConfiguration->method('getFormOptions')->willReturn([]);

        $payPalPaymentMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalPaymentMethodsResolver->method('getInChannel')->willReturn([$this->payPalPaymentMethod]);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/create-pay-pal-order-from-cart/1');

        return new AddToCartAction(
            $this->createStub(AddToCartCommandFactoryInterface::class),
            $this->createConfiguredStub(CartContextInterface::class, ['getCart' => $this->cart]),
            $this->cartManager,
            $this->createStub(FactoryInterface::class),
            $this->createConfiguredStub(FormFactoryInterface::class, ['create' => $form]),
            $this->createStub(MetadataInterface::class),
            $this->createConfiguredStub(NewResourceFactoryInterface::class, ['create' => $cartItem]),
            $this->createStub(OrderItemQuantityModifierInterface::class),
            $this->createStub(OrderModifierInterface::class),
            $this->createConfiguredStub(RequestConfigurationFactoryInterface::class, ['create' => $requestConfiguration]),
            $router,
            $this->cartStorage,
            $payPalPaymentMethodsResolver,
            $this->vaultRequestProvider,
        );
    }

    private function request(string $paymentSource, Session $session): Request
    {
        $request = Request::create('/paypal-add-to-cart/1?paymentSource=' . $paymentSource, 'POST');
        $request->setSession($session);

        return $request;
    }
}
