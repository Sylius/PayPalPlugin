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

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Doctrine\DBAL\Connection;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApi;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Symfony\Component\Clock\MockClock;

final class CacheAuthorizeClientApiTest extends JsonApiTestCase
{
    private const TABLE = 'sylius_paypal_plugin_pay_pal_credentials';

    private Connection $connection;

    private MockClock $clock;

    /** @var list<AccessToken> */
    private array $accessTokens = [];

    private int $fetchedTokensCount = 0;

    private PaymentMethodInterface $paymentMethod;

    private CacheAuthorizeClientApi $cacheAuthorizeClientApi;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->paymentMethod = $entityManager->find($fixtures['paypal']::class, $fixtures['paypal']->getId());

        $this->connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection->executeStatement('DELETE FROM ' . self::TABLE);
        $this->clock = new MockClock('2026-10-14 12:00:00');

        $authorizeClientApi = $this->createMock(AuthorizeClientApiInterface::class);
        $authorizeClientApi->method('authorize')->willReturnCallback(function (): AccessToken {
            ++$this->fetchedTokensCount;

            return array_shift($this->accessTokens) ?? throw new \LogicException('No more access tokens to give.');
        });

        $uuidProvider = $this->createMock(UuidProviderInterface::class);
        $uuidProvider->method('provide')->willReturnCallback(static fn (): string => uniqid('', true));

        $this->cacheAuthorizeClientApi = new CacheAuthorizeClientApi(
            $entityManager,
            $entityManager->getRepository(PayPalCredentials::class),
            $authorizeClientApi,
            $uuidProvider,
            $this->clock,
        );
    }

    public function test_it_reuses_the_token_until_shortly_before_paypal_expires_it(): void
    {
        $this->accessTokens = [new AccessToken('FIRST_TOKEN', 3600), new AccessToken('SECOND_TOKEN', 3600)];

        self::assertSame('FIRST_TOKEN', $this->cacheAuthorizeClientApi->authorize($this->paymentMethod));

        $this->clock->modify('+3299 seconds');
        self::assertSame('FIRST_TOKEN', $this->cacheAuthorizeClientApi->authorize($this->paymentMethod));

        $this->clock->modify('+1 second');
        self::assertSame('SECOND_TOKEN', $this->cacheAuthorizeClientApi->authorize($this->paymentMethod));
        self::assertSame(2, $this->fetchedTokensCount);
    }

    public function test_it_keeps_a_single_token_per_payment_method(): void
    {
        $this->accessTokens = [new AccessToken('FIRST_TOKEN', 600), new AccessToken('SECOND_TOKEN', 600)];

        $this->cacheAuthorizeClientApi->authorize($this->paymentMethod);
        $this->clock->modify('+1 hour');
        $this->cacheAuthorizeClientApi->authorize($this->paymentMethod);

        self::assertSame(['SECOND_TOKEN'], $this->connection->fetchFirstColumn('SELECT access_token FROM ' . self::TABLE));
    }

    public function test_it_fetches_a_new_token_once_the_cached_one_is_invalidated(): void
    {
        $this->accessTokens = [new AccessToken('REVOKED_TOKEN', 32400), new AccessToken('NEW_TOKEN', 32400)];

        $this->cacheAuthorizeClientApi->authorize($this->paymentMethod);
        $this->cacheAuthorizeClientApi->invalidate($this->paymentMethod);

        self::assertSame('NEW_TOKEN', $this->cacheAuthorizeClientApi->authorize($this->paymentMethod));
    }
}
