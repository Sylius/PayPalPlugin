<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class OrderNotFoundException extends \Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Order not found');
    }

    public static function withToken(string $token): self
    {
        return new self(sprintf('Order with token "%s" not found', $token));
    }

    public static function withId(int $id): self
    {
        return new self(sprintf('Order with id %d not found', $id));
    }
}
