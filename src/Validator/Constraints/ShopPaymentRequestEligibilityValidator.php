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

use Sylius\Bundle\ApiBundle\Command\Payment\AddPaymentRequest;
use Sylius\Bundle\ApiBundle\Command\Payment\UpdatePaymentRequest;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledCheckerInterface;
use Sylius\PayPalPlugin\CommandHandler\CreatePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ShopPaymentRequestEligibilityValidator extends ConstraintValidator
{
    private const ALLOWED_ACTIONS = [
        PaymentRequestInterface::ACTION_CAPTURE,
        PaymentRequestInterface::ACTION_AUTHORIZE,
        PaymentRequestInterface::ACTION_STATUS,
    ];

    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentRequestRepositoryInterface $paymentRequestRepository,
        private readonly PayPalPaymentMethodsResolverInterface $payPalPaymentMethodsResolver,
        private readonly PayPalPaymentSourceProviderInterface $paymentSourceProvider,
        private readonly PaymentSourceEnabledCheckerInterface $paymentSourceEnabledChecker,
        private readonly PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ShopPaymentRequestEligibility) {
            throw new UnexpectedTypeException($constraint, ShopPaymentRequestEligibility::class);
        }

        if ($value instanceof AddPaymentRequest) {
            $this->validateAddPaymentRequest($value, $constraint);

            return;
        }

        if ($value instanceof UpdatePaymentRequest) {
            $this->validateUpdatePaymentRequest($value, $constraint);

            return;
        }

        throw new UnexpectedValueException($value, AddPaymentRequest::class . '|' . UpdatePaymentRequest::class);
    }

    private function validateAddPaymentRequest(AddPaymentRequest $addPaymentRequest, ShopPaymentRequestEligibility $constraint): void
    {
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $addPaymentRequest->paymentMethodCode]);
        if (null === $paymentMethod || !$this->isPayPalPaymentMethod($paymentMethod)) {
            return;
        }

        if (null !== $addPaymentRequest->action && !in_array($addPaymentRequest->action, self::ALLOWED_ACTIONS, true)) {
            $this->context
                ->buildViolation($constraint->actionNotAllowedMessage)
                ->setParameter('{{ action }}', $addPaymentRequest->action)
                ->atPath('action')
                ->addViolation()
            ;

            return;
        }

        $expectedAction = $this->paymentAuthorizationChecker->isAuthorizeEnabled($paymentMethod)
            ? PaymentRequestInterface::ACTION_AUTHORIZE
            : PaymentRequestInterface::ACTION_CAPTURE
        ;
        if (
            null !== $addPaymentRequest->action &&
            PaymentRequestInterface::ACTION_STATUS !== $addPaymentRequest->action &&
            $expectedAction !== $addPaymentRequest->action
        ) {
            $this->context
                ->buildViolation($constraint->actionNotMatchingPaymentMethodMessage)
                ->setParameter('{{ action }}', $addPaymentRequest->action)
                ->setParameter('{{ expected_action }}', $expectedAction)
                ->atPath('action')
                ->addViolation()
            ;

            return;
        }

        /** @var PaymentInterface|null $payment */
        $payment = $this->paymentRepository->findOneByOrderToken($addPaymentRequest->paymentId, $addPaymentRequest->orderTokenValue);
        /** @var ChannelInterface|null $channel */
        $channel = $payment?->getOrder()?->getChannel();
        if (null === $channel) {
            return;
        }

        if (!in_array($paymentMethod, $this->payPalPaymentMethodsResolver->getInChannel($channel), true)) {
            $this->context
                ->buildViolation($constraint->paymentMethodNotAvailableMessage)
                ->atPath('paymentMethodCode')
                ->addViolation()
            ;

            return;
        }

        if (PaymentRequestInterface::ACTION_STATUS === $addPaymentRequest->action) {
            return;
        }

        $this->validatePayload($addPaymentRequest->payload, $paymentMethod, $constraint);
    }

    private function validateUpdatePaymentRequest(UpdatePaymentRequest $updatePaymentRequest, ShopPaymentRequestEligibility $constraint): void
    {
        $paymentRequest = $this->paymentRequestRepository->find($updatePaymentRequest->hash);
        if (
            null === $paymentRequest ||
            PaymentRequestInterface::STATE_NEW !== $paymentRequest->getState() ||
            PaymentRequestInterface::ACTION_STATUS === $paymentRequest->getAction()
        ) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();
        if (!$this->isPayPalPaymentMethod($paymentMethod)) {
            return;
        }

        $this->validatePayload($updatePaymentRequest->payload, $paymentMethod, $constraint);
    }

    private function validatePayload(mixed $payload, PaymentMethodInterface $paymentMethod, ShopPaymentRequestEligibility $constraint): void
    {
        if (
            !is_array($payload) ||
            [CreatePayPalOrderPaymentRequestHandler::PAYLOAD_PAYMENT_SOURCE] !== array_keys($payload) ||
            !is_string($payload[CreatePayPalOrderPaymentRequestHandler::PAYLOAD_PAYMENT_SOURCE])
        ) {
            $this->context
                ->buildViolation($constraint->payloadInvalidMessage)
                ->atPath('payload')
                ->addViolation()
            ;

            return;
        }

        $paymentSource = $payload[CreatePayPalOrderPaymentRequestHandler::PAYLOAD_PAYMENT_SOURCE];
        if (
            $this->paymentSourceProvider->supports($paymentSource) &&
            $this->paymentSourceEnabledChecker->isEnabled($paymentSource, $paymentMethod)
        ) {
            return;
        }

        $this->context
            ->buildViolation($constraint->paymentSourceNotAvailableMessage)
            ->setParameter('{{ payment_source }}', $paymentSource)
            ->atPath('payload.' . CreatePayPalOrderPaymentRequestHandler::PAYLOAD_PAYMENT_SOURCE)
            ->addViolation()
        ;
    }

    private function isPayPalPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        return SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $paymentMethod->getGatewayConfig()?->getFactoryName();
    }
}
