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

namespace Sylius\PayPalPlugin\Exception;

final class PayPalWebhookResourceNotSupportedException extends \Exception implements PermanentWebhookFailureInterface
{
    public function __construct(string $resourceType)
    {
        parent::__construct(sprintf('PayPal webhook resources of type "%s" cannot be read.', $resourceType));
    }
}
