<?php

declare(strict_types=1);

namespace MakinaCorpus\DbToolsBundle\Anonymization\Config\Loader;

use MakinaCorpus\DbToolsBundle\Error\ConfigurationException;

class PhpFileLoader extends ArrayLoader
{
    public function __construct(
        private string $file,
        string $connectionName = 'default',
    ) {
        parent::__construct([], $connectionName);
    }

    #[\Override]
    protected function getData(): array
    {
        try {
            $data = require $this->file;
        } catch (\Throwable $e) {
            throw new ConfigurationException(\sprintf(
                "An error occurred when loading \"%s\" configuration file: %s",
                $this->file,
                $e->getMessage()
            ), 0, $e);
        }

        if (!\is_array($data)) {
            throw new ConfigurationException(\sprintf(
                "File \"%s\" is not a valid PHP anonymization configuration file (must return an array).",
                $this->file
            ));
        }

        return $data;
    }
}
