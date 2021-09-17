<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Command;

use Doctrine\Persistence\ObjectManager;
use Payum\Core\Model\GatewayConfigInterface;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CompletePaidPaymentsCommand extends Command
{
    private PaymentRepositoryInterface $paymentRepository;

    private ObjectManager $paymentManager;

    private CacheAuthorizeClientApiInterface $authorizeClientApi;

    private OrderDetailsApiInterface $orderDetailsApi;

    private FactoryInterface $stateMachineFactory;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        ObjectManager $paymentManager,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        FactoryInterface $stateMachineFactory
    ) {
        parent::__construct();

        $this->paymentRepository = $paymentRepository;
        $this->paymentManager = $paymentManager;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    protected function configure(): void
    {
        $this
            ->setName('sylius:pay-pal-plugin:complete-payments')
            ->setDescription('Completes payments for completed PayPal orders')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payments = $this->paymentRepository->findBy(['state' => PaymentInterface::STATE_PROCESSING]);
        /** @var PaymentInterface $payment */
        foreach ($payments as $payment) {
            /** @var PaymentMethodInterface $paymentMethod */
            $paymentMethod = $payment->getMethod();
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
                continue;
            }

            /** @var string $payPalOrderId */
            $payPalOrderId = $payment->getDetails()['paypal_order_id'];

            $token = $this->authorizeClientApi->authorize($paymentMethod);
            $details = $this->orderDetailsApi->get($token, $payPalOrderId);

            if ($details['status'] === 'COMPLETED') {
                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);

                $paymentDetails = $payment->getDetails();
                $paymentDetails['status'] = StatusAction::STATUS_COMPLETED;

                $payment->setDetails($paymentDetails);
            }
        }

        $this->paymentManager->flush();

        return 0;
    }
}
