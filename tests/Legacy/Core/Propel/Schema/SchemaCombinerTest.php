<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Core\Propel\Schema;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Propel\Schema\SchemaCombiner;
use Thelia\Tests\Core\Propel\Schema\Fixtures\Expectation\SchemaCombineExpectation as CombineExpectation;

/**
 * @covers \Thelia\Core\Propel\Schema\SchemaCombiner
 */
class SchemaCombinerTest extends TestCase
{
    /**
     * Path of the XML Schema Definition (XSD) for Propel schema files.
     * @var string
     */
    protected static $PROPEL_SCHEMA_XSD_PATH;

    /**
     * Map of [database attributes inheritable by tables => corresponding table attribute].
     * @var array
     */
    protected static $DATABASE_INHERITABLE_ATTRIBUTES;

    /**
     * Path of the fixture files, relative to this file.
     * @var string
     */
    protected static $FIXTURES_PATH;

    public static function setUpBeforeClass(): void
    {
        $fs = new Filesystem();

        if ($fs->exists(THELIA_VENDOR . '/thelia/propel/resources/xsd/database.xsd')) {
            self::$PROPEL_SCHEMA_XSD_PATH = THELIA_VENDOR . '/thelia/propel/resources/xsd/database.xsd';
        } else {
            self::$PROPEL_SCHEMA_XSD_PATH = THELIA_VENDOR . '/propel/propel/resources/xsd/database.xsd';
        }

        $schemaProcessorReflection = new \ReflectionClass('Thelia\Core\Propel\Schema\SchemaCombiner');
        $databaseInheritableAttributesProperty
            = $schemaProcessorReflection->getProperty('DATABASE_INHERITABLE_ATTRIBUTES');
        $databaseInheritableAttributesProperty->setAccessible(true);
        self::$DATABASE_INHERITABLE_ATTRIBUTES = $databaseInheritableAttributesProperty->getValue();

        self::$FIXTURES_PATH = __DIR__ . '/Fixtures/schema/';
    }

