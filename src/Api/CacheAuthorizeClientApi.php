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

namespace Sylius\PayPalPlugin\Api;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class CacheAuthorizeClientApi implements CacheAuthorizeClientApiInterface
{
    private const EXPIRY_MARGIN_IN_SECONDS = 300;

    /** @param ObjectRepository<PayPalCredentialsInterface> $payPalCredentialsRepository */
    public function __construct(
        private ObjectManager $payPalCredentialsManager,
        private ObjectRepository $payPalCredentialsRepository,
        private AuthorizeClientApiInterface $authorizeClientApi,
        private UuidProviderInterface $uuidProvider,
        private ClockInterface $clock,
    ) {
    }

    public function authorize(PaymentMethodInterface $paymentMethod): string
    {
        if (null === $paymentMethod->getId()) {
            return $this->fetch($paymentMethod)->value();
        }

        $now = \DateTime::createFromImmutable($this->clock->now());

        $payPalCredentials = $this->payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod]);
        if ($payPalCredentials !== null && !$payPalCredentials->isExpired($now)) {
            return $payPalCredentials->accessToken();
        }

        $accessToken = $this->fetch($paymentMethod);
        $expiresIn = max(0, $accessToken->expiresIn() - self::EXPIRY_MARGIN_IN_SECONDS);

        if ($payPalCredentials === null) {
            $payPalCredentials = new PayPalCredentials($this->uuidProvider->provide(), $paymentMethod, $accessToken->value(), $now, $expiresIn);
            $this->payPalCredentialsManager->persist($payPalCredentials);
        } else {
            $payPalCredentials->refresh($accessToken->value(), $now, $expiresIn);
        }

        $this->payPalCredentialsManager->flush();

        return $payPalCredentials->accessToken();
    }

    public function invalidate(PaymentMethodInterface $paymentMethod): void
    {
        if (null === $paymentMethod->getId()) {
            return;
        }

        $payPalCredentials = $this->payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod]);
        if ($payPalCredentials === null) {
            return;
        }

        $payPalCredentials->expire();
        $this->payPalCredentialsManager->flush();
    }

    private function fetch(PaymentMethodInterface $paymentMethod): AccessToken
    {
        $config = PayPalGatewayConfig::fromGatewayConfig($paymentMethod->getGatewayConfig());

        return $this->authorizeClientApi->authorize($config->clientId(), $config->clientSecret(), $config->isSandbox());
    }
}
