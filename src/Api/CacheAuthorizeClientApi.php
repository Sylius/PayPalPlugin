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
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final readonly class CacheAuthorizeClientApi implements CacheAuthorizeClientApiInterface
{
    /** @param ObjectRepository<PayPalCredentialsInterface> $payPalCredentialsRepository */
    public function __construct(
        private ObjectManager $payPalCredentialsManager,
        private ObjectRepository $payPalCredentialsRepository,
        private AuthorizeClientApiInterface $authorizeClientApi,
        private UuidProviderInterface $uuidProvider,
    ) {
    }

    public function authorize(PaymentMethodInterface $paymentMethod): string
    {
        $payPalCredentials = $this->payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod]);
        if ($payPalCredentials !== null && !$payPalCredentials->isExpired()) {
            return $payPalCredentials->accessToken();
        }

        if ($payPalCredentials !== null && $payPalCredentials->isExpired()) {
            $this->payPalCredentialsManager->remove($payPalCredentials);
            $this->payPalCredentialsManager->flush();
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        $token = $this->authorizeClientApi->authorize(
            $config->clientId(),
            $config->clientSecret(),
        );
        $payPalCredentials = new PayPalCredentials(
            $this->uuidProvider->provide(),
            $paymentMethod,
            $token,
            new \DateTime(),
            3600,
        );

        $this->payPalCredentialsManager->persist($payPalCredentials);
        $this->payPalCredentialsManager->flush();

        return $payPalCredentials->accessToken();
    }
}
