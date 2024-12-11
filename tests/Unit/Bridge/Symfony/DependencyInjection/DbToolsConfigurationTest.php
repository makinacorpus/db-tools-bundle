<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\Tests\Unit\Bridge\Symfony\DependencyInjection;

use MakinaCorpus\DbToolsBundle\Bridge\Symfony\DependencyInjection\DbToolsConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

class DbToolsConfigurationTest extends TestCase
{
    private function processYamlConfiguration(array|string $dataOrFilename): array
    {
        $processor = new Processor();
        $configuration = new DbToolsConfiguration(true, true);

        $config = $processor->processConfiguration(
            $configuration,
            \is_string($dataOrFilename) ? Yaml::parseFile($dataOrFilename) : ['db_tools' => $dataOrFilename],
        );

        return DbToolsConfiguration::finalizeConfiguration($config);
    }

    public function testConfigurationEmpty(): array
    {
        $result = $this->processYamlConfiguration(
            \dirname(__DIR__, 4) . '/Resources/config/packages/db_tools_empty.yaml'
        );

        self::assertEquals(
            [
                'anonymization' => [],
                'anonymization_files' => [],
                'anonymizer_paths' => [],
                'backup_binary' => null,
                'backup_excluded_tables' => [],
                'backup_options' => null,
                'connections' => [],
                'default_connection' => null,
                'restore_binary' => null,
                'restore_options' => null,
                'storage_directory' => null,
                'storage_filename_strategy' => null,
                'workdir' => null,
            ],
            $result,
        );

        return $result;
    }

    public function testConfigurationFull(): array
    {
        $result = $this->processYamlConfiguration(
            \dirname(__DIR__, 4) . '/Resources/config/packages/db_tools_full.yaml'
        );

        self::assertEquals(
            [
                'anonymization' => [
                    'connection_one' => [
                        'user' => [
                            'last_name' => 'fr-fr.firstname',
                            'email' => [
                                'anonymizer' => 'email',
                                'options' => [
                                    'domain' => 'toto.com',
                                ],
                            ],
                        ],
                    ],
                ],
                'anonymization_files' => [
                    'connection_one' => 'connection_one.yaml',
                    'connection_two' => 'connection_two.yaml',
                ],
                'anonymizer_paths' => [
                    './',
                ],
                'backup_binary' => '/path/to/dump',
                'backup_excluded_tables' => ['table1', 'table2'],
                'backup_expiration_age' => '2 minutes ago',
                'backup_options' => '--dump',
                'backup_timeout' => 135,
                'connections' => [
                    'connection_one' => [
                        'backup_binary' => '/path/to/dump/one',
                        'backup_excluded_tables' => ['one1'],
                        'backup_expiration_age' => '1 minutes ago',
                        'backup_options' => '--dump-one',
                        'backup_timeout' => 11,
                        'restore_binary' => '/paht/to/restore/one',
                        'restore_options' => '--restore-one',
                        'restore_timeout' => 23,
                        'storage_directory' => '/one/storage',
                        'storage_filename_strategy' => 'one_strategy',
                        'url' => null,
                    ],
                ],
                'default_connection' => 'connection_one',
                'restore_binary' => '/path/to/restore',
                'restore_options' => '--restore',
                'restore_timeout' => 357,
                'storage_directory' => '%kernel.project_dir%/var/db_tools',
                'storage_filename_strategy' => 'datetime',
                'workdir' => null, // '/path/to',
            ],
            $result,
        );

        return $result;
    }

