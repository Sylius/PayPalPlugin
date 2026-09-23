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

namespace Sylius\PayPalPlugin\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

// TODO: remove once the real partner-credentials endpoint is in place.
final class PartnerCredentialsTestAction
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'partner_id' => '',
            'partner_client_id' => '',
            'partner_logo_url' => '',
        ]);
    }
}
