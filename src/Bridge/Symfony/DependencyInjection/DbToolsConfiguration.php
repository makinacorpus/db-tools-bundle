<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class DbToolsConfiguration implements ConfigurationInterface
{
    public function __construct(
        protected readonly bool $standalone = false,
        protected readonly bool $withDeprecated = false,
    ) {}

    /**
     * Apply some final modifications to the configuration after it has been
     * processed, such as append values we cannot set as default values, or
     * fix legacy option names for backwards compatibility.
     *
     * Callable outside the Symfony extension context to apply the same
     * modifications to configurations from other contexts (standalone,
     * Laravel, etc.).
     */
    public static function finalizeConfiguration(array $config): array
    {
        $config = self::fixLegacyOptions($config);

        return $config;
    }

    /**
     * From a normalized configuration array, fixes legacy options names,
     * and raise errors when duplicates are found.
     *
     * @internal
     * @todo Alter in 3.x accordingly to option removal.
     */
    private static function fixLegacyOptions(array $config): array
    {
        if (isset($config['storage']['root_dir'])) {
            \trigger_deprecation('makinacorpus/db-tools-bundle', '2.0.0', '"db_tools.storage.root_dir" configuration option is deprecated and renamed "db_tools.storage_directory"');
            if (isset($config['storage_directory'])) {
                throw throw new \InvalidArgumentException('Deprecated option "storage.root_dir" and actual option "storage_directory" are both defined, please fix your configuration.');
            }
            $config['storage_directory'] = $config['storage']['root_dir'];
        }
        if (isset($config['storage']['filename_strategy'])) {
            \trigger_deprecation('makinacorpus/db-tools-bundle', '2.0.0', '"db_tools.storage.filename_strategy" configuration option is deprecated and renamed "db_tools.storage_filename_strategy"');
            foreach ($config['storage']['filename_strategy'] as $connection => $strategy) {
                \trigger_deprecation('makinacorpus/db-tools-bundle', '2.0.0', '"storage.filename_strategy.%s" configuration option is deprecated and renamed "connections.%s.storage_filename_strategy"', $connection, $connection);
                if (isset($config['connections'][$connection]['storage_filename_strategy'])) {
                    throw throw new \InvalidArgumentException(\sprintf('Deprecated option "storage.filename_strategy.%s" and actual option "connections.%s.storage_filename_strategy" are both defined, please fix your configuration.', $connection, $connection));
                }
                $config['connections'][$connection]['storage_filename_strategy'] = $strategy;
            }
        }
        unset($config['storage']);

        if (isset($config['excluded_tables'])) {
            foreach ($config['excluded_tables'] as $connection => $tables) {
                \trigger_deprecation('makinacorpus/db-tools-bundle', '2.0.0', '"excluded_tables.%s" configuration option is deprecated and renamed "connections.%s.backup_excluded_tables"', $connection, $connection);
                if (isset($config['connections'][$connection]['backup_excluded_tables'])) {
                    throw throw new \InvalidArgumentException(\sprintf('Deprecated option "excluded_tables.%s" and actual option "connections.%s.backup_excluded_tables" are both defined, please fix your configuration.', $connection, $connection));
                }
                $config['connections'][$connection]['backup_excluded_tables'] = $tables;
            }
        }
        unset($config['excluded_tables']);

        if (isset($config['anonymization']['yaml'])) {
            \trigger_deprecation('makinacorpus/db-tools-bundle', '2.0.0', '"db_tools.anonymization.yaml" configuration option is deprecated and renamed "anonymization_files"');
            $config['anonymization_files'] = $config['anonymization']['yaml'];
        }
        unset($config['anonymization']['yaml']);

        return $config;
    }

    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('db_tools');
        $rootNode = $treeBuilder->getRootNode();
        // For PHPStan.
        \assert($rootNode instanceof ArrayNodeDefinition);

        // @phpstan-ignore-next-line
        $rootNode
            ->children()
                ->arrayNode('anonymization')
                    ->useAttributeAsKey('connection')
                    ->variablePrototype()
                        ->info('Keys are table names, values are arrays whose keys are column names and values are anonymizer configurations.')
                    ->end()
                ->end()
                ->arrayNode('anonymization_files')
                    ->useAttributeAsKey('connection')
                    ->beforeNormalization()->ifString()->then(function ($v) { return ['default' => $v]; })->end()
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('anonymizer_paths')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        // Add defaults.
        $this->addConnectionConfigTreeBuilder($rootNode);

        // Add "connections" children definition.
        // @phpstan-ignore-next-line
        $connectionsNode = $rootNode
            ->children()
                ->arrayNode('connections')
                    ->beforeNormalization()->ifString()->then(function ($v) { return ['default' => $v]; })->end()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->beforeNormalization()->ifString()->then(function ($v) { return ['url' => $v]; })->end()
                        ->children()
                            ->scalarNode('url')
                                ->defaultNull()
                            ->end()
                        ->end()
                        // Do not close arrayNode() we use it below.
        ;
        \assert($connectionsNode instanceof ArrayNodeDefinition);
        $this->addConnectionConfigTreeBuilder($connectionsNode);

        if ($this->standalone) {
            // Add extra options for standalone CLI app.
            // @phpstan-ignore-next-line
            $rootNode
                ->children()
                    ->scalarNode('workdir')
                        ->info('Directory path all other files will be relative to, if none provided then the configuration file directory will be used instead.')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('default_connection')
                        ->info('Default connection name. If none provided, first one is used instead.')
                        ->defaultNull()
                    ->end()
                ->end()
            ;
        }

        if ($this->withDeprecated) {
            // Add deprecated options.
            // @phpstan-ignore-next-line
            $rootNode
                ->children()
                    ->arrayNode('storage')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('root_dir')
                                ->setDeprecated('makinacorpus/db-tools-bundle', '2.0.0', 'Please use "storage_root_dir" instead.')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('filename_strategy')
                                ->setDeprecated('makinacorpus/db-tools-bundle', '2.0.0', 'Please use "storage_filename_strategy" instead.')
                                ->beforeNormalization()->ifString()->then(function ($v) { return ['default' => $v]; })->end()
                                ->useAttributeAsKey('connection')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    // @todo Remove in 3.x
                    ->arrayNode('excluded_tables')
                        ->setDeprecated('makinacorpus/db-tools-bundle', '2.0.0', 'Please use "db_tools.backup_excluded_tables" instead.')
                        ->beforeNormalization()->always(function ($v) { return \array_is_list($v) ? ['default' => $v] : $v; })->end()
                        ->useAttributeAsKey('connection')
                        ->arrayPrototype()
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ;
        }

        return $treeBuilder;
    }

    /**
     * Common options for connections and top-level default configuration.
     */
    protected function addConnectionConfigTreeBuilder(ArrayNodeDefinition $node): void
    {
        $intervalToInt = $this->getIntervalToInt();

        // @phpstan-ignore-next-line
        $node
            ->children()
                ->scalarNode('backup_binary')
                    ->defaultNull()
                ->end()
                ->arrayNode('backup_excluded_tables')
                    ->beforeNormalization()->always(function ($v) { return \is_array($v) ? $v : [$v]; })->end()
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('backup_expiration_age')->end()
                ->scalarNode('backup_options')
                    ->defaultNull()
                ->end()
                ->scalarNode('backup_timeout')
                    ->beforeNormalization()->always($intervalToInt)->end()
                ->end()
                ->scalarNode('restore_binary')
                    ->defaultNull()
                ->end()
                ->scalarNode('restore_options')
                    ->defaultNull()
                ->end()
                ->scalarNode('restore_timeout')
                    ->beforeNormalization()->always($intervalToInt)->end()
                ->end()
                ->scalarNode('storage_directory')
                    ->defaultNull()
                ->end()
                ->scalarNode('storage_filename_strategy')
                    ->defaultNull()
                ->end()
            ->end()
        ;
    }

    /**
     * Convert any value to an interval as a number of second.
     */
    protected function getIntervalToInt(): callable
    {
        return function (mixed $v): null|int {
            if (null === $v) {
                return $v;
            }
            if (\is_string($v)) {
                if (\ctype_digit($v)) {
                    return (int) $v;
                }
                try {
                    if (false !== ($i = @\DateInterval::createFromDateString($v))) {
                        return $i->days * 86400 + $i->h * 3600 + $i->i * 60 + $i->s;
                    }
                } catch (\DateMalformedIntervalStringException) {
                    // Pass, invalid format.
                }
                throw throw new \InvalidArgumentException(\sprintf("Given value '%s' is not an int and cannot be parsed as a date interval", $v));
            }
            if (\is_int($v) || \is_float($v)) {
                return (int) $v;
            }
            throw throw new \InvalidArgumentException(\sprintf("Expected an int or valid date interval string value, got '%s'", \get_debug_type($v)));
        };
    }
}
