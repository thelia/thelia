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

namespace Thelia\Command;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Propel\Schema\SchemaCombiner;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\PropelInitService;
use Thelia\Module\Validator\ModuleValidator;

/**
 * base class for module commands.
 *
 * Class BaseModuleGenerate
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseModuleGenerate extends ContainerAwareCommand
{
    protected $module;
    protected $moduleDirectory;

    protected $reservedKeyWords = [
         'thelia',
     ];

    protected $neededDirectories = [
         'Config',
         'Model',
         'Loop',
         'Command',
         'Controller',
         'EventListeners',
         'I18n',
         'templates',
         'Hook',
     ];

    protected function verifyExistingModule()
    {
        if (file_exists($this->moduleDirectory)) {
            throw new \RuntimeException(
                sprintf(
                    '%s module already exists. Use --force option to force generation.',
                    $this->module
                )
            );
        }
    }

    protected function formatModuleName($name)
    {
        if (\in_array(strtolower($name), $this->reservedKeyWords)) {
            throw new \RuntimeException(sprintf('%s module name is a reserved keyword', $name));
        }

        return ucfirst($name);
    }

    protected function validModuleName($name)
    {
        if (!preg_match('#^[A-Z]([A-Za-z\d])+$#', $name)) {
            throw new \RuntimeException(
                sprintf('%s module name is not a valid name, it must be in CamelCase. (ex: MyModuleName)', $name)
            );
        }
    }

    protected function checkModuleSchema()
    {
        $moduleValidator = new ModuleValidator($this->moduleDirectory);
        $moduleValidator->checkModulePropelSchema();
    }

    protected function generateGlobalSchemaForModule()
    {
        /** @var SchemaLocator $schemaLocator */
        $schemaLocator = $this->getContainer()->get('thelia.propel.schema.locator');
        /** @var PropelInitService $propelInitService */
        $propelInitService = $this->getContainer()->get('thelia.propel.init');

        $schemaCombiner = new SchemaCombiner(
            $schemaLocator->findForModules([$this->module])
        );

        $fs = new Filesystem();
        $schemasDir = "{$propelInitService->getPropelCacheDir()}/schema-{$this->module}";
        $fs->mkdir($schemasDir);

        foreach ($schemaCombiner->getDatabases() as $database) {
            file_put_contents(
                "{$schemasDir}/{$database}.schema.xml",
                $schemaCombiner->getCombinedDocument($database)->saveXML()
            );
        }

        return $schemasDir;
    }
}
