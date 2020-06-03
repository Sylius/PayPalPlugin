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
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class StatusAction implements ActionInterface
{
    public function execute($request): void
    {
        $request->markCaptured();
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getFirstModel() instanceof PaymentInterface
        ;
    }
}
