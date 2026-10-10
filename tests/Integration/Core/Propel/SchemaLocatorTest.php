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

namespace Thelia\Tests\Integration\Core\Propel;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Test\IntegrationTestCase;

final class SchemaLocatorTest extends IntegrationTestCase
{
    private ?string $localModuleDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->localModuleDir) {
            (new Filesystem())->remove($this->localModuleDir);
        }

        parent::tearDown();
    }

    private function createSchemaLocator(): SchemaLocator
    {
        return new SchemaLocator(
            THELIA_CONF_DIR,
            THELIA_MODULE_DIR,
            $this->localModuleDir ?? THELIA_LOCAL_MODULE_DIR,
        );
    }

    /**
     * A module folder whose name reads as a URI ("Mod%41ule") is a folder: libxml, given
     * the path, decoded it and the schemas of the module were silently left out. Each
     * schema is told apart by its path, as the documents are merged by it.
     */
    public function testTheSchemasOfAModuleInAFolderThatReadsAsAUriAreFound(): void
    {
        $this->localModuleDir = sys_get_temp_dir().'/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $configDir = $this->localModuleDir.'/Mod%41ule/Config';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_one"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');
        $filesystem->dumpFile($configDir.'/other-schema.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_two"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');

        $schemas = $this->createSchemaLocator()->findForModules(['Mod%41ule'], false);

        ksort($schemas);
        self::assertSame([$configDir.'/other-schema.xml', $configDir.'/schema.xml'], array_keys($schemas));
        self::assertSame('sample_two', $schemas[$configDir.'/other-schema.xml']->getElementsByTagName('table')->item(0)?->getAttribute('name'));
    }

    public function testFindForModulesReturnsCoreSchemas(): void
    {
        $schemas = $this->createSchemaLocator()->findForModules(['Thelia']);

        self::assertNotEmpty($schemas);
    }

    public function testFindForModulesSkipsModuleMissingFromDisk(): void
    {
        // A module can be active in the database while its code is missing from
        // disk (e.g. removed from composer while a previously populated database
        // is reused). The locator must skip it instead of crashing the boot.
        $schemas = $this->createSchemaLocator()->findForModules(['GhostModuleMissingFromDisk'], true);

        // Core schemas are still returned: 'Thelia' is always added as a dependency.
        self::assertNotEmpty($schemas);
    }

    public function testFindForModulesWithEmptyListReturnsNothing(): void
    {
        self::assertSame([], $this->createSchemaLocator()->findForModules([]));
    }
}
