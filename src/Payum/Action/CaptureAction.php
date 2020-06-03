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

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Payum\Model\PayPalApi;

final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    /** @var PayPalApi */
    private $api;

    /** @param Capture $request */
    public function execute($request): void
    {
        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        $payment->setDetails(['status' => 200]);
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }

    public function setApi($api): void
    {
        if (!$api instanceof PayPalApi) {
            throw new UnsupportedApiException('Not supported');
        }

        $this->api = $api;
    }
}
