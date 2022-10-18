<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Application;

use PSS\SymfonyMockerContainer\DependencyInjection\MockerContainer;
use Sylius\Bundle\CoreBundle\Application\Kernel as SyliusKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';

        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->addResource(new FileResource($this->getProjectDir() . '/config/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', true);

        foreach ($this->getConfigDirectories() as $confDir) {
            $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
            $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
            $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
            $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach ($this->getConfigDirectories() as $confDir) {
            $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS);
            $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS);
            $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS);
        }
    }

    protected function getContainerBaseClass(): string
    {
        if ($this->isTestEnvironment() && class_exists(MockerContainer::class)) {
            return MockerContainer::class;
        }

        return parent::getContainerBaseClass();
    }

    private function getConfigDirectories(): array
    {
        return array_filter([
            $this->getProjectDir() . '/config',
            $this->getProjectDir() . '/config/sylius/' . SyliusKernel::MAJOR_VERSION . '.' . SyliusKernel::MINOR_VERSION,
        ], 'file_exists');
    }

    private function isTestEnvironment(): bool
    {
        return 0 === strpos($this->getEnvironment(), 'test');
    }
}
