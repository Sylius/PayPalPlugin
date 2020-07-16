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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final class CompleteOrderAction implements ActionInterface
{
    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var CompleteOrderApiInterface */
    private $completeOrderApi;

    public function __construct(
        AuthorizeClientApiInterface $authorizeClientApi,
        CompleteOrderApiInterface $completeOrderApi
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->completeOrderApi = $completeOrderApi;
    }

    /** @param CompleteOrder $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $token = $this
            ->authorizeClientApi
            ->authorize((string) $config['client_id'], (string) $config['client_secret'])
        ;
        $content = $this->completeOrderApi->complete($token, $request->getOrderId());

        if ($content['status'] === 'COMPLETED') {
            $payment->setDetails([
                'status' => StatusAction::STATUS_COMPLETED,
                'paypal_order_id' => $content['id'],
            ]);
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof CompleteOrder &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
