<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class CacheAuthorizeClientApi implements CacheAuthorizeClientApiInterface
{
    private ObjectManager $payPalCredentialsManager;

    private ObjectRepository $payPalCredentialsRepository;

    private AuthorizeClientApiInterface $authorizeClientApi;

    private UuidProviderInterface $uuidProvider;

    public function __construct(
        ObjectManager $payPalCredentialsManager,
        ObjectRepository $payPalCredentialsRepository,
        AuthorizeClientApiInterface $authorizeClientApi,
        UuidProviderInterface $uuidProvider
    ) {
        $this->payPalCredentialsManager = $payPalCredentialsManager;
        $this->payPalCredentialsRepository = $payPalCredentialsRepository;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->uuidProvider = $uuidProvider;
    }

    public function authorize(PaymentMethodInterface $paymentMethod): string
    {
        /** @var PayPalCredentialsInterface|null $payPalCredentials */
        $payPalCredentials = $this->payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod]);
        if ($payPalCredentials !== null && !$payPalCredentials->isExpired()) {
            return $payPalCredentials->accessToken();
        }

        if ($payPalCredentials !== null && $payPalCredentials->isExpired()) {
            $this->payPalCredentialsManager->remove($payPalCredentials);
            $this->payPalCredentialsManager->flush();
        }

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $token = $this->authorizeClientApi->authorize(
            (string) $config['client_id'], (string) $config['client_secret']
        );
        $payPalCredentials = new PayPalCredentials(
            $this->uuidProvider->provide(), $paymentMethod, $token, new \DateTime(), 3600
        );

        $this->payPalCredentialsManager->persist($payPalCredentials);
        $this->payPalCredentialsManager->flush();

        return $payPalCredentials->accessToken();
    }
}
