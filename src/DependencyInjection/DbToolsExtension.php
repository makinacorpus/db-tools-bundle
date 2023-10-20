<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\CacheWarmer\AnonymizatorCacheWarmer;
use MakinaCorpus\DbToolsBundle\Anonymizer\Anonymizator;
use MakinaCorpus\DbToolsBundle\Anonymizer\Loader\AttributesLoader;
use MakinaCorpus\DbToolsBundle\Anonymizer\Loader\YamlLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class DbToolsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/../config'));
        $loader->load('services.yaml');

        $container->setParameter('db_tools.storage_directory', $config['storage_directory']);

        // Backupper
        $container->setParameter('db_tools.backupper.binaries', $config['backupper_binaries']);
        $container->setParameter('db_tools.backup_expiration_age', $config['backup_expiration_age']);
        $container->setParameter('db_tools.excluded_tables', $config['excluded_tables'] ?? []);

        // Restorer
        $container->setParameter('db_tools.restorer.binaries', $config['restorer_binaries']);

        // Validate user-given anonymizer paths.
        $anonymizerPaths = $config['anonymizer_paths'];
        foreach ($anonymizerPaths as $userPath) {
            $resolvedUserPath = $container->getParameterBag()->resolveValue($userPath);
            if (!\is_dir($resolvedUserPath)) {
                throw new InvalidArgumentException(\sprintf('"db_tools.anonymizer_paths": path "%s" does not exist', $userPath));
            }
        }
        // Only set the default provided one if the folder exists in order to
        // prevent "directory does not exists" errors.
        $defaultDirectory = $container->getParameterBag()->resolveValue('%kernel.project_dir%/src/Anonymizer');
        if (\is_dir($defaultDirectory)) {
            $anonymizerPaths[] = '%kernel.project_dir%/src/Anonymizer';
        }
        $anonymizerPaths[] = \realpath(\dirname(__DIR__)) . '/Anonymizer';

        // Anonymization
        $container->setParameter('db_tools.anonymization.anonymizer.paths', $anonymizerPaths);

        if (isset($config['anonymization']) && isset($config['anonymization']['type'])) {
            $type = $config['anonymization']['type'];
            if ('yaml' === $type && !isset($config['anonymization']['file'])) {
                throw new \InvalidArgumentException(<<<TXT
                If you want to configure your anonymization with a yaml file,
                you should provide a 'file' parameter
                TXT);
            }

            // Foreach doctrine connection
            foreach (['default' => 1] as $connection => $connectionConfig) {
                // We register the configuration loader.
                $loaderId = match ($type) {
                    'yaml' => $this->registerYamlLoader($connection, $config['anonymization']['file'], $container),
                    'attributes' => $this->registerAttributesLoader($connection, $container),
                    default => throw new \InvalidArgumentException(\sprintf("'%s' type is unknown. Available types are 'yaml' and 'attributes'.", $type)),
                };

                // We register the anonymizator
                $definition = new Definition();
                $definition->setClass(Anonymizator::class);
                $definition->setArguments([
                    $connection,
                    new Reference(\sprintf('doctrine.dbal.%s_connection', $connection)),
                    new Reference('db_tools.anonymization.anonymizer.registry'),
                    new Reference($loaderId),
                ]);
                $definition->addTag('db_tools.anonymization.anonymizator');

                $anonymizatorId = \sprintf('db_tools.anonymization.%s_anonymizator', $connection);
                $container->setDefinition($anonymizatorId, $definition);

                $this->createAnonymizatorWarmup($connection, $anonymizatorId, $container);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new DbToolsConfiguration();
    }

    private function registerYamlLoader(string $connection, string $file, ContainerBuilder $container): string
    {
        $definition = new Definition();
        $definition->setClass(YamlLoader::class);
        $definition->setArguments([$connection, $file]);

        $loaderId = \sprintf('db_tools.anonymization.loader.%s', $connection);
        $container->setDefinition($loaderId, $definition);

        return $loaderId;
    }

    private function registerAttributesLoader(string $connection, ContainerBuilder $container): string
    {
        $definition = new Definition();
        $definition->setClass(AttributesLoader::class);
        $definition->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $connection))]);

        $loaderId = \sprintf('db_tools.anonymization.loader.%s', $connection);
        $container->setDefinition($loaderId, $definition);

        return $loaderId;
    }

    private function createAnonymizatorWarmup(string $connection, string $anonymizatorId, ContainerBuilder $container): void
    {
        if (! $container->getParameter('kernel.debug')) {
            $cacheWarmerId = \sprintf('db_tools.anonymization.cache_warmer.%s_anonymizator', $connection);
            $container->register($cacheWarmerId, AnonymizatorCacheWarmer::class)
                ->setArguments([new Reference($anonymizatorId)])
                ->addTag('kernel.cache_warmer', ['priority' => 0]); // priority should be lower than DoctrineMetadataCacheWarmer
        }
    }
}
