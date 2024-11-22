<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\Bridge\Standalone;

use Composer\InstalledVersions;
use MakinaCorpus\DbToolsBundle\Anonymization\AnonymizatorFactory;
use MakinaCorpus\DbToolsBundle\Anonymization\Anonymizer\AnonymizerRegistry;
use MakinaCorpus\DbToolsBundle\Anonymization\Config\Loader\ArrayLoader;
use MakinaCorpus\DbToolsBundle\Anonymization\Config\Loader\YamlLoader;
use MakinaCorpus\DbToolsBundle\Backupper\BackupperFactory;
use MakinaCorpus\DbToolsBundle\Bridge\Symfony\DependencyInjection\DbToolsConfiguration;
use MakinaCorpus\DbToolsBundle\Command\BackupCommand;
use MakinaCorpus\DbToolsBundle\Command\CheckCommand;
use MakinaCorpus\DbToolsBundle\Command\RestoreCommand;
use MakinaCorpus\DbToolsBundle\Command\StatsCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\AnonymizeCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\AnonymizerListCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\CleanCommand;
use MakinaCorpus\DbToolsBundle\Command\Anonymization\ConfigDumpCommand;
use MakinaCorpus\DbToolsBundle\Configuration\Configuration;
use MakinaCorpus\DbToolsBundle\Configuration\ConfigurationRegistry;
use MakinaCorpus\DbToolsBundle\Database\DatabaseSessionRegistry;
use MakinaCorpus\DbToolsBundle\Error\ConfigurationException;
use MakinaCorpus\DbToolsBundle\Restorer\RestorerFactory;
use MakinaCorpus\DbToolsBundle\Stats\StatsProviderFactory;
use MakinaCorpus\DbToolsBundle\Storage\Storage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Yaml\Yaml;

/**
 * Creates Symfony console application.
 *
 * @internal
 * @see ../../bin/db-tools.php
 */
class Bootstrap
{
    /**
     * Create and run Symfony console application.
     */
    public static function run(): void
    {
        static::createApplication()->run();
    }

