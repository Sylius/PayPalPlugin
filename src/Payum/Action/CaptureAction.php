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

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final readonly class CaptureAction implements ActionInterface
{
    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private CreateOrderApiInterface $createOrderApi,
        private UuidProviderInterface $uuidProvider,
        private ?PayPalOrderCreatedStatusesProviderInterface $orderCreatedStatusesProvider = null,
    ) {
        if (null === $this->orderCreatedStatusesProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $orderCreatedStatusesProvider to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    /** @param Capture $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $referenceId = $this->uuidProvider->provide();
        $content = $this->createOrderApi->create($token, $payment, $referenceId);

        if (in_array($content['status'] ?? null, $this->getOrderCreatedStatuses(), true)) {
            $payment->setDetails([
                'status' => StatusAction::STATUS_CAPTURED,
                'paypal_order_id' => $content['id'],
                'reference_id' => $referenceId,
                'payment_amount' => $payment->getAmount(),
            ]);
        }
    }

    /** @return array<int, string> */
    private function getOrderCreatedStatuses(): array
    {
        $provider = $this->orderCreatedStatusesProvider ?? new PayPalOrderCreatedStatusesProvider();

        return $provider->provide();
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
