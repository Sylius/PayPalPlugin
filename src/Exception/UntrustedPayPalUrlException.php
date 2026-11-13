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

final class UntrustedPayPalUrlException extends \Exception implements PermanentWebhookFailureInterface
{
    public function __construct(string $url)
    {
        parent::__construct(sprintf('Refusing to call "%s" with the PayPal credentials, as it is not a PayPal API URL.', $url));
    }
}
