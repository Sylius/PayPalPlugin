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

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\OnboardingAccountMismatchException;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Model\PermissionsUpdateResult;

interface PermissionsUpdateProcessorInterface
{
    /**
     * @throws OnboardingSessionExpiredException
     * @throws OnboardingFailedException
     * @throws OnboardingAccountMismatchException
     */
    public function process(PaymentMethodInterface $paymentMethod, string $authCode, string $sharedId): PermissionsUpdateResult;
}
