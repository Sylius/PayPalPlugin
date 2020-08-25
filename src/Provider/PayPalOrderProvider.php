<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class PayPalOrderProvider implements PayPalOrderProviderInterface
{
    /** @var PaymentProviderInterface */
    private $paymentProvider;

    public function __construct(PaymentProviderInterface $paymentProvider)
    {
        $this->paymentProvider = $paymentProvider;
    }

    public function provide(Request $request): OrderInterface
    {
        $content = (array) json_decode((string) $request->getContent(false), true);
        Assert::keyExists($content, 'resource');
        $resource = (array) $content['resource'];
        Assert::keyExists($resource, 'id');

        $payment = $this->paymentProvider->getByPayPalOrderId($resource['id']);
        Assert::notNull($payment);

        return $payment->getOrder();
    }
}
