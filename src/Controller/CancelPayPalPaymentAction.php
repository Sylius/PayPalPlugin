<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\WinzouStateMachineAdapter;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

final class CancelPayPalPaymentAction
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly ObjectManager $objectManager,
        private readonly FlashBag|RequestStack $flashBagOrRequestStack,
        private readonly FactoryInterface|StateMachineInterface $stateMachineFactory,
        private readonly OrderProcessorInterface $orderPaymentProcessor,
    ) {
        if ($flashBagOrRequestStack instanceof FlashBag) {
            trigger_deprecation('sylius/paypal-plugin', '1.5', sprintf('Passing an instance of %s as constructor argument for %s is deprecated as of PayPalPlugin 1.5 and will be removed in 2.0. Pass an instance of %s instead.', FlashBag::class, self::class, RequestStack::class));
        }

        if ($this->stateMachineFactory instanceof FactoryInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the fourth argument is deprecated and will be prohibited in 2.0. Use "%s" instead.',
                    FactoryInterface::class,
                    StateMachineInterface::class,
                ),
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        /**
         * @var string $content
         */
        $content = $request->getContent();

        $content = (array) json_decode($content, true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $this->getStateMachine()->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();

        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
            ->add('success', 'sylius.pay_pal.payment_cancelled')
        ;

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function getStateMachine(): StateMachineInterface
    {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            return new WinzouStateMachineAdapter($this->stateMachineFactory);
        }

        return $this->stateMachineFactory;
    }
}
