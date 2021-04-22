<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Controller\OrderItemController;
use Sylius\Component\Order\CartActions;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PayPalOrderItemController extends OrderItemController
{
    /**
     * Most of the method's body is copied from the OrderItemController::addAction
     * The idea is to use the same process as adding to cart and then redirect to PayPal payment from cart page
     */
    public function createFromProductDetailsAction(Request $request): Response
    {
        $cart = $this->getCurrentCart();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, CartActions::ADD);
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->newResourceFactory->create($configuration, $this->factory);

        $this->getQuantityModifier()->modify($orderItem, 1);
        /** @var string $formType */
        $formType = $configuration->getFormType();

        $form = $this->getFormFactory()->create(
            $formType,
            $this->createAddToCartCommand($cart, $orderItem),
            $configuration->getFormOptions()
        );

        $form = $form->handleRequest($request);

        if (!$form->isValid()) {
            return new RedirectResponse((string) $request->headers->get('referer'));
        }

        /** @var AddToCartCommandInterface $addToCartCommand */
        $addToCartCommand = $form->getData();

        $this->getOrderModifier()->addToOrder($addToCartCommand->getCart(), $addToCartCommand->getCartItem());

        $cartManager = $this->getCartManager();
        $cartManager->persist($cart);
        $cartManager->flush();

        return $this->redirectToRoute('sylius_paypal_plugin_create_paypal_order_from_cart', ['id' => $cart->getId()]);
    }
}
