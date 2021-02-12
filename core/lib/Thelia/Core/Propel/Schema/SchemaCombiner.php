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

/**
 * Combine Propel schemas describing databases into a single schema per database.
 */
class SchemaCombiner
{
    /**
     * XML header version attribute for the generated schema documents.
     *
     * @var string
     */
    protected static $GLOBAL_SCHEMA_XML_VERSION = '1.0';

    /**
     * XML header encoding attribute for the generated schema documents.
     *
     * @var string
     */
    protected static $GLOBAL_SCHEMA_XML_ENCODING = 'UTF-8';

    /**
     * Map of [database attributes inheritable by tables => corresponding table attribute].
     * These are attributes that can be defined either at the database or the table level.
     * Since we are merging tables from various schema files that all define part of a database and can have different
     * database attribute for their own tables, we have to copy these attributes to the tables themselves (if they are
     * not already defined on the table).
     *
     * @var array
     */
    protected static $DATABASE_INHERITABLE_ATTRIBUTES = [
        'defaultIdMethod' => 'idMethod',
        'defaultAccessorVisibility' => 'defaultAccessorVisibility',
        'defaultMutatorVisibility' => 'defaultMutatorVisibility',
        'package' => 'package',
        'namespace' => 'namespace',
        'schema' => 'schema',
        'baseClass' => 'baseClass',
        'defaultPhpNamingMethod' => 'phpNamingMethod',
        'heavyIndexing' => 'heavyIndexing',
    ];

    /**
     * Combined databases.
     *
     * @var string[]
     */
    protected $databases = [];

    /**
     * Map of [database name => global database \DOMElement for that database].
     *
     * @var array
     */
    protected $globalDatabaseElements = [];

    /**
     * Map of [database name => [source database \DOMElement combined for this database]].
     *
     * @var array
     */
    protected $sourceDatabaseElements = [];

    /**
     * Map of [database name => [external-schema database \DOMElement included for this database]].
     *
     * @var array
     */
    protected $externalSchemaDatabaseElements = [];

    /**
     * @param \DOMDocument[] $schemaDocuments         schema documents to combine
     * @param \DOMDocument[] $externalSchemaDocuments schemas documents to include as external schemas in the combined
     *                                                documents
     */
    public function __construct(array $schemaDocuments = [], array $externalSchemaDocuments = [])
    {
        $this->combine($schemaDocuments, $externalSchemaDocuments);
    }

    /**
     * @return string[] combined databases
     */
    public function getDatabases()
    {
        return $this->databases;
    }

    /**
     * @param string $database Database
     *
     * @throws \InvalidArgumentException if the database is not in the combined databases
     */
    protected function assertDatabase($database): void
    {
        if (!\in_array($database, $this->databases)) {
            throw new \InvalidArgumentException("Database '{$database}' is not in the combined databases.");
        }
    }

    /**
     * @param \DOMElement $element element
     *
     * @return \DOMDocument element owner
     */
    protected static function getOwnerDocument(\DOMElement $element)
    {
        return $element->ownerDocument;
    }

    /**
     * @param string $database database
     *
     * @return \DOMDocument combined schema document for this database
     */
    public function getCombinedDocument($database)
    {
        $this->assertDatabase($database);

        /** @var \DOMElement $globalDatabaseElement */
        $globalDatabaseElement = $this->globalDatabaseElements[$database];

        return static::getOwnerDocument($globalDatabaseElement);
    }

    /**
     * @param string $database database
     *
     * @return \DOMDocument[] source schema documents that were combined for this database
     */
    public function getSourceDocuments($database)
    {
        $this->assertDatabase($database);

        return array_map([$this, 'getOwnerDocument'], $this->sourceDatabaseElements[$database]);
    }

    /**
     * @param string $database database
     *
     * @return \DOMDocument[] external schema documents that were included for this database
     */
    public function getExternalSchemaDocuments($database)
    {
        $this->assertDatabase($database);

        return array_map([$this, 'getOwnerDocument'], $this->externalSchemaDatabaseElements[$database]);
    }

