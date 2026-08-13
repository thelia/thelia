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

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ProductSaleElementsVirtualDocumentQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.0.0-beta3.sql runs to move the virtual document association out of
 * meta_data, applied to the rows a shop upgrading from an earlier version can hold.
 *
 * The statements are read from the shipped script rather than repeated here, so the
 * test fails if the script stops doing what it claims.
 */
final class VirtualDocumentMigrationTest extends IntegrationTestCase
{
    private const MARKER = 'product_sale_elements_virtual_document (#3520)';

    public function testAnAssociationIsMovedToItsOwnTable(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        $this->writeMetaData($saleElement->getId(), (string) $document->getId());

        $this->runMigration();

        $association = ProductSaleElementsVirtualDocumentQuery::create()
            ->filterByProductSaleElementsId($saleElement->getId())
            ->findOne();

        self::assertNotNull($association);
        self::assertSame($document->getId(), $association->getProductDocumentId());
        self::assertSame(1, $association->getPosition());
        self::assertNotNull($association->getCreatedAt());
    }

    public function testTheMigratedRowsAreRemovedFromMetaData(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        $this->writeMetaData($saleElement->getId(), (string) $document->getId());
        MetaDataQuery::setVal('color', MetaData::PSE_KEY, $saleElement->getId(), 'blue');

        $this->runMigration();

        self::assertNull(MetaDataQuery::getVal('virtual', MetaData::PSE_KEY, $saleElement->getId()));
        self::assertSame('blue', MetaDataQuery::getVal('color', MetaData::PSE_KEY, $saleElement->getId()));
    }

    public function testAnAssociationPointingAtADeletedDocumentIsNotCarriedOver(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);
        $documentId = $document->getId();

        $this->writeMetaData($saleElement->getId(), (string) $documentId);
        $document->delete();

        $this->runMigration();

        self::assertSame(0, $this->countAssociations($saleElement->getId()));
    }

    public function testAnAssociationOfADeletedSaleElementIsNotCarriedOver(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        $unknownSaleElementId = ((int) ProductSaleElementsQuery::create()->orderById('desc')->findOne()?->getId()) + 1000;
        $this->writeMetaData($unknownSaleElementId, (string) $document->getId());

        $this->runMigration();

        self::assertSame(0, $this->countAssociations($unknownSaleElementId));
    }

    public function testASerializedValueIsNotCarriedOver(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        // `value` is a CLOB any module can write through MetaDataQuery::setVal(), and the
        // row says so. Its content is not the document id it happens to look like.
        $metaData = new MetaData();
        $metaData
            ->setMetaKey('virtual')
            ->setElementKey(MetaData::PSE_KEY)
            ->setElementId($saleElement->getId())
            ->setIsSerialized(true)
            ->setValue((string) $document->getId())
            ->save();

        $this->runMigration();

        self::assertSame(0, $this->countAssociations($saleElement->getId()));
    }

    public function testANonNumericValueIsNotCarriedOver(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        // MySQL casts '12-handbook.pdf' to 12, so without the digit check this row would
        // silently become an association with the document it merely starts with.
        $this->writeMetaData($saleElement->getId(), $document->getId().'-handbook.pdf');

        $this->runMigration();

        self::assertSame(0, $this->countAssociations($saleElement->getId()));
    }

    private function runMigration(): void
    {
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        foreach ($this->migrationStatements() as $statement) {
            $connection->exec($statement);
        }
    }

    /**
     * The INSERT and DELETE of the migration block, taken from the update script. The
     * CREATE TABLE is left out: the test database already carries the table, and DDL
     * would commit the transaction the test case rolls back.
     *
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.0.0-beta3.sql');

        $block = strstr((string) $script, self::MARKER);
        self::assertIsString($block, 'The 3.0.0-beta3 script no longer holds the virtual document migration.');

        $statements = [];

        foreach (explode(";\n", $block) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (preg_match('/^(INSERT|DELETE)\b/i', $sql)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(2, $statements, 'Expected the INSERT and the DELETE of the migration block.');

        return $statements;
    }

    private function countAssociations(int $saleElementId): int
    {
        return ProductSaleElementsVirtualDocumentQuery::create()
            ->filterByProductSaleElementsId($saleElementId)
            ->count();
    }

    private function writeMetaData(int $saleElementId, string $value): void
    {
        MetaDataQuery::setVal('virtual', MetaData::PSE_KEY, $saleElementId, $value);
    }

    private function createSaleElement(): ProductSaleElements
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $saleElement = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        self::assertInstanceOf(ProductSaleElements::class, $saleElement);

        return $saleElement;
    }

    private function createDocument(ProductSaleElements $saleElement): ProductDocument
    {
        $document = new ProductDocument();
        $document
            ->setProductId($saleElement->getProductId())
            ->setFile('handbook.pdf')
            ->setVisible(0)
            ->save();

        return $document;
    }
}
