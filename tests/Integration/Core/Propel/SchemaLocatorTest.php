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

    private ?string $projectDir = null;

    protected function tearDown(): void
    {
        foreach ([$this->localModuleDir, $this->projectDir] as $directory) {
            if (null !== $directory) {
                (new Filesystem())->remove($directory);
            }
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
        // The keys are real paths: the temporary directory is a link on some hosts.
        $this->localModuleDir = realpath(sys_get_temp_dir()).'/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $configDir = $this->localModuleDir.'/Mod%41ule/Config';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_one"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');
        $filesystem->dumpFile($configDir.'/other-schema.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_two"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');

        $schemas = $this->createSchemaLocator()->findForModules(['Mod%41ule'], false);

        ksort($schemas);
        self::assertSame([$configDir.'/other-schema.xml', $configDir.'/schema.xml'], array_keys($schemas));
        self::assertSame('sample_two', $schemas[$configDir.'/other-schema.xml']->getElementsByTagName('table')->item(0)?->getAttribute('name'));
    }

    /**
     * A schema that cannot be read is left out, as before, but said so in the error log:
     * its tables went missing from the generated models without a word.
     */
    public function testASchemaThatCannotBeReadIsSkippedAndSaidSo(): void
    {
        $this->localModuleDir = realpath(sys_get_temp_dir()).'/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $configDir = $this->localModuleDir.'/Broken/Config';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_one"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');
        $filesystem->dumpFile($configDir.'/broken-schema.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="never"></database>');
        $errorLog = $this->localModuleDir.'/error.log';
        $previousErrorLog = ini_set('error_log', $errorLog);

        try {
            $schemas = $this->createSchemaLocator()->findForModules(['Broken'], false);
        } finally {
            ini_set('error_log', (string) $previousErrorLog);
        }

        self::assertSame([$configDir.'/schema.xml'], array_keys($schemas));
        $said = (string) file_get_contents($errorLog);
        self::assertStringContainsString('[thelia] The Propel schema '.$configDir.'/broken-schema.xml of module "Broken" could not be read (', $said);
        self::assertStringContainsString('tag mismatch', $said);
        self::assertStringContainsString('it is skipped, and its tables with it.', $said);
    }

    /**
     * An external schema a module names (a file of the project, by its path from the
     * root) is read as the schemas of the module are: one that cannot be read is left
     * out and said so, by the schema that named it, the others are kept.
     */
    public function testAnExternalSchemaThatCannotBeReadIsSkippedAndSaidSo(): void
    {
        $this->localModuleDir = realpath(sys_get_temp_dir()).'/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $projectFolder = 'var/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $this->projectDir = THELIA_ROOT.$projectFolder;
        $configDir = $this->localModuleDir.'/Outer/Config';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', \sprintf('<?xml version="1.0"?><database name="TheliaMain"><table name="sample_one"><column name="id" type="INTEGER" primaryKey="true"/></table><external-schema filename="%1$s/good.xml"/><external-schema filename="%1$s/broken.xml"/></database>', $projectFolder));
        $filesystem->dumpFile($this->projectDir.'/good.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_two"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');
        $filesystem->dumpFile($this->projectDir.'/broken.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="never"></database>');
        $errorLog = $this->localModuleDir.'/error.log';
        $previousErrorLog = ini_set('error_log', $errorLog);

        try {
            $schemas = $this->createSchemaLocator()->findForModules(['Outer'], false);
        } finally {
            ini_set('error_log', (string) $previousErrorLog);
        }

        ksort($schemas);
        self::assertSame([$configDir.'/schema.xml', $this->projectDir.'/good.xml'], array_keys($schemas));
        $said = (string) file_get_contents($errorLog);
        self::assertStringContainsString('[thelia] The Propel schema '.$this->projectDir.'/broken.xml of the schema '.$configDir.'/schema.xml could not be read (', $said);
        self::assertStringContainsString('tag mismatch', $said);
        self::assertStringNotContainsString('good.xml', $said);
    }

    /**
     * The filename of an external schema is relative to the project: a file it names
     * outside of it is no schema of the project, and is left out with that reason.
     */
    public function testAnExternalSchemaOutsideTheProjectIsSkippedAndSaidSo(): void
    {
        $this->localModuleDir = realpath(sys_get_temp_dir()).'/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $configDir = $this->localModuleDir.'/Outer/Config';
        $outside = str_repeat('../', substr_count(trim(THELIA_ROOT, '/'), '/') + 1).ltrim($this->localModuleDir, '/').'/outside.xml';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', \sprintf('<?xml version="1.0"?><database name="TheliaMain"><table name="sample_one"><column name="id" type="INTEGER" primaryKey="true"/></table><external-schema filename="%s"/></database>', $outside));
        $filesystem->dumpFile($this->localModuleDir.'/outside.xml', '<?xml version="1.0"?><database name="TheliaMain"><table name="sample_two"><column name="id" type="INTEGER" primaryKey="true"/></table></database>');
        $errorLog = $this->localModuleDir.'/error.log';
        $previousErrorLog = ini_set('error_log', $errorLog);

        try {
            $schemas = $this->createSchemaLocator()->findForModules(['Outer'], false);
        } finally {
            ini_set('error_log', (string) $previousErrorLog);
        }

        self::assertSame([$configDir.'/schema.xml'], array_keys($schemas));
        self::assertStringContainsString('of the schema '.$configDir.'/schema.xml could not be read (it is not a file of the project)', (string) file_get_contents($errorLog));
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