    /**
     * Combine multiple schemas into one schema per database.
     *
     * @param \DOMDocument[] $schemaDocuments         schema documents to combine
     * @param \DOMDocument[] $externalSchemaDocuments schemas documents to include as external schemas in the combined
     *                                                documents
     *
     * @return array a map of [database name => \DOMDocument schema for that database]
     */
    public function combine(array $schemaDocuments = [], array $externalSchemaDocuments = [])
    {
        $globalDatabaseElements = [];

        // merge schema documents, per database
        foreach ($schemaDocuments as $sourceSchemaDocument) {
            if (!$sourceSchemaDocument instanceof \DOMDocument) {
                throw new \InvalidArgumentException('Schema file is not a \DOMDocument');
            }

            // work on document clones since we are going to edit them
            $sourceSchemaDocument = clone $sourceSchemaDocument;

            // process all <database> elements in the document
            /** @var \DOMElement $sourceDatabaseElement */
            foreach ($sourceSchemaDocument->getElementsByTagName('database') as $sourceDatabaseElement) {
                // pre-process the element
                $this->filterExternalSchemaElements($sourceDatabaseElement);
                $this->inheritDatabaseAttributes($sourceDatabaseElement);
                $this->applyDatabaseTablePrefix($sourceDatabaseElement);

                // append the element
                $this->mergeDatabaseElement($sourceDatabaseElement);
            }
        }

        // include external schema documents, per database
        foreach ($externalSchemaDocuments as $externalSchemaDocument) {
            if (!$externalSchemaDocument instanceof \DOMDocument) {
                throw new \InvalidArgumentException('Schema file is not a \DOMDocument');
            }

            // process all <database> elements in the document
            /** @var \DOMElement $externalSchemaDatabaseElement */
            foreach ($externalSchemaDocument->getElementsByTagName('database') as $externalSchemaDatabaseElement) {
                // include the document
                $this->includeExternalSchema($externalSchemaDatabaseElement);
            }
        }

        // return the documents, not the database elements
        $globalSchemaDocuments = [];
        /**
         * @var string      $database
         * @var \DOMElement $globalDatabaseElement
         */
        foreach ($globalDatabaseElements as $database => $globalDatabaseElement) {
            $globalSchemaDocuments[$database] = $globalDatabaseElement->ownerDocument;
        }

        return $globalSchemaDocuments;
    }

    /**
     * Remove <external-schema> references from a database element.
     *
     * @param \DOMElement $databaseElement database element to process
     */
    protected function filterExternalSchemaElements(\DOMElement $databaseElement): void
    {
        // removing the elements in the foreach itself will break the iterator, remove them later
        $externalSchemaElementsToDelete = [];
        /** @var \DOMElement $externalSchemaElement */
        foreach ($databaseElement->getElementsByTagName('external-schema') as $externalSchemaElement) {
            $externalSchemaElementsToDelete[] = $externalSchemaElement;
        }

        foreach ($externalSchemaElementsToDelete as $externalSchemaElement) {
            // add a removal notice
            $externalSchemaRemovalNoticeComment = $databaseElement->ownerDocument->createComment(
                "external-schema reference to '{$externalSchemaElement->getAttribute('filename')}' removed"
            );
            $databaseElement->appendChild($externalSchemaRemovalNoticeComment);

            $externalSchemaElement->parentNode->removeChild($externalSchemaElement);
        }
    }

    /**
     * Copy inheritable database attribute to the tables in the database.
     *
     * @param \DOMElement $databaseElement database element to process
     */
    protected function inheritDatabaseAttributes(\DOMElement $databaseElement): void
    {
        $attributesToInherit = [];
        foreach (static::$DATABASE_INHERITABLE_ATTRIBUTES as $databaseAttribute => $tableAttribute) {
            if (!$databaseElement->hasAttribute($databaseAttribute)) {
                continue;
            }

            $attributesToInherit[$tableAttribute] = $databaseElement->getAttribute($databaseAttribute);
        }

        /** @var \DOMElement $tableElement */
        foreach ($databaseElement->getElementsByTagName('table') as $tableElement) {
            foreach (static::$DATABASE_INHERITABLE_ATTRIBUTES as $databaseAttribute => $tableAttribute) {
                if (!isset($attributesToInherit[$tableAttribute])) {
                    continue;
                }

                if ($tableElement->hasAttribute($tableAttribute)) {
                    // do not inherit the attribute if the table defines its own
                    continue;
                }

                // add an inheritance notice
                $databaseAttributeInheritanceNoticeComment = $tableElement->ownerDocument->createComment(
                    "Attribute '{$tableAttribute}'"
                    ." inherited from parent database attribute '{$databaseAttribute}'"
                );
                $tableElement->insertBefore(
                    $databaseAttributeInheritanceNoticeComment,
                    $tableElement->firstChild
                );

                $tableElement->setAttribute($tableAttribute, $attributesToInherit[$tableAttribute]);
            }
        }
    }

