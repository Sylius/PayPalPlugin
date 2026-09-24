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

/**
 * Marks a failure that delivering the same webhook event again cannot resolve, so PayPal is told the event
 * was handled instead of being asked to replay it.
 */
interface PermanentWebhookFailureInterface extends \Throwable
{
}
