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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * ProductSaleElements::getVirtualDocuments() is the supported way to reach the
 * documents attached to a sale element. It hides the meta data the association
 * currently lives in, so that a module reading it keeps working when the storage
 * moves to its own table.
 */
final class ProductSaleElementsVirtualDocumentTest extends IntegrationTestCase
{
    public function testASaleElementWithoutAssociationHasNoDocument(): void
    {
        $saleElement = $this->createSaleElement();

        self::assertTrue($saleElement->getVirtualDocuments()->isEmpty());
        self::assertNull($saleElement->getVirtualDocument());
    }

    public function testTheAssociatedDocumentIsReturned(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        $this->associate($saleElement, $document->getId());

        $documents = $saleElement->getVirtualDocuments();

        self::assertCount(1, $documents);
        self::assertSame($document->getId(), $documents->getFirst()->getId());
        self::assertSame($document->getId(), $saleElement->getVirtualDocument()?->getId());
    }

    public function testAnAssociationLeftBehindByADeletedDocumentIsIgnored(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);
        $documentId = $document->getId();

        $this->associate($saleElement, $documentId);
        $document->delete();

        // Deleting a document leaves its meta data behind: the accessor is the place
        // that keeps a dangling id from being mistaken for a downloadable file.
        self::assertSame(
            (string) $documentId,
            (string) MetaDataQuery::getVal(
                ProductSaleElements::VIRTUAL_DOCUMENT_META_KEY,
                MetaData::PSE_KEY,
                $saleElement->getId()
            )
        );
        self::assertTrue($saleElement->getVirtualDocuments()->isEmpty());
        self::assertNull($saleElement->getVirtualDocument());
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
            ->setPosition(1)
            ->save();

        return $document;
    }

    private function associate(ProductSaleElements $saleElement, int $documentId): void
    {
        MetaDataQuery::setVal(
            ProductSaleElements::VIRTUAL_DOCUMENT_META_KEY,
            MetaData::PSE_KEY,
            $saleElement->getId(),
            $documentId
        );
    }
}
