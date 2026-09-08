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
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final readonly class CaptureAction implements ActionInterface
{
    /**
     * PayPal answers CREATED for an order created without a selected payment source, and
     * PAYER_ACTION_REQUIRED once payment_source.paypal.experience_context is sent. Both mean the order
     * exists and both carry its id - treating only the former as success leaves the payment without a
     * paypal_order_id, which breaks every step that follows.
     */
    private const ORDER_CREATED_STATUSES = ['CREATED', 'PAYER_ACTION_REQUIRED'];

    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private CreateOrderApiInterface $createOrderApi,
        private UuidProviderInterface $uuidProvider,
    ) {
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

        if (in_array($content['status'] ?? null, self::ORDER_CREATED_STATUSES, true)) {
            $payment->setDetails([
                'status' => StatusAction::STATUS_CAPTURED,
                'paypal_order_id' => $content['id'],
                'reference_id' => $referenceId,
                'payment_amount' => $payment->getAmount(),
            ]);
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
