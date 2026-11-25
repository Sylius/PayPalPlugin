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

namespace Sylius\PayPalPlugin\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Sylius\Bundle\PaymentBundle\Checker\GatewayConfigEncryptionCheckerInterface;
use Sylius\Component\Payment\Encryption\EncryptionCheckTrait;
use Sylius\Component\Payment\Encryption\EntityEncrypterInterface;
use Sylius\Component\Payment\Encryption\Exception\EncryptionException;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sylius-paypal:encrypt-gateway-configs',
    description: 'Encrypts the PayPal gateway configs that were saved in plain text under Payum',
)]
final class EncryptGatewayConfigsCommand extends Command
{
    use EncryptionCheckTrait;

    private const PAYUM_ENCRYPTED = 'encrypted';

    /**
     * @param FactoryInterface<GatewayConfigInterface> $gatewayConfigFactory
     * @param EntityEncrypterInterface<GatewayConfigInterface>|null $gatewayConfigEncrypter
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly FactoryInterface $gatewayConfigFactory,
        private readonly ?GatewayConfigEncryptionCheckerInterface $encryptionChecker,
        private readonly ?EntityEncrypterInterface $gatewayConfigEncrypter,
        private readonly ?object $payumCypher = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null === $this->encryptionChecker || null === $this->gatewayConfigEncrypter) {
            $io->note('Encryption of payment data is disabled ("sylius_payment.encryption.enabled"), so PayPal gateway configs stay in plain text.');

            return Command::SUCCESS;
        }

        $encrypted = 0;
        $failed = false;

        foreach ($this->findPayPalGatewayConfigs() as $row) {
            $gatewayConfig = $this->gatewayConfigToEncrypt($row, $this->encryptionChecker);
            if (null === $gatewayConfig) {
                continue;
            }

            try {
                $this->removePayumEncryption($gatewayConfig);
            } catch (\RuntimeException $exception) {
                $io->error($exception->getMessage());
                $failed = true;

                continue;
            }

            try {
                $this->encrypt($gatewayConfig, (int) $row['id'], $this->gatewayConfigEncrypter);
            } catch (EncryptionException $exception) {
                $io->error(sprintf(
                    'The PayPal gateway config "%s" could not be encrypted: %s Generate a key with "bin/console sylius:payment:generate-key" and point SYLIUS_PAYMENT_ENCRYPTION_KEY_PATH at it.',
                    $gatewayConfig->getGatewayName(),
                    $exception->getMessage(),
                ));

                return Command::FAILURE;
            }

            ++$encrypted;
        }

        if ($failed) {
            return Command::FAILURE;
        }

        $io->success(sprintf('Encrypted %d PayPal gateway config(s).', $encrypted));

        return Command::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function findPayPalGatewayConfigs(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, gateway_name, config FROM sylius_gateway_config WHERE factory_name = ? AND use_payum = ? ORDER BY id',
            [SyliusPayPalExtension::PAYPAL_FACTORY_NAME, false],
            [ParameterType::STRING, ParameterType::BOOLEAN],
        );
    }

    /** @param array<string, mixed> $row */
    private function gatewayConfigToEncrypt(array $row, GatewayConfigEncryptionCheckerInterface $encryptionChecker): ?GatewayConfigInterface
    {
        $config = json_decode((string) $row['config'], true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($config) || [] === $config || $this->isEncrypted(current($config))) {
            return null;
        }

        $gatewayConfig = $this->gatewayConfigFactory->createNew();
        $gatewayConfig->setGatewayName((string) $row['gateway_name']);
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        if (method_exists($gatewayConfig, 'setUsePayum')) {
            $gatewayConfig->setUsePayum(false);
        }
        $gatewayConfig->setConfig($config);

        return $encryptionChecker->isEncryptionEnabled($gatewayConfig) ? $gatewayConfig : null;
    }

    private function removePayumEncryption(GatewayConfigInterface $gatewayConfig): void
    {
        $config = $gatewayConfig->getConfig();
        if (true !== ($config[self::PAYUM_ENCRYPTED] ?? false)) {
            return;
        }

        if (null === $this->payumCypher || !method_exists($this->payumCypher, 'decrypt')) {
            throw new \RuntimeException(sprintf(
                'The PayPal gateway config "%s" was encrypted by Payum. Run this command with the Payum encryption key still configured ("payum.dynamic_gateways.encryption.defuse_secret_key"), so it can be decrypted first.',
                $gatewayConfig->getGatewayName(),
            ));
        }

        try {
            foreach ($config as $key => $value) {
                if (self::PAYUM_ENCRYPTED !== $key && !is_bool($value)) {
                    $config[$key] = $this->payumCypher->decrypt($value);
                }
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf(
                'The PayPal gateway config "%s" could not be decrypted with the Payum key: %s',
                $gatewayConfig->getGatewayName(),
                $exception->getMessage(),
            ), 0, $exception);
        }

        unset($config[self::PAYUM_ENCRYPTED]);
        $gatewayConfig->setConfig($config);
    }

    /** @param EntityEncrypterInterface<GatewayConfigInterface> $gatewayConfigEncrypter */
    private function encrypt(GatewayConfigInterface $gatewayConfig, int $id, EntityEncrypterInterface $gatewayConfigEncrypter): void
    {
        $gatewayConfigEncrypter->encrypt($gatewayConfig);

        $this->connection->executeStatement(
            'UPDATE sylius_gateway_config SET config = ? WHERE id = ?',
            [json_encode($gatewayConfig->getConfig(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), $id],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }
}
