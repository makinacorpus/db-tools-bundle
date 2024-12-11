<?php

declare(strict_types=1);

return [
    /*
    | -------------------------------------------------------------------------
    | Storage directory
    | -------------------------------------------------------------------------
    |
    | Where to store generated backup files. Root directory for the backup
    | storage manager. Default filename strategy will always use this folder
    | as root path.
    |
    */

    'storage_directory' => env('DBTOOLS_STORAGE_DIRECTORY', storage_path('db_tools')),

    /*
    | -------------------------------------------------------------------------
    | Storage filename strategy
    | -------------------------------------------------------------------------
    |
    | Filename strategies for backups storage. You may specify one strategy for
    | each database connection. Keys are connection names, values are strategy
    | names.
    |
    | A strategy name can be either the class name (FQN) of a custom strategy
    | implementation, or its potential "little name" or "identifier" if you
    | registered it into the container without using directly its class name.
    |
    | "default", null, or omitting the connection are equivalent and involve
    | the use of the default implementation.
    |
    | To summarize, allowed values are:
    |  - "default": alias of "datetime".
    |  - "datetime": implementation producing names formatted as such:
    |    <storage_directory>/YYYY/MM/<connection_name>-<datestamp>.<ext>.
    |  - CLASS_NAME: class name of a custom strategy implementation.
    |  - SERVICE_ID: identifier of a custom strategy implementation registered
    |     as a service into the container.
    |
    */

    'storage_filename_strategy' => env('DBTOOLS_STORAGE_FILENAME_STRATEGY'),

    /*
    | -------------------------------------------------------------------------
    | Backup file expiration
    | -------------------------------------------------------------------------
    |
    | Indicate when old backups can be considered obsolete.
    |
    | Use relative date/time formats:
    | https://www.php.net/manual/en/datetime.formats.relative.php
    |
    */

    'backup_expiration_age' => env('DBTOOLS_BACKUP_EXPIRATION_AGE'),

    /*
    | -------------------------------------------------------------------------
    | Backup excluded tables
    | -------------------------------------------------------------------------
    |
    | List here database tables you don't want to include in your backups.
    |
    | If you have more than one database connection, it is strongly advised
    | to configure this for each connection instead.
    |
    */

    'backup_excluded_tables' => env('DBTOOLS_BACKUP_EXCLUDED_TABLES'),

    /*
    | -------------------------------------------------------------------------
    | Process timeouts
    | -------------------------------------------------------------------------
    |
    | Default timeouts for backup and restoration processes.
    |
    */

    'backup_timeout' => env('DBTOOLS_BACKUP_TIMEOUT'),
    'restore_timeout' => env('DBTOOLS_RESTORE_TIMEOUT'),

    /*
    | -------------------------------------------------------------------------
    | Binaries & options
    | -------------------------------------------------------------------------
    |
    | Specify here paths to backup and restoration binaries as well as their
    | respective command line options.
    |
    | Warning: this will apply to all connections disregarding their database
    | vendor. If you have more than one connection and if they use different
    | database vendors or versions, please configure those for each connection
    | instead.
    |
    | Default values depends upon vendor and are documented at
    | https://dbtoolsbundle.readthedocs.io/en/stable/configuration.html
    |
    */

    'backup_binary' => env('DBTOOLS_BACKUP_BINARY'),
    'backup_options' => env('DBTOOLS_BACKUP_OPTIONS'),
    'restore_binary' => env('DBTOOLS_RESTORE_BINARY'),
    'restore_options' => env('DBTOOLS_RESTORE_OPTIONS'),

    /*
    | -------------------------------------------------------------------------
    | Connection specific parameters
    | -------------------------------------------------------------------------
    |
    | For advanced usage, you may also override any parameter for each database
    | connection. All parameters defined above are allowed. Keys are connection
    | names.
    |
    | Example:
    |
    | 'connections' => [
    |     'specific_connection' => [
    |         'backup_binary' => '/usr/local/bin/vendor-dump',
    |         'backup_excluded_tables' => ['table_one', 'table_two'],
    |         'backup_expiration_age' => '1 month ago',
    |         'backup_options' => '--no-table-lock',
    |         'backup_timeout' => 2000,
    |         'restore_binary' => '/usr/local/bin/vendor-restore',
    |         'restore_options' => '--disable-triggers --other-option',
    |         'restore_timeout' => 5000,
    |         'storage_directory' => '/path/to/storage',
    |         'storage_filename_strategy' => 'datetime',
    |     ],
    | ],
    |
    */

    'connections' => [],

    /*
    | -------------------------------------------------------------------------
    | Anonymizer folders
    | -------------------------------------------------------------------------
    |
    | Update this configuration if you want to look for additional anonymizers
    | in custom folders.
    |
    | Be aware that DbToolsBundle will always take a look at the default folder
    | dedicated to your custom anonymizers: <project-dir>/app/Anonymizer,
    | so you don't have to repeat it.
    |
    */

    'anonymizer_paths' => [],

    /*
    | -------------------------------------------------------------------------
    | Anonymization configuration
    | -------------------------------------------------------------------------
    |
    | For simple needs, you may simply write the anonymization configuration
    | here. Keys are connection names, values are structures identical to what
    | you may find in the "anonymizations.sample.yaml" example.
    |
    | Example:
    |
    | 'anonymization' => [
    |     'specific_connection' => [
    |         'table1' => [
    |             'column1' => [
    |                 'anonymizer' => 'anonymizer_name',
    |                 // Anonymizer specific options...
    |             ],
    |             'column2' => [
    |                 // ...
    |             ],
    |         ],
    |         'table2' => [
    |             // ...
    |         ],
    |     ],
    | ],
    |
    */

    'anonymization' => [],

    /*
    | -------------------------------------------------------------------------
    | Anonymization configuration files
    | -------------------------------------------------------------------------
    |
    | You can, for organisation purpose, delegate anonymization configuration
    | into extra configuration files, and simply reference them here.
    |
    | File paths must be absolute.
    |
    | You can use PHP or YAML files to configure your anonymization.
    |
    | See the "config/anonymizations.sample.yaml" file included in the
    | makinacorpus/db-tools-bundle package as an example of anonymization
    | configuration file.
    |
    | Example:
    |
    | 'anonymization_files' => [
    |     'connection_one' => database_path('anonymization/connection_one.php'),
    |     'connection_two' => database_path('anonymization/connection_two.php'),
    | ],
    |
    | You can as well provide several files per connection if you need:
    |
    | 'anonymization_files' => [
    |     'connection_one' => [
    |         database_path('anonymization/connection_one/schema_one.php'),
    |         database_path('anonymization/connection_one/schema_two.php'),
    |     ],
    |     'connection_two' => database_path('anonymization/connection_two.php'),
    | ],
    |
    | If you have only one connection, you can directly provide a single file
    | path as the parameter value:
    |
    | 'anonymization_files' => database_path('anonymization.php'),
    |
    */

    'anonymization_files' => [],
];