    /**
     * Create Symfony console application.
     */
    public static function createApplication(): Application
    {
        if (\class_exists(InstalledVersions::class)) {
            $version = InstalledVersions::getVersion('makinacorpus/db-tools-bundle');
        }
        $version ??= 'cli';

        $application = new Application('DbTools', $version);
        $application->setCatchExceptions(true);
        $application->setDefaultCommand('list');

        $definition = $application->getDefinition();
        $definition->addOption(new InputOption('config', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Configuration files', null));
        $definition->addOption(new InputOption('env', null, InputOption::VALUE_REQUIRED, 'Environment', 'dev'));

        // Hack, we need it output to have the same configuration as the application.
        $input = new ArgvInput();
        $output = new ConsoleOutput();
        (\Closure::bind(fn () => $application->configureIO($input, $output), null, Application::class))();

        // We need to parse a few arguments prior running the console
        // application in order to setup commands. This is hackish but
        // should work.
        // @todo This does not work, fix it.
        $config = $configFiles = [];
        if ($input->hasOption('config')) {
            foreach ((array) $input->getOption('config') as $filename) {
                if ($output->isVerbose()) {
                    $output->writeln('Using configuration file: ' . $filename);
                }
                $configFiles[] = $filename;
            }
        }

        $commands = [
            'anonymization:clean' => [
                fn (Context $context) => new CleanCommand(
                    anonymizatorFactory: $context->anonymizatorFactory,
                    defaultConnectionName: $context->databaseSessionRegistry->getDefaultConnectionName(),
                ),
                'Clean DbTools left-over temporary tables',
                ['clean'],
            ],
            'anonymization:config-dump' => [
                fn (Context $context) => new ConfigDumpCommand(
                    anonymizatorFactory: $context->anonymizatorFactory,
                ),
                'Dump anonymization configuration',
                ['config-dump'],
            ],
            'anonymization:list' => [
                fn (Context $context) => new AnonymizerListCommand(
                    anonymizerRegistry: $context->anonymizerRegistry,
                ),
                'List all available anonymizers',
                [],
            ],
            'anonymization:run' => [
                fn (Context $context) => new AnonymizeCommand(
                    connectionName: $context->databaseSessionRegistry->getDefaultConnectionName(),
                    restorerFactory: $context->restorerFactory,
                    backupperFactory: $context->backupperFactory,
                    anonymizatorFactory: $context->anonymizatorFactory,
                    storage: $context->storage,
                ),
                'Anonymize given backup file or the local database',
                ['anonymize'],
            ],
            'database:backup' => [
                fn (Context $context) => new BackupCommand(
                    connectionName: $context->databaseSessionRegistry->getDefaultConnectionName(),
                    backupperFactory: $context->backupperFactory,
                    storage: $context->storage,
                ),
                'Backup database',
                ['backup'],
            ],
            'database:check' => [
                fn (Context $context) => new CheckCommand(
                    defaultConnectionName: $context->databaseSessionRegistry->getDefaultConnectionName(),
                    backupperFactory: $context->backupperFactory,
                    restorerFactory: $context->restorerFactory,
                ),
                'Check backup and restore binaries',
                ['check'],
            ],
            'database:restore' => [
                fn (Context $context) => new RestoreCommand(
                    connectionName: $context->databaseSessionRegistry->getDefaultConnectionName(),
                    restorerFactory: $context->restorerFactory,
                    storage: $context->storage,
                ),
                'Restore database.',
                ['restore'],
            ],
            'database:stats' => [
                fn (Context $context) => new StatsCommand(
                    defaultConnectionName: $context->databaseSessionRegistry->getDefaultConnectionName(),
                    statsProviderFactory: $context->statsProviderFactory,
                ),
                'Give some database statistics',
                ['stats'],
            ],
        ];

        $initializer = static fn (): Context => self::bootstrap($config, $configFiles, new ConsoleLogger($output));

        // All commands are wrapped into LazyCommand instances, we do not
        // really care about performances here, we have really few commands
        // and it's OK to initialize them all, but we need to change their
        // name to shorten them.
        foreach ($commands as $name => $data) {
            list($callback, $description, $aliases) = $data;
            $application->add(
                new LazyCommand(
                    name: $name,
                    aliases: $aliases,
                    description: $description,
                    isHidden: false,
                    commandFactory: fn () => $callback($initializer()),
                ),
            );
        }

        return $application;
    }

    /**
     * Bootstrap components as a standalone application.
     *
     * @param array<string,mixed> $config
     *   Configuration parsed from application bootstrap using CLI options.
     *   This configuration must match the Symfony configuration file without
     *   the "db_tools" root level.
     * @param array<string> $configFiles
     *   Additional configuration files to parse.
     */
    public static function bootstrap(array $config = [], array $configFiles = [], ?LoggerInterface $logger = null): Context
    {
        $logger ??= new NullLogger();
        $config = self::configParse($config, $configFiles, $logger);

        $default = new Configuration(
            backupBinary: $config['backup_binary'] ?? null,
            backupExcludedTables: $config['backup_excluded_tables'] ?? null,
            backupExpirationAge: $config['backup_expiration_age'] ?? null,
            backupOptions: $config['backup_options'] ?? null,
            backupTimeout: $config['backup_timeout'] ?? null,
            restoreBinary: $config['restore_binary'] ?? null,
            restoreOptions: $config['restore_options'] ?? null,
            restoreTimeout: $config['restore_timeout'] ?? null,
            storageDirectory: $config['storage_directory'] ?? null,
            storageFilenameStrategy: $config['storage_filename_strategy'] ?? null,
        );

        $connections = [];
        foreach (($config['connections'] ?? []) as $name => $data) {
            $connections[$name] = new Configuration(
                backupBinary: $data['backup_binary'] ?? null,
                backupExcludedTables: $data['backup_excluded_tables'] ?? null,
                backupExpirationAge: $data['backup_expiration_age'] ?? null,
                backupOptions: $data['backup_options'] ?? null,
                backupTimeout: $data['backup_timeout'] ?? null,
                restoreBinary: $data['restore_binary'] ?? null,
                restoreOptions: $data['restore_options'] ?? null,
                restoreTimeout: $data['restore_timeout'] ?? null,
                parent: $default,
                storageDirectory: $data['storage_directory'] ?? null,
                storageFilenameStrategy: $data['storage_filename_strategy'] ?? null,
                url: $data['url'] ?? null,
            );
        }

        $configRegistry = new ConfigurationRegistry($default, $connections, $config['default_connection'] ?? null);

        $databaseSessionRegistry = self::createDatabaseSessionRegistry($configRegistry);

        $anonymizerRegistry = self::createAnonymizerRegistry($config);
        $anonymizatorFactory = new AnonymizatorFactory($databaseSessionRegistry, $anonymizerRegistry, $logger);

        foreach (($config['anonymization_files'] ?? []) as $connectionName => $file) {
            $anonymizatorFactory->addConfigurationLoader(new YamlLoader($file, $connectionName));
        }
        foreach (($config['anonymization'] ?? []) as $connectionName => $array) {
            $anonymizatorFactory->addConfigurationLoader(new ArrayLoader($array, $connectionName));
        }

        $backupperFactory = new BackupperFactory($databaseSessionRegistry, $configRegistry, $logger);
        $restorerFactory = new RestorerFactory($databaseSessionRegistry, $configRegistry, $logger);

        $statsProviderFactory = new StatsProviderFactory($databaseSessionRegistry);
        $storage = self::createStorage($configRegistry, $logger);

        return new Context(
            anonymizatorFactory: $anonymizatorFactory,
            anonymizerRegistry: $anonymizerRegistry,
            backupperFactory: $backupperFactory,
            databaseSessionRegistry: $databaseSessionRegistry,
            logger: $logger,
            restorerFactory: $restorerFactory,
            statsProviderFactory: $statsProviderFactory,
            storage: $storage,
        );
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     */
    private static function getProjectDir(LoggerInterface $logger): ?string
    {
        // 4 level of \dirname() gets us in this project parent folder.
        $candidates = [\getcwd(), \dirname(__DIR__, 4)];

        foreach ($candidates as $candidate) {
            $dir = $candidate;
            while ($dir) {
                if (\is_file($dir.'/composer.json')) {
                    $logger->notice('Project root directory found: {dir}', ['dir' => $dir]);

                    return $dir;
                }
                $logger->debug('Not found project directory: {dir}', ['dir' => $dir]);
                $dir = \dirname($dir);
            }
        }
        return null;
    }

    /**
     * Parse configuration files, and environment provided configuration.
     *
     * @param array $config
     *   Overriding configuration from user input. It will overide configuration
     *   from given files.
     * @param array $files
     *   Configuration files, in override order in case of conflict.
     *
     * @return array
     *   Merged proper configuration.
     */
    private static function configParse(array $config, array $files, LoggerInterface $logger): array
    {
        $projectRoot = self::getProjectDir($logger);
        $workdir = $config['workdir'] ?? $projectRoot ?? \getcwd();

        // When no configuration file given, attempt to find one.
        if (empty($files)) {
            $configFileNames = ['db_tools.yaml', 'db_tools.yml', 'db_tools.config.yaml', 'db_tools.config.yml'];

            $candidates = [];
            if ($projectRoot) {
                foreach ($configFileNames as $filename) {
                    $candidates[] = self::pathConcat($projectRoot, $filename);
                }
            }
            if ($projectRoot !== $workdir) {
                foreach ($configFileNames as $filename) {
                    $candidates[] = self::pathConcat($workdir, $filename);
                }
            }
            // Will not work under Windows (and that's OK).
            if ($homedir = \getenv("HOME")) {
                // @todo .config folder is configurable with XDG portals?
                $candidates[] = self::pathConcat($homedir, '/.config/db_tools/config.yaml');
                $candidates[] = self::pathConcat($homedir, '/.config/db_tools/config.yml');
                // As dot files.
                foreach ($configFileNames as $filename) {
                    $candidates[] = self::pathConcat($homedir, '.' . $filename);
                }
            }

            // Stop at first file found, but continue unrolling all candidates
            // and emit debug output: this may help people knowing what happens
            // in case of any problems when running the CLI using the -vvv
            // option.
            $found = false;
            foreach ($candidates as $filename) {
                if ($found) {
                    $logger->debug("Earlier configuration file found, skipping file: {file}", ['file' => $filename]);
                } elseif (\file_exists($filename)) {
                    if (\is_readable($filename)) {
                        $logger->notice("Found configuration file: {file}", ['file' => $filename]);
                        $files[] = $filename;
                        $found = true;
                    } else {
                        $logger->warning("Configuration file could not be read: {file}", ['file' => $filename]);
                    }
                } else {
                    $logger->debug("Configuration file does not exist: {file}", ['file' => $filename]);
                }
            }
        }

        $configs = [];
        foreach ($files as $filename) {
            $configs[] = self::configParseFile($filename);
        }
        $configs[] = $config;

        $config = self::configGetEnv($config);

        // Use symfony/config and our bundle configuration, which allows us
        // to use it fully for validation and merge.
        $configuration = new DbToolsConfiguration(true, true);
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, $configs);
        $config = DbToolsConfiguration::finalizeConfiguration($config);

        return $config;
    }

    /**
     * Parse a single configuration file.
     */
    private static function configParseFile(string $filename): array
    {
        if (!\file_exists($filename)) {
            throw new ConfigurationException(\sprintf("%s: file does not exist.", $filename));
        }
        if (!\is_readable($filename)) {
            throw new ConfigurationException(\sprintf("%s: file cannot be read.", $filename));
        }

        // 0 is not a good index for extension, this fails for false and 0.
        if (!($pos = \strrpos($filename, '.'))) {
            throw new ConfigurationException(\sprintf("%s: file extension cannot be guessed.", $filename));
        }
        $ext = \substr($filename, $pos + 1);

        $config = match ($ext) {
            'yml', 'yaml' => Yaml::parseFile($filename),
            default => throw new ConfigurationException(\sprintf("%s: file extension '%s' is unsupported.", $filename, $ext)),
        };

        // Resolve all known filenames relative to this file.
        // @todo Warning, this code will only work on UNIX-like filesystems.
        $workdir = \rtrim($config['workdir'] ?? \dirname($filename), '/');

        // Storage root directory.
        if ($path = ($config['storage_directory'] ?? null)) {
            $config['storage_directory'] = self::pathAbs($workdir, $path);
        }

        // YAML anonymizer file paths.
        $yaml = $config['anonymization_files'] ?? null;
        if (isset($yaml)) {
            if (\is_array($yaml)) {
                foreach ($yaml as $name => $path) {
                    $config['anonymization_files'][$name] = self::pathAbs($workdir, $path);
                }
            } else {
                $config['anonymization_files'] = self::pathAbs($workdir, $yaml);
            }
        }

        // Custom anonymizer paths.
        foreach (($config['anonymizer_paths'] ?? []) as $index => $path) {
            $config['anonymizer_paths'][$index] = self::pathAbs($workdir, $path);
        }

        return $config;
    }

    /**
     * Get config variables from environment variables.
     */
    private static function configGetEnv(array $config): array
    {
        if (!isset($config['backup_binary'])) {
            $config['backup_binary'] = self::getEnv('DBTOOLS_BACKUP_BINARY');
        }
        if (!isset($config['backup_excluded_tables'])) {
            $config['backup_excluded_tables'] = self::getEnv('DBTOOLS_BACKUP_EXCLUDED_TABLES');
        }
        if (!isset($config['backup_expiration_age'])) {
            $config['backup_expiration_age'] = self::getEnv('DBTOOLS_BACKUP_EXPIRATION_AGE');
        }
        if (!isset($config['backup_options'])) {
            $config['backup_options'] = self::getEnv('DBTOOLS_BACKUP_OPTIONS');
        }
        if (!isset($config['backup_timeout'])) {
            $config['backup_timeout'] = self::getEnv('DBTOOLS_BACKUP_TIMEOUT');
        }
        if (!isset($config['default_connection'])) {
            $config['default_connection'] = self::getEnv('DBTOOLS_DEFAULT_CONNECTION');
        }
        if (!isset($config['restore_binary'])) {
            $config['restore_binary'] = self::getEnv('DBTOOLS_RESTORE_BINARY');
        }
        if (!isset($config['restore_options'])) {
            $config['restore_options'] = self::getEnv('DBTOOLS_RESTORE_OPTIONS');
        }
        if (!isset($config['restore_timeout'])) {
            $config['restore_timeout'] = self::getEnv('DBTOOLS_RESTORE_TIMEOUT');
        }
        if (!isset($config['storage_directory'])) {
            $config['storage_directory'] = self::getEnv('DBTOOLS_STORAGE_DIRECTORY');
        }
        if (!isset($config['storage_filename_strategy'])) {
            $config['storage_filename_strategy'] = self::getEnv('DBTOOLS_STORAGE_FILENAME_STRATEGY');
        }
        return $config;
    }

    private static function getEnv(string $name): string|null
    {
        $value = \getenv($name);

        return (!$value && $value !== '0') ? null : (string) $value;
    }

    /**
     * Create anonymizer registry and register custom code and additional packs.
     */
    private static function createAnonymizerRegistry(array $config): AnonymizerRegistry
    {
        return new AnonymizerRegistry($config['anonymizer_paths']);
    }

    /**
     * Create database session registry from config-given connections.
     */
    private static function createDatabaseSessionRegistry(ConfigurationRegistry $configRegistry): DatabaseSessionRegistry
    {
        $connections = [];
        foreach ($configRegistry->getConnectionConfigAll() as $name => $config) {
            \assert($config instanceof Configuration);
            $connections[$name] = $config->getUrl() ?? throw new ConfigurationException(\sprintf('Connection "%s" is missing the "url" option.', $name));
        }

        // Do not crash on initialization, it will crash later when a connection
        // will be request instead: this allows commands that don't act on
        // database (such as anonymizer list) to work even if not configured.
        return new StandaloneDatabaseSessionRegistry($connections, $configRegistry->getDefaultConnection());
    }

    /**
     * Create storage.
     */
    private static function createStorage(ConfigurationRegistry $configRegistry, LoggerInterface $logger): Storage
    {
        $rootdir = $configRegistry->getDefaultConfig()->getStorageDirectory();

        if (!\is_dir($rootdir)) {
            if (\file_exists($rootdir)) {
                throw new ConfigurationException(\sprintf("Storage root folder is a regular file instead of a directory: %s", $rootdir));
            }

            $logger->notice("Storage root folder does not exists: {dir}", ['dir' => $rootdir]);
        } else {
            $logger->notice("Found storage root folder: {dir}", ['dir' => $rootdir]);
        }

        return new Storage($configRegistry);
    }

    /**
     * Concat and make absolute using given root if relative.
     */
    private static function pathAbs(string $root, string ...$pieces): string
    {
        $path = self::pathConcat(...$pieces);
        if (\str_starts_with($path, '/')) {
            return $path;
        }
        return self::pathConcat($root, $path);
    }

    /**
     * Concat all path segment while cleaning a bit given input.
     */
    private static function pathConcat(string ...$pieces): string
    {
        $first = true;
        foreach ($pieces as $index => $piece) {
            if ($first) {
                $first = false;
                $pieces[$index] = \rtrim($piece, '/\\');
            } else {
                if (\str_starts_with($piece, './')) {
                    $piece = \substr($piece, 2);
                }
                $pieces[$index] = \trim($piece, '/\\');
            }
        }
        return \implode(DIRECTORY_SEPARATOR, $pieces);
    }
}
