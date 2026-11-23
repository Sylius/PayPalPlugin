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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;

final class PayPalVaultRequirementTest extends TestCase
{
    public function test_it_is_met_without_a_vault_request_when_the_order_needs_no_vaulting(): void
    {
        $requirement = PayPalVaultRequirement::notRequired();

        self::assertTrue($requirement->isMet());
        self::assertNull($requirement->vaultRequest());
    }

    public function test_it_is_met_with_the_vault_request_paypal_can_vault_for(): void
    {
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);
        $requirement = PayPalVaultRequirement::met($vaultRequest);

        self::assertTrue($requirement->isMet());
        self::assertSame($vaultRequest, $requirement->vaultRequest());
    }

    public function test_it_is_unmet_without_a_vault_request_when_paypal_cannot_vault(): void
    {
        $requirement = PayPalVaultRequirement::unmet();

        self::assertFalse($requirement->isMet());
        self::assertNull($requirement->vaultRequest());
    }
}
