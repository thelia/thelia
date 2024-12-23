<?php

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
     *
     * @var string
     */
    protected static $SCHEMA_FILE_PATTERN = '*schema.xml';

    /**
     * Thelia configuration directory.
     * Used to find Thelia schema files.
     *
     * @var string
     */
    protected $theliaConfDir;

    /**
     * Thelia module directory.
     * Used to find modules schema files.
     *
     * @var string
     */
    protected $theliaModuleDir;

    /**
     * @param string $theliaConfDir   thelia configuration directory
     * @param string $theliaModuleDir thelia module directory
     */
    public function __construct($theliaConfDir, $theliaModuleDir)
    {
        $this->theliaConfDir = $theliaConfDir;
        $this->theliaModuleDir = $theliaModuleDir;
    }

    /**
     * Get schema documents for Thelia core and active modules, as well as included external schemas.
     *
     * @return \DOMDocument[] schema documents
     */
    public function findForAllModules()
    {
        $finder = new Finder();
        $filesystem = new Filesystem();
        $modulesPath = THELIA_MODULE_DIR.'*'.DS.'Config';

        try {
            // Vérifiez si le répertoire existe avant d'utiliser Finder
            if (!$filesystem->exists(THELIA_MODULE_DIR)) {
                throw new DirectoryNotFoundException(sprintf('The directory "%s" does not exist.', THELIA_MODULE_DIR));
            }

            $finder->name('module.xml')->in($modulesPath);

            // reset keys
            $codes = array_map(function ($file) {
                return basename(\dirname($file, 2));
            }, iterator_to_array($finder));

            return $this->findForModules($codes);
        } catch (\Exception) {
            return $this->findForModules(['Thelia']);
        }
    }

    /**
     * Get schema documents for specific modules and their dependencies (including Thelia), as well as included
     * external schemas.
     *
     * @param string[] $modules          Codes of the modules to fetch schemas for. 'Thelia' can be used to include Thelia core
     *                                   schemas.
     * @param bool     $withDependencies whether to also return schemas for the specified modules dependencies
     *
     * @return \DOMDocument[] schema documents
     */
    public function findForModules(array $modules = [], $withDependencies = true)
    {
        if ($withDependencies) {
            $modules = $this->addModulesDependencies($modules);
        }

        $schemas = [];

        foreach ($modules as $module) {
            if ($module === 'Thelia') {
                $moduleSchemas = $this->getSchemaPathsForThelia();
            } else {
                $moduleSchemas = $this->getSchemaPathsForModule($module);
            }

            $schemaDocuments = [];
            /** @var SplFileInfo $schemaFile */
            foreach ($moduleSchemas as $schemaFile) {
                $schemaDocument = new \DOMDocument();
                $schemaDocument->load($schemaFile->getRealPath());
                $schemaDocuments[] = $schemaDocument;
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
    protected function addModulesDependencies(array $modules = [])
    {
        if (empty($modules)) {
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

            $moduleValidator = new ModuleValidator("{$this->theliaModuleDir}/{$module}");
            $dependencies = $moduleValidator->getCurrentModuleDependencies(true);
            foreach ($dependencies as $dependency) {
                if (!\in_array($dependency['code'], $modules)) {
                    $modules[] = $dependency['code'];
                }
            }
        }

        return $modules;
    }

    /**
     * @return Finder thelia schema files
     */
    protected function getSchemaPathsForThelia()
    {
        return Finder::create()
            ->files()
            ->name(static::$SCHEMA_FILE_PATTERN)
            ->in($this->theliaConfDir);
    }

    /**
     * @param string $module module code
     *
     * @return Finder schema files for this module
     */
    protected function getSchemaPathsForModule($module)
    {
        $moduleSchemas = Finder::create()
            ->files()
            ->name(static::$SCHEMA_FILE_PATTERN)
            ->depth(0);

        try {
            $moduleSchemas->in("{$this->theliaModuleDir}/{$module}/Config");
        } catch (\InvalidArgumentException $e) {
            // just continue if the module has no Config directory
        }

        return $moduleSchemas;
    }

    /**
     * Add external schema documents not already included.
     *
     * @param \DOMDocument[] $schemaDocuments schema documents
     *
     * @return \DOMDocument[] schema documents
     */
    protected function addExternalSchemaDocuments(array $schemaDocuments)
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
    protected function mergeDOMDocumentsArrays(array $documentArrays)
    {
        $result = [];
        $includedDocumentURIs = [];

        foreach ($documentArrays as $documentArray) {
            foreach ($documentArray as $document) {
                if (\in_array($document->baseURI, $includedDocumentURIs)) {
                    continue;
                }

                $result[] = $document;
                $includedDocumentURIs[] = $document->baseURI;
            }
        }

        return $result;
    }
}
