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

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Resource\Metadata\MetadataInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final readonly class AddToCartAction
{
    public function __construct(
        private AddToCartCommandFactoryInterface $addToCartCommandFactory,
        private CartContextInterface $cartContext,
        private EntityManagerInterface $cartManager,
        private FactoryInterface $factory,
        private FormFactoryInterface $formFactory,
        private MetadataInterface $metadata,
        private NewResourceFactoryInterface $newResourceFactory,
        private OrderItemQuantityModifierInterface $quantityModifier,
        private OrderModifierInterface $orderModifier,
        private RequestConfigurationFactoryInterface $requestConfigurationFactory,
        private RouterInterface $router,
        private ?OrderTokenAssignerInterface $orderTokenAssigner = null,
    ) {
        if (null === $this->orderTokenAssigner) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                OrderTokenAssignerInterface::class,
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface $cart */
        $cart = $this->cartContext->getCart();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->newResourceFactory->create($configuration, $this->factory);

        $this->quantityModifier->modify($orderItem, 1);
        /** @var string $formType */
        $formType = $configuration->getFormType();

        $form = $this->formFactory->create(
            $formType,
            $this->addToCartCommandFactory->createWithCartAndCartItem($cart, $orderItem),
            $configuration->getFormOptions(),
        );

        $form = $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $product = $orderItem->getVariant()->getProduct();

            return new RedirectResponse(
                $this->router->generate('sylius_shop_product_show', ['slug' => $product->getSlug()]),
            );
        }

        /** @var AddToCartCommandInterface $addToCartCommand */
        $addToCartCommand = $form->getData();

        $this->orderModifier->addToOrder($addToCartCommand->getCart(), $addToCartCommand->getCartItem());

        // A brand-new cart only gets its tokenValue assigned by AssignOrderTokenListener, which fires on
        // an order *checkout* transition - a plain add-to-order never triggers one, so a cart created by
        // this very request would otherwise still have no token to redirect with.
        $this->getOrderTokenAssigner()->assignTokenValueIfNotSet($cart);

        $this->cartManager->persist($cart);
        $this->cartManager->flush();

        // 307 (not the default 302) so the browser's fetch() preserves POST across the redirect - the
        // target route only accepts POST (see UPGRADE-2.1.md: GET was dropped, it existed only to let this
        // redirect's auto-followed request through, which made the target reachable from a plain <img> tag).
        return new RedirectResponse(
            $this->router->generate('sylius_paypal_shop_create_paypal_order_from_cart', ['tokenValue' => $cart->getTokenValue()]),
            Response::HTTP_TEMPORARY_REDIRECT,
        );
    }

    private function getOrderTokenAssigner(): OrderTokenAssignerInterface
    {
        if (null === $this->orderTokenAssigner) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to assign a token to a newly created cart.',
                OrderTokenAssignerInterface::class,
            ));
        }

        return $this->orderTokenAssigner;
    }
}
