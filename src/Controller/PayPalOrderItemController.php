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
    public function createFromProductDetailsAction(Request $request): Response
    {
        $cart = $this->getCurrentCart();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, CartActions::ADD);
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->newResourceFactory->create($configuration, $this->factory);

        $this->getQuantityModifier()->modify($orderItem, 1);

        $form = $this->getFormFactory()->create(
            $configuration->getFormType(),
            $this->createAddToCartCommand($cart, $orderItem),
            $configuration->getFormOptions()
        );

        $form = $form->handleRequest($request);

        if (!$form->isValid()) {
            return new RedirectResponse($request->headers->get('referer'));
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
