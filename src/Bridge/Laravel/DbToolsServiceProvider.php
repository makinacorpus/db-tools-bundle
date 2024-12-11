<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\Bridge\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use MakinaCorpus\DbToolsBundle\Anonymization\AnonymizatorFactory;
use MakinaCorpus\DbToolsBundle\Anonymization\Anonymizer\AnonymizerRegistry;
use MakinaCorpus\DbToolsBundle\Anonymization\Config\Loader\ArrayLoader;
use MakinaCorpus\DbToolsBundle\Anonymization\Config\Loader\PhpFileLoader;
use MakinaCorpus\DbToolsBundle\Anonymization\Config\Loader\YamlLoader;
use MakinaCorpus\DbToolsBundle\Backupper\BackupperFactory;
use MakinaCorpus\DbToolsBundle\Bridge\Symfony\DependencyInjection\DbToolsConfiguration;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\AnonymizeCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\AnonymizerListCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\CleanCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\ConfigDumpCommand;
use MakinaCorpus\DbToolsBundle\Command\BackupCommand;
use MakinaCorpus\DbToolsBundle\Command\CheckCommand;
use MakinaCorpus\DbToolsBundle\Command\RestoreCommand;
use MakinaCorpus\DbToolsBundle\Command\StatsCommand;
use MakinaCorpus\DbToolsBundle\Configuration\Configuration;
use MakinaCorpus\DbToolsBundle\Configuration\ConfigurationRegistry;
use MakinaCorpus\DbToolsBundle\Database\DatabaseSessionRegistry;
use MakinaCorpus\DbToolsBundle\Error\ConfigurationException;
use MakinaCorpus\DbToolsBundle\Restorer\RestorerFactory;
use MakinaCorpus\DbToolsBundle\Stats\StatsProviderFactory;
use MakinaCorpus\DbToolsBundle\Storage\DefaultFilenameStrategy;
use MakinaCorpus\DbToolsBundle\Storage\FilenameStrategyInterface;
use MakinaCorpus\DbToolsBundle\Storage\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Processor;

class DbToolsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->extend('config', function (Repository $config, Application $app) {
            $dbToolsConfig = $config->get('db-tools', []);
            $dbToolsConfig = ['db_tools' => $dbToolsConfig];

            $processor = new Processor();
            $configuration = new DbToolsConfiguration(false, true);

            $dbToolsConfig = $processor->processConfiguration($configuration, $dbToolsConfig);
            $dbToolsConfig = DbToolsConfiguration::finalizeConfiguration($dbToolsConfig);

            $config->set('db-tools', $dbToolsConfig);

            return $config;
        });

        $this->app->singleton(DatabaseSessionRegistry::class, function (Application $app) {
            return new IlluminateDatabaseSessionRegistry($app->make('db'));
        });

        $this->app->singleton(ConfigurationRegistry::class, function (Application $app) {
            /** @var Repository $config */
            $config = $app->make('config');

            $defaultConfig = new Configuration(
                backupBinary: $config->get('db-tools.backup_binary'),
                backupExcludedTables: $config->get('db-tools.backup_excluded_tables'),
                backupExpirationAge: $config->get('db-tools.backup_expiration_age'),
                backupOptions: $config->get('db-tools.backup_options'),
                backupTimeout: $config->get('db-tools.backup_timeout'),
                restoreBinary: $config->get('db-tools.restore_binary'),
                restoreOptions: $config->get('db-tools.restore_options'),
                restoreTimeout: $config->get('db-tools.restore_timeout'),
                storageDirectory: $config->get('db-tools.storage_directory', $app->storagePath('db_tools')),
                storageFilenameStrategy: $config->get('db-tools.storage_filename_strategy'),
            );

            $connectionConfigs = [];
            foreach ($config->get('db-tools.connections', []) as $name => $data) {
                $connectionConfigs[$name] = new Configuration(
                    backupBinary: $data['backup_binary'] ?? null,
                    backupExcludedTables: $data['backup_excluded_tables'] ?? null,
                    backupExpirationAge: $data['backup_expiration_age'] ?? null,
                    backupOptions: $data['backup_options'] ?? null,
                    backupTimeout: $data['backup_timeout'] ?? null,
                    restoreBinary: $data['restore_binary'] ?? null,
                    restoreOptions: $data['restore_options'] ?? null,
                    restoreTimeout: $data['restore_timeout'] ?? null,
                    parent: $defaultConfig,
                    storageDirectory: $data['storage_directory'] ?? null,
                    storageFilenameStrategy: $data['storage_filename_strategy'] ?? null,
                );
            }

            return new ConfigurationRegistry(
                $defaultConfig,
                $connectionConfigs,
                $config->get('database.default')
            );
        });

        $this->app->singleton(Storage::class, function (Application $app) {
            /** @var ConfigurationRegistry $configRegistry */
            $configRegistry = $app->make(ConfigurationRegistry::class);
            $connections = $configRegistry->getConnectionConfigAll();

            // Register filename strategies.
            $strategies = [];
            foreach ($connections as $connectionName => $connection) {
                $strategyId = $connection->getStorageFilenameStrategy();

                if ($strategyId === null || $strategyId === 'default' || $strategyId === 'datetime') {
                    $strategy = new DefaultFilenameStrategy();
                } elseif ($app->bound($strategyId)) {
                    $strategy = $app->make($strategyId);
                } elseif (\class_exists($strategyId)) {
                    if (!\is_subclass_of($strategyId, FilenameStrategyInterface::class)) {
                        throw new \InvalidArgumentException(\sprintf(
                            '"db-tools.connections.%s.filename_strategy": class "%s" does not implement "%s"',
                            $connectionName,
                            $strategyId,
                            FilenameStrategyInterface::class
                        ));
                    }
                    $strategy = $app->make($strategyId);
                } else {
                    throw new \InvalidArgumentException(\sprintf(
                        '"db-tools.connections.%s.filename_strategy": class or service "%s" does not exist or is not registered in container',
                        $connectionName,
                        $strategyId
                    ));
                }

                $strategies[$connectionName] = $strategy;
            }

            return new Storage($configRegistry, $strategies);
        });

        $this->app->resolving(
            AnonymizatorFactory::class,
            function (AnonymizatorFactory $factory, Application $app): void {
                /** @var Repository $config */
                $config = $app->make('config');

                foreach ($config->get('db-tools.anonymization_files', []) as $connectionName => $file) {
                    // 0 is not a good index for extension, this fails for false and 0.
                    if (!($pos = \strrpos($file, '.'))) {
                        throw new ConfigurationException(\sprintf(
                            "File extension cannot be guessed for \"%s\" file path.",
                            $file
                        ));
                    }

                    $ext = \substr($file, $pos + 1);
                    $loader = match ($ext) {
                        'php' => new PhpFileLoader($file, $connectionName),
                        'yml', 'yaml' => new YamlLoader($file, $connectionName),
                        default => throw new ConfigurationException(\sprintf(
                            "File extension \"%s\" is unsupported (given path: \"%s\").",
                            $ext,
                            $file
                        )),
                    };

                    $factory->addConfigurationLoader($loader);
                }

                foreach ($config->get('db-tools.anonymization', []) as $connectionName => $array) {
                    $factory->addConfigurationLoader(new ArrayLoader($array, $connectionName));
                }
            }
        );

        $this->app->singleton(AnonymizerRegistry::class, function (Application $app) {
            /** @var Repository $config */
            $config = $app->make('config');

            // Validate user-given anonymizer paths.
            $anonymizerPaths = $config->get('db-tools.anonymizer_paths');
            foreach ($anonymizerPaths as $path) {
                if (!\is_dir($path)) {
                    throw new \InvalidArgumentException(\sprintf(
                        '"db_tools.anonymizer_paths": path "%s" does not exist',
                        $path
                    ));
                }
            }

            // Set the default anonymizer directory only if the folder
            // exists in order to avoid "directory does not exist" errors.
            $defaultDirectory = $app->basePath('app/Anonymizer');
            if (\is_dir($defaultDirectory)) {
                $anonymizerPaths[] = $defaultDirectory;
            }

            return new AnonymizerRegistry($anonymizerPaths);
        });

        $this->app->singleton(BackupperFactory::class, function (Application $app) {
            return new BackupperFactory(
                $app->make(DatabaseSessionRegistry::class),
                $app->make(ConfigurationRegistry::class),
                $app->make(LoggerInterface::class),
            );
        });

        $this->app->singleton(RestorerFactory::class, function (Application $app) {
            return new RestorerFactory(
                $app->make(DatabaseSessionRegistry::class),
                $app->make(ConfigurationRegistry::class),
                $app->make(LoggerInterface::class),
            );
        });

        $this->app->singleton(StatsProviderFactory::class, function (Application $app) {
            return new StatsProviderFactory($app->make(DatabaseSessionRegistry::class));
        });

        // Inject the default database connection name to services
        // which require it.
        $this->app
            ->when([
                AnonymizeCommand::class,
                BackupCommand::class,
                RestoreCommand::class,
            ])
            ->needs('$connectionName')
            ->giveConfig('database.default')
        ;
        $this->app
            ->when([
                CheckCommand::class,
                CleanCommand::class,
                StatsCommand::class,
            ])
            ->needs('$defaultConnectionName')
            ->giveConfig('database.default')
        ;
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        AboutCommand::add('DbToolsBundle', fn () => ['Version' => '2.0.0']);

        $this->publishes([
            __DIR__ . '/Resources/config/db-tools.php' => $this->app->configPath('db-tools.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AnonymizeCommand::class,
                AnonymizerListCommand::class,
                BackupCommand::class,
                CheckCommand::class,
                CleanCommand::class,
                ConfigDumpCommand::class,
                RestoreCommand::class,
                StatsCommand::class,
            ]);
        }
    }
}
