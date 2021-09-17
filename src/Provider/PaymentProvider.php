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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;

final class PaymentProvider implements PaymentProviderInterface
{
    private PaymentRepositoryInterface $paymentRepository;

    public function __construct(PaymentRepositoryInterface $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function getByPayPalOrderId(string $orderId): PaymentInterface
    {
        /** @var PaymentInterface[] $payments */
        $payments = $this->paymentRepository->findAll();

        foreach ($payments as $payment) {
            $details = $payment->getDetails();

            if (isset($details['paypal_order_id']) && $details['paypal_order_id'] === $orderId) {
                return $payment;
            }
        }

        throw PaymentNotFoundException::withPayPalOrderId($orderId);
    }
}
