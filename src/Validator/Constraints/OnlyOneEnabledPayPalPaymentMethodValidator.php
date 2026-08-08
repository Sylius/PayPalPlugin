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

namespace Sylius\PayPalPlugin\Validator\Constraints;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class OnlyOneEnabledPayPalPaymentMethodValidator extends ConstraintValidator
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OnlyOneEnabledPayPalPaymentMethod) {
            throw new UnexpectedTypeException($constraint, OnlyOneEnabledPayPalPaymentMethod::class);
        }

        if (!$value instanceof PaymentMethodInterface) {
            throw new UnexpectedValueException($value, PaymentMethodInterface::class);
        }

        if (!$this->isPayPalMethod($value) || !$value->isEnabled()) {
            return;
        }

        $allMethods = $this->paymentMethodRepository->findBy(['enabled' => true]);
        foreach ($allMethods as $method) {
            if ($method->getId() === $value->getId()) {
                continue;
            }
            if (!$this->isPayPalMethod($method)) {
                continue;
            }

            $this->context
                ->buildViolation($constraint->message)
                ->atPath('enabled')
                ->addViolation()
            ;

            return;
        }
    }

    private function isPayPalMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig?->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME;
    }
}