    public function combineExpectationsProvider()
    {
        return CombineExpectation::getCombineExpectations();
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testOutputTypesAreCorrect(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        $this->assertContainsOnly(
            'string',
            $schemaCombiner->getDatabases()
        );
        foreach ($schemaCombiner->getDatabases() as $database) {
            $this->assertInstanceOf(
                \DOMDocument::class,
                $schemaCombiner->getCombinedDocument($database)
            );
            $this->assertContainsOnlyInstancesOf(
                \DOMDocument::class,
                $schemaCombiner->getSourceDocuments($database)
            );
            $this->assertContainsOnlyInstancesOf(
                \DOMDocument::class,
                $schemaCombiner->getExternalSchemaDocuments($database)
            );
        }
    }

    /**
     * Load fixture files.
     * @param array $files Fixture file names.
     * @return array A map of [file name => \DOMDocument].
     */
    protected function loadFixtureFiles(array $files)
    {
        $documents = [];

        foreach ($files as $file) {
            $document = new \DOMDocument();
            $document->load(self::$FIXTURES_PATH . $file);
            $documents[$file] = $document;
        }

        return $documents;
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testCombinedSchemasAreValid(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $this->assertTrue(
                $schemaCombiner->getCombinedDocument($database)->schemaValidate(static::$PROPEL_SCHEMA_XSD_PATH),
                "Document for database '{$database}' is invalid."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testOneGlobalSchemaPerDatabaseIsProduced(array $schemaFiles, array $expectedDatabases)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        $this->assertEquals(
            \count($expectedDatabases),
            \count($schemaCombiner->getDatabases()),
            'Unexpected number of combined databases.'
        );

        foreach ($expectedDatabases as $expectedDatabase => $expectedTables) {
            $this->assertContains(
                $expectedDatabase,
                $schemaCombiner->getDatabases(),
                "A combined document for database '{$expectedDatabase}' should have been produced."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testCombinedSchemasContainOnlyOneDatabaseElement(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            $databaseElements = $databaseDocument->getElementsByTagName('database');

            $this->assertEquals(
                1,
                $databaseElements->length,
                "Document for database '{$database}' should contain only one database tag."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testDatabaseElementsAreCorrectlyNamed(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            $this->assertEquals(
                $database,
                $databaseElement->getAttribute('name'),
                "Element for database '{$database}' should have this as the 'name' attribute."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testDatabaseElementsHaveIdentifierQuotingActive(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            $this->assertEquals(
                'true',
                $databaseElement->getAttribute('identifierQuoting'),
                "Element for database '{$database}' should have the 'identifierQuoting' attribute set to 'true'."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testDatabaseElementsHaveNoTablePrefixAttribute(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            $this->assertFalse(
                $databaseElement->hasAttribute('tablePrefix'),
                "Element for database '{$database}' should not have a 'tablePrefix' attribute"
                . " (tables will be prefixed)."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testDatabaseElementsHaveNoInheritableAttributes(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            foreach (array_keys(static::$DATABASE_INHERITABLE_ATTRIBUTES) as $databaseInheritableAttribute) {
                $this->assertFalse(
                    $databaseElement->hasAttribute($databaseInheritableAttribute),
                    "Element for database '{$database}'"
                    . " should not have inheritable attribute '{$databaseInheritableAttribute}' (it will be inherited)."
                );
            }
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testDatabaseElementsHaveNoExternalSchemaElements(array $schemaFiles)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            $externalSchemaElements = $databaseDocument->getElementsByTagName('external-schema');

            $this->assertEquals(
                0,
                $externalSchemaElements->length,
                "Element for database '{$database}' should not contain any 'external-schema' element."
            );
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testExpectedTableElementsAreGenerated(array $schemaFiles, array $expectedDatabases)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            $expectedTables = $expectedDatabases[$database];

            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            $tableElements = $databaseElement->getElementsByTagName('table');

            $this->assertEquals(
                \count($expectedTables),
                $tableElements->length,
                "Table count for database '{$database}' is incorrect."
            );

            $databaseDocumentPath = new \DOMXPath($databaseDocument);

            foreach (array_keys($expectedTables) as $expectedTable) {
                $actualTableElements = $databaseDocumentPath->query("table[@name=\"{$expectedTable}\"]");

                // having multiple tables with the same name is a problem, but not something we check for
                $this->assertGreaterThanOrEqual(
                    1,
                    $actualTableElements->length,
                    "Table '{$expectedTable}' missing in database '{$database}'."
                );
            }

            /** @var \DOMElement $tableElement */
            foreach ($tableElements as $tableElement) {
                $table = $tableElement->getAttribute('name');

                $this->assertTrue(
                    isset($expectedTables[$table]),
                    "Unexpected table '{$table}' in database '{$database}'."
                );

                unset($expectedTables[$table]);
            }
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testInheritableDatabaseAttributesAreInheritedOnTables(array $schemaFiles, array $expectedDatabases)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            $expectedTables = $expectedDatabases[$database];

            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            $tableElements = $databaseElement->getElementsByTagName('table');

            /** @var \DOMElement $tableElement */
            foreach ($tableElements as $tableElement) {
                $table = $tableElement->getAttribute('name');

                $tableExpectations = $expectedTables[$table];

                if (!isset($tableExpectations['attributes'])) {
                    continue;
                }

                foreach ($tableExpectations['attributes'] as $expectedAttributeName => $attributeExpectations) {
                    $this->assertTrue(
                        $tableElement->hasAttribute($expectedAttributeName),
                        "Attribute '{$expectedAttributeName}' is missing on table '{$table}'"
                        . " (database '{$database}')."
                    );

                    $expectedAttributeValue = $attributeExpectations['value'];
                    $expectedAttributeSource = $attributeExpectations['source'];

                    $this->assertEquals(
                        $tableElement->getAttribute($expectedAttributeName),
                        $attributeExpectations['value'],
                        "Incorrect value for attribute '{$expectedAttributeName}' on table '{$table}'"
                        . " (database '{$database}'). "
                        . "Should be '{$expectedAttributeValue}', from the {$expectedAttributeSource}."
                    );
                }
            }
        }
    }

    /**
     * @covers       Thelia\Core\Propel\Schema\SchemaCombiner
     * @dataProvider combineExpectationsProvider
     */
    public function testTableElementsContentIsPreserved(array $schemaFiles, array $expectedDatabases)
    {
        $schemaDocuments = $this->loadFixtureFiles($schemaFiles);
        $schemaCombiner = new SchemaCombiner(array_values($schemaDocuments));

        foreach ($schemaCombiner->getDatabases() as $database) {
            $databaseDocument = $schemaCombiner->getCombinedDocument($database);
            $expectedTables = $expectedDatabases[$database];

            /** @var \DOMElement $databaseElement */
            $databaseElement = $databaseDocument->getElementsByTagName('database')->item(0);

            $tableElements = $databaseElement->getElementsByTagName('table');

            /** @var \DOMElement $tableElement */
            foreach ($tableElements as $tableElement) {
                $table = $tableElement->getAttribute('name');

                $tableExpectations = $expectedTables[$table];
                $tableSourceFile = $tableExpectations['source-file'];
                /** @var \DOMDocument $tableSourceDocument */
                $tableSourceDocument = $schemaDocuments[$tableSourceFile];

                if (isset($tableExpectations['name-prefixed-from'])
                    || !empty($tableExpectations['name-prefixed-from'])
                ) {
                    $tableSourceName = $tableExpectations['name-prefixed-from'];
                } else {
                    $tableSourceName = $table;
                }

                $tableSourceDocumentPath = new \DOMXPath($tableSourceDocument);
                $tableSourceElement = $tableSourceDocumentPath->query("table[@name=\"{$tableSourceName}\"]")->item(0);

                $this->assertEquals(
                    $this->c14nChildNodes($tableSourceElement),
                    $this->c14nChildNodes($tableElement),
                    "Table '{$table}' (in database '{$database}') content was altered."
                );
            }
        }
    }

    /**
     * Canonicalize child nodes of a nodes to a string
     * @param \DOMNode $node Source node.
     * @return string Child nodes as a string.
     */
    protected function c14nChildNodes(\DOMNode $node)
    {
        $content = '';

        /** @var \DOMNode $node */
        foreach ($node->childNodes as $node) {
            $content .= $node->C14N();
        }

        return $content;
    }
}
