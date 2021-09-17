<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
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

final class CaptureAction implements ActionInterface
{
    private CacheAuthorizeClientApiInterface $authorizeClientApi;

    private CreateOrderApiInterface $createOrderApi;

    private UuidProviderInterface $uuidProvider;

    public function __construct(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        CreateOrderApiInterface $createOrderApi,
        UuidProviderInterface $uuidProvider
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->createOrderApi = $createOrderApi;
        $this->uuidProvider = $uuidProvider;
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

        if ($content['status'] === 'CREATED') {
            $payment->setDetails([
                'status' => StatusAction::STATUS_CAPTURED,
                'paypal_order_id' => $content['id'],
                'reference_id' => $referenceId,
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
