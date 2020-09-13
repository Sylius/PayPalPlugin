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
use Payum\Core\Exception\RequestNotSupportedException;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;

final class StatusAction implements ActionInterface
{
    public const STATUS_CAPTURED = 'CAPTURED';

    public const STATUS_CREATED = 'CREATED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_PROCESSING = 'PROCESSING';

    /** @param GetStatus $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);
        /** @var array $model */
        $model = $request->getModel();

        if ($model['status'] === self::STATUS_CREATED) {
            $request->markNew();

            return;
        }

        if ($model['status'] === self::STATUS_CAPTURED) {
            $request->markPending();

            return;
        }

        if ($model['status'] === self::STATUS_COMPLETED) {
            $request->markCaptured();

            return;
        }

        $request->markFailed();
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatus &&
            $request->getFirstModel() instanceof PaymentInterface
        ;
    }
}
