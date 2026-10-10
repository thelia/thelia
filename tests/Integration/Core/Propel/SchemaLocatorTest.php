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
    private const BROKEN_SCHEMA = '<?xml version="1.0"?><database name="TheliaMain"><table name="never"></database>';

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
        $configDir = $this->temporaryModules().'/Mod%41ule/Config';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', self::schema('sample_one'));
        $filesystem->dumpFile($configDir.'/other-schema.xml', self::schema('sample_two'));

        [$schemas] = $this->schemasAndErrorLog(['Mod%41ule']);

        self::assertEqualsCanonicalizing([$configDir.'/other-schema.xml', $configDir.'/schema.xml'], array_keys($schemas));
        self::assertSame('sample_two', $schemas[$configDir.'/other-schema.xml']->getElementsByTagName('table')->item(0)?->getAttribute('name'));
    }

    /**
     * A schema that cannot be read is left out, as before, but said so in the error log:
     * its tables went missing from the generated models without a word.
     */
    public function testASchemaThatCannotBeReadIsSkippedAndSaidSo(): void
    {
        $configDir = $this->temporaryModules().'/Broken/Config';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', self::schema('sample_one'));
        $filesystem->dumpFile($configDir.'/broken-schema.xml', self::BROKEN_SCHEMA);

        [$schemas, $said] = $this->schemasAndErrorLog(['Broken']);

        self::assertSame([$configDir.'/schema.xml'], array_keys($schemas));
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
        $configDir = $this->temporaryModules().'/Outer/Config';
        $projectFolder = $this->temporaryProjectFolder();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', self::schema('sample_one', \sprintf('<external-schema filename="%1$s/good.xml"/><external-schema filename="%1$s/broken.xml"/>', $projectFolder)));
        $filesystem->dumpFile($this->projectDir.'/good.xml', self::schema('sample_two'));
        $filesystem->dumpFile($this->projectDir.'/broken.xml', self::BROKEN_SCHEMA);

        [$schemas, $said] = $this->schemasAndErrorLog(['Outer']);

        self::assertEqualsCanonicalizing([$configDir.'/schema.xml', $this->projectDir.'/good.xml'], array_keys($schemas));
        self::assertStringContainsString('[thelia] The Propel schema '.$this->projectDir.'/broken.xml of the schema '.$configDir.'/schema.xml could not be read (', $said);
        self::assertStringContainsString('tag mismatch', $said);
        self::assertStringNotContainsString('good.xml', $said);
    }

    /**
     * The filename of an external schema is relative to the project: a file it names
     * outside of it is no schema of the project, and is left out with that reason, by
     * the filename as the module wrote it, on one line.
     */
    public function testAnExternalSchemaOutsideTheProjectIsSkippedAndSaidSo(): void
    {
        $configDir = $this->temporaryModules().'/Outer/Config';
        $outside = str_repeat('../', substr_count(trim(THELIA_ROOT, '/'), '/') + 1).ltrim($this->localModuleDir, '/').'/out';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', self::schema('sample_one', \sprintf('<external-schema filename="%s&#10;side.xml"/>', $outside)));
        $filesystem->dumpFile($this->localModuleDir."/out\nside.xml", self::schema('sample_two'));

        [$schemas, $said] = $this->schemasAndErrorLog(['Outer']);

        self::assertSame([$configDir.'/schema.xml'], array_keys($schemas));
        self::assertStringContainsString('[thelia] The Propel schema '.$outside.'?side.xml of the schema '.$configDir.'/schema.xml could not be read (it is not a file of the project)', $said);
        self::assertStringNotContainsString("\n", trim($said));
    }

    /**
     * The log line holds on one line whatever the path of a schema or the folder of a
     * module carries (a line break, a mark that reorders text): what the module wrote
     * cannot forge a line of the log.
     */
    public function testTheLogLineHoldsOnOneLineWhateverAPathCarries(): void
    {
        $moduleCode = "Forged\u{202E}";
        $configDir = $this->temporaryModules().'/'.$moduleCode.'/Config';
        $projectFolder = $this->temporaryProjectFolder();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configDir.'/schema.xml', self::schema('sample_one', \sprintf('<external-schema filename="%s/bad&#10;[thelia] forged.xml"/>', $projectFolder)));
        $filesystem->dumpFile($this->projectDir."/bad\n[thelia] forged.xml", self::BROKEN_SCHEMA);

        [$schemas, $said] = $this->schemasAndErrorLog([$moduleCode]);

        self::assertSame([$configDir.'/schema.xml'], array_keys($schemas));
        self::assertStringContainsString('[thelia] The Propel schema '.$this->projectDir.'/bad?[thelia] forged.xml of the schema '.$this->localModuleDir.'/Forged?/Config/schema.xml could not be read (', $said);
        self::assertStringNotContainsString("\n", trim($said));
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

    private static function schema(string $table, string $trailingElements = ''): string
    {
        return \sprintf('<?xml version="1.0"?><database name="TheliaMain"><table name="%s"><column name="id" type="INTEGER" primaryKey="true"/></table>%s</database>', $table, $trailingElements);
    }

    /**
     * A folder of modules of its own, removed after the test. The keys of the schemas
     * are real paths: the temporary directory is a link on some hosts.
     */
    private function temporaryModules(): string
    {
        return $this->localModuleDir = realpath(sys_get_temp_dir()).'/thelia-schema-locator-'.bin2hex(random_bytes(4));
    }

    /**
     * A folder of the project, where an external schema has to be, by its path from the
     * root, as a filename attribute names it: the first file written there makes it.
     * Kept in $projectDir, removed after the test.
     */
    private function temporaryProjectFolder(): string
    {
        $projectFolder = 'var/thelia-schema-locator-'.bin2hex(random_bytes(4));
        $this->projectDir = THELIA_ROOT.$projectFolder;

        return $projectFolder;
    }

    /**
     * The schemas found for the modules, and what was written to the error log meanwhile
     * (the log is a file of the folder temporaryModules() made).
     *
     * @param list<string> $modules
     *
     * @return array{0: array<string, \DOMDocument>, 1: string}
     */
    private function schemasAndErrorLog(array $modules): array
    {
        $errorLog = $this->localModuleDir.'/error.log';
        $previousErrorLog = ini_set('error_log', $errorLog);

        try {
            $schemas = $this->createSchemaLocator()->findForModules($modules, false);
        } finally {
            ini_set('error_log', (string) $previousErrorLog);
        }

        return [$schemas, is_file($errorLog) ? (string) file_get_contents($errorLog) : ''];
    }
}
