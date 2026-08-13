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

use Thelia\Model\ProductDocument;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ProductSaleElementsVirtualDocumentQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The documents a sale element is downloaded from live in their own table, reached
 * through getVirtualDocuments() and written through setVirtualDocument().
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

        $saleElement->setVirtualDocument($document->getId());

        $documents = $saleElement->getVirtualDocuments();

        self::assertCount(1, $documents);
        self::assertSame($document->getId(), $documents->getFirst()->getId());
        self::assertSame($document->getId(), $saleElement->getVirtualDocument()?->getId());
    }

    public function testTheAssociationIsRemovedWithNull(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        $saleElement->setVirtualDocument($document->getId());
        $saleElement->setVirtualDocument(null);

        self::assertNull($saleElement->getVirtualDocument());
        self::assertSame(0, $this->countAssociations($saleElement));
    }

    public function testASaleElementKeepsASingleDocument(): void
    {
        $saleElement = $this->createSaleElement();
        $first = $this->createDocument($saleElement);
        $second = $this->createDocument($saleElement);

        $saleElement->setVirtualDocument($first->getId());
        $saleElement->setVirtualDocument($second->getId());

        self::assertSame(1, $this->countAssociations($saleElement));
        self::assertSame($second->getId(), $saleElement->getVirtualDocument()?->getId());
    }

    public function testDeletingTheDocumentDropsTheAssociation(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);

        $saleElement->setVirtualDocument($document->getId());
        $document->delete();

        // The foreign key is what keeps a deleted document from leaving behind an
        // association that a sale element created later could inherit.
        self::assertSame(0, $this->countAssociations($saleElement));
        self::assertNull($saleElement->getVirtualDocument());
    }

    public function testDeletingTheSaleElementDropsTheAssociation(): void
    {
        $saleElement = $this->createSaleElement();
        $document = $this->createDocument($saleElement);
        $saleElementId = $saleElement->getId();

        $saleElement->setVirtualDocument($document->getId());
        $saleElement->delete();

        self::assertSame(
            0,
            ProductSaleElementsVirtualDocumentQuery::create()
                ->filterByProductSaleElementsId($saleElementId)
                ->count()
        );
    }

    private function countAssociations(ProductSaleElements $saleElement): int
    {
        return ProductSaleElementsVirtualDocumentQuery::create()
            ->filterByProductSaleElementsId($saleElement->getId())
            ->count();
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
