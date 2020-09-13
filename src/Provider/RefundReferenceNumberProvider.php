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

final class RefundReferenceNumberProvider implements RefundReferenceNumberProviderInterface
{
    public function provide(PaymentInterface $payment): string
    {
        return ((string) $payment->getId()) . '-' . (new \DateTime())->format('d-m-Y');
    }
}
