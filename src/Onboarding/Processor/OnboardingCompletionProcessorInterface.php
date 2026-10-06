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

use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodAlreadyExistsException;
use Sylius\PayPalPlugin\Model\OnboardingCompletionResult;

interface OnboardingCompletionProcessorInterface
{
    /**
     * @throws PayPalPaymentMethodAlreadyExistsException
     * @throws OnboardingSessionExpiredException
     * @throws OnboardingFailedException
     */
    public function process(string $authCode, string $sharedId): OnboardingCompletionResult;
}