    public function testConfigurationConnectionsPartial(): array
    {
        $result = $this->processYamlConfiguration(
            \dirname(__DIR__, 4) . '/Resources/config/packages/db_tools_connections_partial.yaml'
        );

        self::assertEquals(
            [
                'anonymization' => [],
                'anonymization_files' => [],
                'anonymizer_paths' => [],
                'backup_binary' => '/path/to/dump',
                'backup_excluded_tables' => ['table1', 'table2'],
                'backup_expiration_age' => '2 minutes ago',
                'backup_options' => '--dump',
                'backup_timeout' => 135,
                'connections' => [
                    'connection_one' => [
                        'backup_binary' => null,
                        'backup_excluded_tables' => ['one1'],
                        'backup_expiration_age' => '1 minutes ago',
                        'backup_options' => null,
                        //'backup_timeout' => null,
                        'restore_binary' => null,
                        'restore_options' => null,
                        'restore_timeout' => 23,
                        'storage_directory' => '/one/storage',
                        'storage_filename_strategy' => 'one_strategy',
                        'url' => null,
                    ],
                ],
                'default_connection' => 'connection_one',
                'restore_binary' => '/path/to/restore',
                'restore_options' => '--restore',
                'restore_timeout' => 357,
                'storage_directory' => '%kernel.project_dir%/var/db_tools',
                'storage_filename_strategy' => 'datetime',
                'workdir' => null, // '/path/to',
            ],
            $result,
        );

        return $result;
    }

    public function testDeprecatedV2(): void
    {
        $result = $this->processYamlConfiguration(
            \dirname(__DIR__, 4) . '/Resources/config/packages/db_tools_deprecated_v2.yaml'
        );

        self::assertEquals(
            [
                'anonymization' => [],
                'anonymization_files' => [
                    'connection_one' => 'connection_one.yaml',
                    'connection_two' => 'connection_two.yaml',
                ],
                'anonymizer_paths' => [],
                'backup_binary' => null,
                'backup_excluded_tables' => [],
                'backup_options' => null,
                'connections' => [
                    'connection_one' => [
                        'backup_excluded_tables' => ['one1', 'one2'],
                        'storage_filename_strategy' => 'some_strategy',
                    ],
                    'connection_two' => [
                        'backup_excluded_tables' => ['two1', 'two2', 'two3'],
                    ],
                ],
                'default_connection' => null,
                'restore_binary' => null,
                'restore_options' => null,
                'storage_directory' => '/foo/bar',
                'storage_filename_strategy' => null,
                'workdir' => null,
            ],
            $result,
        );
    }

    public function testDeprecatedV2Conflict(): void
    {
        self::expectExceptionMessage('Deprecated option "excluded_tables.connection_one" and actual option "connections.connection_one.backup_excluded_tables" are both defined, please fix your configuration.');

        $this->processYamlConfiguration(
            \dirname(__DIR__, 4) . '/Resources/config/packages/db_tools_deprecated_v2_conflict.yaml'
        );
    }

    public function testConfigurationBackupTimeoutInt(): void
    {
        $result = $this->processYamlConfiguration([
            'backup_timeout' => 123,
        ]);

        self::assertSame(123, $result['backup_timeout']);
    }

    public function testConfigurationBackupTimeoutIntervalString(): void
    {
        $result = $this->processYamlConfiguration([
            'backup_timeout' => '1 minute 2 seconds',
        ]);

        self::assertSame(62, $result['backup_timeout']);
    }

    public function testConfigurationBackupTimeoutInvalid(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $this->processYamlConfiguration([
            'backup_timeout' => "this is not parsable",
        ]);
    }

    public function testConfigurationRestoreTimeoutInt(): void
    {
        $result = $this->processYamlConfiguration([
            'restore_timeout' => 123,
        ]);

        self::assertSame(123, $result['restore_timeout']);
    }

    public function testConfigurationRestoreTimeoutIntervalString(): void
    {
        $result = $this->processYamlConfiguration([
            'restore_timeout' => '1 minute 2 seconds',
        ]);

        self::assertSame(62, $result['restore_timeout']);
    }

    public function testConfigurationRestoreTimeoutInvalid(): void
    {
        $result = $this->processYamlConfiguration([
            'restore_timeout' => 123,
        ]);

        self::assertSame(123, $result['restore_timeout']);
    }
}
