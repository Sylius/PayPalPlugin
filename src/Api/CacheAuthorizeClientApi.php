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
use Ramsey\Uuid\Uuid;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;

final class CacheAuthorizeClientApi implements CacheAuthorizeClientApiInterface
{
    /** @var ObjectManager */
    private $payPalCredentialsManager;

    /** @var ObjectRepository */
    private $payPalCredentialsRepository;

    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    public function __construct(
        ObjectManager $payPalCredentialsManager,
        ObjectRepository $payPalCredentialsRepository,
        AuthorizeClientApiInterface $authorizeClientApi
    ) {
        $this->payPalCredentialsManager = $payPalCredentialsManager;
        $this->payPalCredentialsRepository = $payPalCredentialsRepository;
        $this->authorizeClientApi = $authorizeClientApi;
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

        $token = $this->authorizeClientApi->authorize($config['client_id'], $config['client_secret']);
        $payPalCredentials = new PayPalCredentials(
            Uuid::uuid4()->toString(), $paymentMethod, $token, new \DateTime(), 3600
        );

        $this->payPalCredentialsManager->persist($payPalCredentials);
        $this->payPalCredentialsManager->flush();

        return $payPalCredentials->accessToken();
    }
}
