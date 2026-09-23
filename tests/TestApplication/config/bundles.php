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

$bundles = [
    Sylius\PayPalPlugin\SyliusPayPalPlugin::class => ['all' => true],
];

if (class_exists('winzou\Bundle\StateMachineBundle\winzouStateMachineBundle')) {
    $bundles[winzou\Bundle\StateMachineBundle\winzouStateMachineBundle::class] = ['all' => true];
}

return $bundles;
