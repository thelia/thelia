<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Propel\Schema;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thelia\Module\Validator\ModuleValidator;

/**
 * Find Propel schemas of Thelia and Thelia modules.
 */
class SchemaLocator
{
    /**
     * Argument to Finder::name() used to filter schema files.
     */
    protected static string $SCHEMA_FILE_PATTERN = '*schema.xml';

    public function __construct(
        protected string $theliaConfDir,
        protected string $theliaModuleDir,
        protected string $theliaLocalModuleDir,
    ) {
    }

    /**
     * Get schema documents for Thelia core and active modules, as well as included external schemas.
     */
    public function findForAllModules(): array
    {
        $finder = new Finder();
        $filesystem = new Filesystem();
        $modulesPath = [
            THELIA_MODULE_DIR => THELIA_MODULE_DIR.'*'.DS.'Config',
            THELIA_LOCAL_MODULE_DIR => THELIA_LOCAL_MODULE_DIR.'*'.DS.'Config',
        ];
        $modules = [];
        foreach ($modulesPath as $rootPath => $modulePath) {
            try {
                if (!$filesystem->exists($rootPath)) {
                    throw new DirectoryNotFoundException(\sprintf('The directory "%s" does not exist.', $rootPath));
                }

                $finder->name('module.xml')->in($modulePath);

                $codes = array_map(static fn ($file): string => basename(\dirname((string) $file, 2)), iterator_to_array($finder));
                $modules = array_merge($this->findForModules($codes), $modules);
            } catch (\Exception) {
                continue;
            }
        }

        return $modules;
    }

    /**
     * Get schema documents for specific modules and their dependencies (including Thelia), as well as included
     * external schemas.
     *
     * @param string[] $modulesCodes     Codes of the modules to fetch schemas for. 'Thelia' can be used to include Thelia core
     *                                   schemas.
     * @param bool     $withDependencies whether to also return schemas for the specified modules dependencies
     *
     * @return \DOMDocument[] schema documents
     */
    public function findForModules(array $modulesCodes = [], bool $withDependencies = true): array
    {
        if ($withDependencies) {
            $modulesCodes = $this->addModulesDependencies($modulesCodes);
        }

        $schemas = [];
        foreach ($modulesCodes as $moduleCode) {
            if ($moduleCode === 'Thelia') {
                $moduleSchemas = $this->getSchemaPathsForThelia();
            } else {
                $moduleSchemas = $this->getSchemaPathsForModule($moduleCode);
            }

            $schemaDocuments = [];
            /** @var SplFileInfo $schemaFile */
            foreach ($moduleSchemas as $schemaFile) {
                $schemaDocument = new \DOMDocument();
                $isValid = $schemaDocument->load($schemaFile->getRealPath());
                if ($isValid) {
                    $schemaDocuments[] = $schemaDocument;
                }
            }

            $schemaDocuments = $this->addExternalSchemaDocuments($schemaDocuments);
            $schemas = $this->mergeDOMDocumentsArrays([$schemas, $schemaDocuments]);
        }

        return $schemas;
    }

    /**
     * Add dependencies of some modules.
     *
     * @param string[] $modules module codes
     *
     * @return string[] modules codes with added dependencies
     */
    protected function addModulesDependencies(array $modules = []): array
    {
        if ($modules === []) {
            return [];
        }

        // Thelia is always a dependency
        if (!\in_array('Thelia', $modules)) {
            $modules[] = 'Thelia';
        }

        foreach ($modules as $module) {
            // Thelia is not a real module, do not try to get its dependencies
            if ($module === 'Thelia') {
                continue;
            }

            $modulePath = is_dir(\sprintf('%s/%s', $this->theliaModuleDir, $module))
                ? \sprintf('%s/%s', $this->theliaModuleDir, $module)
                : \sprintf('%s/%s', $this->theliaLocalModuleDir, $module);

            $moduleValidator = new ModuleValidator($modulePath);
            $dependencies = $moduleValidator->getCurrentModuleDependencies(true);
            foreach ($dependencies as $dependency) {
                if (!\in_array($dependency['code'], $modules, true)) {
                    $modules[] = $dependency['code'];
                }
            }
        }

        return $modules;
    }

    /**
     * @return Finder thelia schema files
     */
    protected function getSchemaPathsForThelia(): Finder
    {
        return Finder::create()
            ->files()
            ->name(static::$SCHEMA_FILE_PATTERN)
            ->in($this->theliaConfDir);
    }

    protected function getSchemaPathsForModule(string $module): Finder
    {
        $moduleSchemas = Finder::create()
            ->files()
            ->name(static::$SCHEMA_FILE_PATTERN)
            ->depth(0);

        try {
            $modulePath = is_dir(\sprintf('%s/%s', $this->theliaModuleDir, $module))
                ? \sprintf('%s%s/Config', $this->theliaModuleDir, $module)
                : \sprintf('%s/%s/Config', $this->theliaLocalModuleDir, $module);
            $moduleSchemas->in($modulePath);
        } catch (\InvalidArgumentException) {
            // just continue if the module has no Config directory
        }

        return $moduleSchemas;
    }

    /**
     * Add external schema documents not already included.
     */
    protected function addExternalSchemaDocuments(array $schemaDocuments): array
    {
        $fs = new Filesystem();

        $externalSchemaDocuments = [];

        foreach ($schemaDocuments as $schemaDocument) {
            /** @var \DOMElement $externalSchemaElement */
            foreach ($schemaDocument->getElementsByTagName('external-schema') as $externalSchemaElement) {
                if (!$externalSchemaElement->hasAttribute('filename')) {
                    continue;
                }

                $externalSchemaPath = THELIA_ROOT.$externalSchemaElement->getAttribute('filename');

                if (!$fs->exists($externalSchemaPath)) {
                    continue;
                }

                $externalSchemaDocument = new \DOMDocument();
                if (!$externalSchemaDocument->load($externalSchemaPath)) {
                    continue;
                }

                $externalSchemaDocuments[] = $externalSchemaDocument;
            }
        }

        return $this->mergeDOMDocumentsArrays([$schemaDocuments, $externalSchemaDocuments]);
    }

    /**
     * @param \DOMDocument[][] $documentArrays
     *
     * @return \DOMDocument[]
     */
    protected function mergeDOMDocumentsArrays(array $documentArrays): array
    {
        $result = [];
        foreach ($documentArrays as $documentArray) {
            foreach ($documentArray as $document) {
                $baseUri = str_replace(THELIA_LOCAL_MODULE_DIR, '', $document->baseURI);
                $baseUri = str_replace(THELIA_MODULE_DIR, '', $baseUri);
                if (isset($result[$baseUri])) {
                    continue;
                }

                $result[$baseUri] = $document;
            }
        }

        return $result;
    }
}