    /**
     * Prefix table names with the prefix defined on the database.
     *
     * @param \DOMElement $databaseElement database element to process
     */
    protected function applyDatabaseTablePrefix(\DOMElement $databaseElement): void
    {
        if (!$databaseElement->hasAttribute('tablePrefix')) {
            return;
        }

        $tablePrefix = $databaseElement->getAttribute('tablePrefix');

        /** @var \DOMElement $tableElement */
        foreach ($databaseElement->getElementsByTagName('table') as $tableElement) {
            if (!$tableElement->hasAttribute('name')) {
                // this is probably wrong, but not our problem here - we do not validate the schema
                continue;
            }

            // add a prefixing notice
            $tablePrefixingNoticeComment = $tableElement->ownerDocument->createComment(
                "Table name prefixed with parent database 'tablePrefix'"
            );
            $tableElement->appendChild($tablePrefixingNoticeComment);

            $table = $tableElement->getAttribute('name');
            $tableElement->setAttribute('name', $tablePrefix.$table);
        }
    }

    /**
     * Get the database name from a database element.
     *
     * @param \DOMElement $databaseElement database element
     *
     * @return string database name
     *
     * @throws \LogicException if the database element is unnamed
     */
    protected function getDatabaseFromDatabaseElement(\DOMElement $databaseElement)
    {
        $database = $databaseElement->getAttribute('name');
        if (empty($database)) {
            throw new \LogicException('Unnamed database node.');
        }

        return $database;
    }

    /**
     * Create a global database element in a new document.
     *
     * @param string $database database
     */
    protected function initGlobalDatabaseElement($database): void
    {
        if (\in_array($database, $this->databases)) {
            return;
        }

        $databaseDocument = new \DOMDocument(static::$GLOBAL_SCHEMA_XML_VERSION, static::$GLOBAL_SCHEMA_XML_ENCODING);

        $databaseElement = $databaseDocument->createElement('database');

        $databaseElement->setAttribute('name', $database);

        $identifierQuotingNoticeComment = $databaseElement->ownerDocument->createComment(
            "Attribute 'identifierQuoting' generated"
        );
        $databaseElement->appendChild($identifierQuotingNoticeComment);
        $databaseElement->setAttribute('identifierQuoting', 'true');

        $databaseDocument->appendChild($databaseElement);

        $this->databases[] = $database;
        $this->globalDatabaseElements[$database] = $databaseElement;
        $this->sourceDatabaseElements[$database] = [];
        $this->externalSchemaDatabaseElements[$database] = [];
    }

    /**
     * Merge a source database element into the corresponding global database element for this database.
     *
     * @param \DOMElement $sourceDatabaseElement source database element to merge
     */
    protected function mergeDatabaseElement(\DOMElement $sourceDatabaseElement): void
    {
        $database = $this->getDatabaseFromDatabaseElement($sourceDatabaseElement);

        $this->initGlobalDatabaseElement($database);

        $globalDatabaseElement = $this->globalDatabaseElements[$database];

        // add a source schema start marker
        $fileStartMarkerComment = $globalDatabaseElement->ownerDocument->createComment(
            "Start of schema from '{$sourceDatabaseElement->ownerDocument->baseURI}'"
        );
        $globalDatabaseElement->appendChild($fileStartMarkerComment);

        // merge the element
        foreach ($sourceDatabaseElement->childNodes as $childNode) {
            $importedNode = $globalDatabaseElement->ownerDocument->importNode($childNode, true);
            $globalDatabaseElement->appendChild($importedNode);
        }

        // and a source schema end marker
        $fileEndMarkerComment = $globalDatabaseElement->ownerDocument->createComment(
            "End of schema from '{$sourceDatabaseElement->ownerDocument->baseURI}'"
        );
        $globalDatabaseElement->appendChild($fileEndMarkerComment);

        $this->sourceDatabaseElements[$database][] = $sourceDatabaseElement;
    }

    /**
     * Include an external schema into a database element.
     *
     * @param \DOMElement $externalDatabaseElement external schema database element to include
     */
    protected function includeExternalSchema(\DOMElement $externalDatabaseElement): void
    {
        $database = $this->getDatabaseFromDatabaseElement($externalDatabaseElement);

        $this->initGlobalDatabaseElement($database);

        $globalDatabaseElement = $this->globalDatabaseElements[$database];

        // add an inclusion notice
        $externalSchemaIncludeComment = $globalDatabaseElement->ownerDocument->createComment(
            'External schema included in the combining process'
        );
        $globalDatabaseElement->appendChild($externalSchemaIncludeComment);

        // include the external schema
        $externalSchemaInclude = $globalDatabaseElement->ownerDocument->createElement(
            'external-schema',
            $externalDatabaseElement->ownerDocument->baseURI
        );
        $globalDatabaseElement->appendChild($externalSchemaInclude);

        $this->externalSchemaDatabaseElements[$database][] = $externalDatabaseElement;
    }
}
