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

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalPaymentOnErrorAction
{
    private const MAX_LOGGED_CONTENT_LENGTH = 2000;

    public function __construct(
        private RequestStack $flashBagOrRequestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /**
         * @var string $content
         */
        $content = $request->getContent();

        $this->logger->error($this->sanitizeForLogging($content));
        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
            ->add('error', 'sylius_paypal.something_went_wrong')
        ;

        return new Response();
    }

    private function sanitizeForLogging(string $content): string
    {
        $sanitized = str_replace(["\r", "\n"], ' ', $content);

        return mb_substr($sanitized, 0, self::MAX_LOGGED_CONTENT_LENGTH);
    }
}
