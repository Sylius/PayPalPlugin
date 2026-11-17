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
use Doctrine\DBAL\ParameterType;
use Sylius\Bundle\PaymentBundle\Checker\GatewayConfigEncryptionChecker;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Encryption\Encrypter;
use Sylius\Component\Payment\Encryption\EncrypterInterface;
use Sylius\Component\Payment\Encryption\GatewayConfigEncrypter;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Console\Command\EncryptGatewayConfigsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class EncryptGatewayConfigsCommandTest extends JsonApiTestCase
{
    private const CONFIG = ['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET', 'sandbox' => true];

    public function test_it_encrypts_a_paypal_gateway_config_saved_in_plain_text(): void
    {
        [$gatewayConfigId, $cashOnDeliveryId] = $this->loadGatewayConfigIds('paypal', 'cash_on_delivery');
        $this->storeGatewayConfig($gatewayConfigId, false, self::CONFIG);
        $this->storeGatewayConfig($cashOnDeliveryId, false, ['instructions' => 'Pay the courier']);

        $commandTester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Encrypted 1 PayPal gateway config(s).', $commandTester->getDisplay());
        self::assertFullyEncrypted($this->storedConfig($gatewayConfigId));
        self::assertEquals(self::CONFIG, $this->decrypted($this->storedConfig($gatewayConfigId)));
        self::assertSame(['instructions' => 'Pay the courier'], $this->storedConfig($cashOnDeliveryId));
    }

    public function test_it_leaves_an_encrypted_paypal_gateway_config_as_it_is(): void
    {
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, false, self::CONFIG);
        (new CommandTester($this->command()))->execute([]);
        $encryptedConfig = $this->storedConfig($gatewayConfigId);

        $commandTester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Encrypted 0 PayPal gateway config(s).', $commandTester->getDisplay());
        self::assertSame($encryptedConfig, $this->storedConfig($gatewayConfigId));
    }

    public function test_it_leaves_a_paypal_gateway_config_still_on_payum_in_plain_text(): void
    {
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, true, self::CONFIG);

        self::assertSame(Command::SUCCESS, (new CommandTester($this->command()))->execute([]));
        self::assertEquals(self::CONFIG, $this->storedConfig($gatewayConfigId));
    }

    public function test_it_leaves_paypal_gateway_configs_in_plain_text_when_encryption_is_disabled_for_paypal(): void
    {
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, false, self::CONFIG);

        $command = $this->command(encryptionChecker: new GatewayConfigEncryptionChecker(['sylius_paypal']));

        self::assertSame(Command::SUCCESS, (new CommandTester($command))->execute([]));
        self::assertEquals(self::CONFIG, $this->storedConfig($gatewayConfigId));
    }

    public function test_it_does_nothing_when_encryption_of_payment_data_is_disabled(): void
    {
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, false, self::CONFIG);

        /** @var Command $command */
        $command = self::getContainer()->get('sylius_paypal.console.command.encrypt_gateway_configs');
        $commandTester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('sylius_payment.encryption.enabled', $commandTester->getDisplay());
        self::assertEquals(self::CONFIG, $this->storedConfig($gatewayConfigId));
    }

    public function test_it_decrypts_a_paypal_gateway_config_encrypted_by_payum_before_encrypting_it(): void
    {
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, false, [
            'client_id' => 'payum:CLIENT_ID',
            'client_secret' => 'payum:CLIENT_SECRET',
            'sandbox' => true,
            'encrypted' => true,
        ]);

        $command = $this->command(payumCypher: new class() {
            public function decrypt(string $value): string
            {
                return substr($value, strlen('payum:'));
            }
        });

        self::assertSame(Command::SUCCESS, (new CommandTester($command))->execute([]));
        self::assertEquals(self::CONFIG, $this->decrypted($this->storedConfig($gatewayConfigId)));
    }

    public function test_it_fails_on_a_paypal_gateway_config_encrypted_by_payum_without_the_payum_key(): void
    {
        $payumEncryptedConfig = ['client_id' => 'payum:CLIENT_ID', 'sandbox' => true, 'encrypted' => true];
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, false, $payumEncryptedConfig);

        $commandTester = new CommandTester($this->command());

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('payum.dynamic_gateways.encryption.defuse_secret_key', $this->display($commandTester));
        self::assertEquals($payumEncryptedConfig, $this->storedConfig($gatewayConfigId));
    }

    public function test_it_fails_with_a_hint_to_generate_a_key_when_the_encryption_key_is_missing(): void
    {
        [$gatewayConfigId] = $this->loadGatewayConfigIds('paypal');
        $this->storeGatewayConfig($gatewayConfigId, false, self::CONFIG);

        $commandTester = new CommandTester($this->command(encrypter: new Encrypter('/nonexistent/encryption.key')));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('sylius:payment:generate-key', $this->display($commandTester));
        self::assertEquals(self::CONFIG, $this->storedConfig($gatewayConfigId));
    }

    private function command(
        ?GatewayConfigEncryptionChecker $encryptionChecker = null,
        ?EncrypterInterface $encrypter = null,
        ?object $payumCypher = null,
    ): EncryptGatewayConfigsCommand {
        return new EncryptGatewayConfigsCommand(
            $this->connection(),
            self::getContainer()->get('sylius.factory.gateway_config'),
            $encryptionChecker ?? new GatewayConfigEncryptionChecker([]),
            new GatewayConfigEncrypter($encrypter ?? $this->encrypter()),
            $payumCypher,
        );
    }

    private function encrypter(): Encrypter
    {
        return new Encrypter(self::getContainer()->getParameter('kernel.project_dir') . '/../../../tests/TestApplication/config/encryption/test.key');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function decrypted(array $config): array
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig($config);
        (new GatewayConfigEncrypter($this->encrypter()))->decrypt($gatewayConfig);

        return $gatewayConfig->getConfig();
    }

    /** @param array<string, mixed> $config */
    private static function assertFullyEncrypted(array $config): void
    {
        foreach ($config as $value) {
            self::assertIsString($value);
            self::assertStringEndsWith(EncrypterInterface::ENCRYPTION_SUFFIX, $value);
        }
    }

    /** @return list<int> */
    private function loadGatewayConfigIds(string ...$paymentMethods): array
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        return array_map(static function (string $paymentMethod) use ($fixtures): int {
            /** @var PaymentMethodInterface $fixture */
            $fixture = $fixtures[$paymentMethod];

            return (int) $fixture->getGatewayConfig()?->getId();
        }, $paymentMethods);
    }

    /** @param array<string, mixed> $config */
    private function storeGatewayConfig(int $id, bool $usePayum, array $config): void
    {
        $this->connection()->executeStatement(
            'UPDATE sylius_gateway_config SET use_payum = ?, config = ? WHERE id = ?',
            [$usePayum, json_encode($config, \JSON_THROW_ON_ERROR), $id],
            [ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    /** @return array<string, mixed> */
    private function storedConfig(int $id): array
    {
        /** @var string $config */
        $config = $this->connection()->fetchOne('SELECT config FROM sylius_gateway_config WHERE id = ?', [$id]);

        /** @var array<string, mixed> $decodedConfig */
        $decodedConfig = json_decode($config, true, 512, \JSON_THROW_ON_ERROR);

        return $decodedConfig;
    }

    private function display(CommandTester $commandTester): string
    {
        return preg_replace('/\s+/', ' ', $commandTester->getDisplay()) ?? '';
    }

    private function connection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}
