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

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;

final class DummyRefundPaymentApi implements RefundPaymentApiInterface
{
    public const REJECTED_CAPTURE_ID = 'REJECTED_CAPTURE_ID';

    public const PENDING_CAPTURE_ID = 'PENDING_CAPTURE_ID';

    /** @var list<string> */
    public static array $refundedPaymentIds = [];

    /** @var list<string|null> */
    public static array $requestIds = [];

    public function refund(
        PaymentMethodInterface $paymentMethod,
        string $paymentId,
        string $payPalAuthAssertion,
        string $invoiceNumber,
        string $amount,
        string $currencyCode,
        ?string $requestId = null,
    ): array {
        self::$requestIds[] = $requestId;

        if (self::REJECTED_CAPTURE_ID === $paymentId) {
            throw new PayPalApiRequestException(
                'POST',
                sprintf('v2/payments/captures/%s/refund', $paymentId),
                422,
                'UNPROCESSABLE_ENTITY',
                'The requested action could not be performed, semantically incorrect, or failed business validation.',
                ['REFUND_AMOUNT_EXCEEDED'],
            );
        }

        self::$refundedPaymentIds[] = $paymentId;

        return ['status' => self::PENDING_CAPTURE_ID === $paymentId ? 'PENDING' : 'COMPLETED', 'id' => $paymentId];
    }

    public static function reset(): void
    {
        self::$refundedPaymentIds = [];
        self::$requestIds = [];
    }
}
