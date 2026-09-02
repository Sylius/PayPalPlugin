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

namespace Sylius\PayPalPlugin\Provider;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutorInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\PartnerCredentials;
use Symfony\Component\HttpFoundation\Request;

final readonly class PartnerCredentialsProvider implements PartnerCredentialsProviderInterface
{
    private const CACHE_KEY = 'sylius_paypal.partner_credentials';

    public function __construct(
        private PayPalOnboardingRequestExecutorInterface $requestExecutor,
        private RequestFactoryInterface $requestFactory,
        private CacheItemPoolInterface $cache,
        private string $partnerCredentialsUrl,
        private int $cacheTtl = 3600,
        private string $fallbackPartnerId = '',
        private string $fallbackPartnerClientId = '',
    ) {
    }

    public function provide(): PartnerCredentials
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            /** @var array{partner_id: string, partner_client_id: string, partner_logo_url?: string} $cached */
            $cached = $item->get();

            return new PartnerCredentials(
                $cached['partner_id'],
                $cached['partner_client_id'],
                $cached['partner_logo_url'] ?? '',
            );
        }

        try {
            $request = $this->requestFactory->createRequest(Request::METHOD_GET, $this->partnerCredentialsUrl)
                ->withHeader('Accept', 'application/json')
            ;

            $content = $this->requestExecutor->execute($request, 'Partner credentials');

            $partnerId = (string) ($content['partner_id'] ?? '');
            $partnerClientId = (string) ($content['partner_client_id'] ?? '');
            $partnerLogoUrl = (string) ($content['partner_logo_url'] ?? '');

            if ('' === $partnerId || '' === $partnerClientId) {
                throw new PayPalPluginException('partner_id/partner_client_id is missing in response');
            }
        } catch (\Throwable $exception) {
            // partner_id/partner_client_id are static and identical for every store, so a failing/slow
            // partner-credentials call should not break onboarding or re-enabling an existing seller.
            if ('' === $this->fallbackPartnerId || '' === $this->fallbackPartnerClientId) {
                throw $exception;
            }

            return new PartnerCredentials($this->fallbackPartnerId, $this->fallbackPartnerClientId);
        }

        $item->set([
            'partner_id' => $partnerId,
            'partner_client_id' => $partnerClientId,
            'partner_logo_url' => $partnerLogoUrl,
        ]);
        $item->expiresAfter($this->cacheTtl);
        $this->cache->save($item);

        return new PartnerCredentials($partnerId, $partnerClientId, $partnerLogoUrl);
    }
}
