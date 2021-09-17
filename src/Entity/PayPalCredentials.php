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

namespace Sylius\PayPalPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_paypal_plugin_pay_pal_credentials")
 */
class PayPalCredentials implements PayPalCredentialsInterface
{
    /**
     *
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private string $id;

    /**
     *
     * @ORM\ManyToOne(targetEntity="Sylius\Component\Core\Model\PaymentMethodInterface")
     * @ORM\JoinColumn(name="payment_method_id", referencedColumnName="id")
     */
    private PaymentMethodInterface $paymentMethod;

    /**
     * @ORM\Column(type="string", name="access_token")
     */
    private string $accessToken;

    /**
     * @ORM\Column(type="datetime", name="creation_time")
     */
    private \DateTime $creationTime;

    /**
     * @ORM\Column(type="datetime", name="expiration_time")
     */
    private \DateTime $expirationTime;

    public function __construct(
        string $id,
        PaymentMethodInterface $paymentMethod,
        string $accessToken,
        \DateTime $creationTime,
        int $expiresIn
    ) {
        $this->id = $id;
        $this->paymentMethod = $paymentMethod;
        $this->accessToken = $accessToken;
        $this->creationTime = $creationTime;
        $this->expirationTime = (clone $creationTime)->modify('+' . $expiresIn . ' seconds');
    }

    public function id(): string
    {
        return $this->id;
    }

    public function paymentMethod(): PaymentMethodInterface
    {
        return $this->paymentMethod;
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function creationTime(): \DateTime
    {
        return $this->creationTime;
    }

    public function expirationTime(): \DateTime
    {
        return $this->expirationTime;
    }

    public function isExpired(): bool
    {
        return new \DateTime() > $this->expirationTime;
    }
}
