<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\Bridge\Symfony\DependencyInjection;

use MakinaCorpus\DbToolsBundle\Configuration\Configuration;
use MakinaCorpus\DbToolsBundle\Storage\FilenameStrategyInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class DbToolsExtension extends Extension
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);
        $config = DbToolsConfiguration::finalizeConfiguration($config);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.yaml');

        $configDef = new Definition();
        $configDef->setClass(Configuration::class);
        $configDef->setArguments([
            '$backupBinary' => $config['backup_binary'] ?? '%env(resolve:DBTOOLS_BACKUP_BINARY)%',
            '$backupExcludedTables' => $config['backup_excluded_tables'] ?? null, // new Parameter('env(resolve:array:DBTOOLS_BACKUP_EXCLUDED_TABLES)'), // @todo
            '$backupExpirationAge' => $config['backup_expiration_age'] ?? '%env(resolve:string:DBTOOLS_BACKUP_EXPIRATION_AGE)%',
            '$backupOptions' => $config['backup_options'] ?? '%env(resolve:DBTOOLS_BACKUP_OPTIONS)%',
            '$backupTimeout' => $config['backup_timeout'] ?? '%env(int:DBTOOLS_BACKUP_TIMEOUT)%',
            '$parent' => null, // For Symfony 6.x.
            '$restoreBinary' => $config['restore_binary'] ?? '%env(resolve:DBTOOLS_RESTORE_BINARY)%',
            '$restoreOptions' => $config['restore_options'] ?? '%env(resolve:DBTOOLS_RESTORE_OPTIONS)%',
            '$restoreTimeout' => $config['restore_timeout'] ?? '%env(int:DBTOOLS_RESTORE_TIMEOUT)%',
            '$storageDirectory' => $config['storage_directory'] ?? '%env(resolve:DBTOOLS_STORAGE_DIRECTORY)%',
            '$storageFilenameStrategy' => $config['storage_filename_strategy'] ?? '%env(resolve:DBTOOLS_STORAGE_FILENAME_STRATEGY)%',
        ]);
        $container->setDefinition('db_tools.configuration.default', $configDef);

        $connectionDefs = [];
        foreach ($config['connections'] as $name => $data) {
            $connConfigDef = new Definition();
            $connConfigDef->setClass(Configuration::class);
            $connConfigDef->setArguments([
                '$backupBinary' => $data['backup_binary'] ?? null,
                '$backupExcludedTables' => $data['backup_excluded_tables'] ?? null,
                '$backupExpirationAge' => $data['backup_expiration_age'] ?? null,
                '$backupOptions' => $data['backup_options'] ?? null,
                '$backupTimeout' => $data['backup_timeout'] ?? null,
                '$restoreBinary' => $data['restore_binary'] ?? null,
                '$restoreOptions' => $data['restore_options'] ?? null,
                '$restoreTimeout' => $data['restore_timeout'] ?? null,
                '$parent' => new Reference('db_tools.configuration.default'),
                '$storageDirectory' => $data['storage_directory'] ?? null,
                '$storageFilenameStrategy' => $data['storage_filename_strategy'] ?? null,
            ]);
            $container->setDefinition('db_tools.configuration.connection.' . $name, $connConfigDef);
            $connectionDefs[$name] = new Reference('db_tools.configuration.connection.' . $name);
        }

        $container->getDefinition('db_tools.configuration.registry')->setArguments([new Reference('db_tools.configuration.default'), $connectionDefs]);

        // Validate user-given anonymizer paths.
        $anonymizerPaths = $config['anonymizer_paths'];
        foreach ($anonymizerPaths as $userPath) {
            $resolvedUserPath = $container->getParameterBag()->resolveValue($userPath);
            if (!\is_dir($resolvedUserPath)) {
                throw new InvalidArgumentException(\sprintf('"db_tools.anonymizer_paths": path "%s" does not exist', $userPath));
            }
        }
        // Only set the default provided one if the folder exists in order to
        // prevent "directory does not exist" errors.
        $defaultDirectory = $container->getParameterBag()->resolveValue('%kernel.project_dir%/src/Anonymizer');
        if (\is_dir($defaultDirectory)) {
            $anonymizerPaths[] = '%kernel.project_dir%/src/Anonymizer';
        }

        $container->setParameter('db_tools.anonymization.anonymizer.paths', $anonymizerPaths);

        // Register filename strategies.
        $strategies = [];
        foreach (($config['storage']['filename_strategy'] ?? []) as $connectionName => $strategyId) {
            // Default is handled directly by the storage service.
            if ($strategyId !== null && $strategyId !== 'default' && $strategyId !== 'datetime') {
                if ($container->hasDefinition($strategyId)) {
                    $strategies[$connectionName] = new Reference($strategyId);
                } elseif (\class_exists($strategyId)) {
                    if (!\is_subclass_of($strategyId, FilenameStrategyInterface::class)) {
                        throw new InvalidArgumentException(\sprintf('"db_tools.connections.%s.filename_strategy": class "%s" does not implement "%s"', $connectionName, $strategyId, FilenameStrategyInterface::class));
                    }
                    $serviceId = '.db_tools.filename_strategy.' . \sha1($strategyId);
                    $container->setDefinition($serviceId, (new Definition())->setClass($strategyId));
                    $strategies[$connectionName] = new Reference($serviceId);
                } else {
                    throw new InvalidArgumentException(\sprintf('"db_tools.connections.%s.filename_strategy": class or service "%s" does not exist or is not registered in container', $connectionName, $strategyId));
                }
                break;
            }
        }
        if ($strategies) {
            $container->getDefinition('db_tools.storage')->setArgument(1, $strategies);
        }
    }

    #[\Override]
    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new DbToolsConfiguration(true, false);
    }
}
