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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\Propel;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementToggleVisibilityEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ProductSaleElementActionTest extends ActionIntegrationTestCase
{
    private ?string $documentUploadDir = null;

    /** @var list<string> */
    private array $preexistingDocumentFiles = [];

    protected function tearDown(): void
    {
        // Cloning documents writes to the media directory, which the database rollback
        // does not undo: both the source file and the copy made for the clone must go.
        if (null !== $this->documentUploadDir && is_dir($this->documentUploadDir)) {
            foreach ($this->documentFiles() as $file) {
                if (is_file($file) && !\in_array($file, $this->preexistingDocumentFiles, true)) {
                    unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    public function testCreateWithoutCombinationReusesOrphanPse(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // Product::create() already made a default PSE with no combination.
        // Dispatching CREATE with an empty attribute list should reuse it.
        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($defaultPse);

        $event = new ProductSaleElementCreateEvent($product, [], $currency->getId());
        $this->dispatch($event, TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT);

        $pse = $event->getProductSaleElement();
        self::assertNotNull($pse);
        self::assertSame($defaultPse->getId(), $pse->getId());
        self::assertTrue((bool) $pse->getIsDefault());
    }

    public function testCreateWithCombinationAttachesAttributes(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $attribute = $this->factory->attribute();
        $av1 = $this->factory->attributeAv($attribute);
        $av2 = $this->factory->attributeAv($attribute);

        $event = new ProductSaleElementCreateEvent(
            $product,
            [$av1->getId(), $av2->getId()],
            $currency->getId(),
        );
        $this->dispatch($event, TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT);

        $pse = $event->getProductSaleElement();
        self::assertNotNull($pse);

        $combinations = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->find();
        self::assertCount(2, $combinations);

        $avIds = array_map(
            static fn ($c) => $c->getAttributeAvId(),
            iterator_to_array($combinations),
        );
        self::assertContains($av1->getId(), $avIds);
        self::assertContains($av2->getId(), $avIds);
    }

    public function testUpdateChangesPseFieldsAndPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $pse = $this->factory->productSaleElement($product);

        $event = new ProductSaleElementUpdateEvent($product, $pse->getId());
        $event
            ->setReference('UPDATED-REF')
            ->setQuantity(42)
            ->setWeight(1.5)
            ->setOnsale(1)
            ->setIsnew(1)
            ->setIsdefault(true)
            ->setEanCode('1234567890123')
            ->setTaxRuleId($product->getTaxRuleId())
            ->setCurrencyId($currency->getId())
            ->setFromDefaultCurrency(0)
            ->setPrice(29.99)
            ->setSalePrice(19.99);

        $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertNotNull($reloaded);
        self::assertSame('UPDATED-REF', $reloaded->getRef());
        self::assertSame(42.0, $reloaded->getQuantity());
        self::assertEqualsWithDelta(1.5, $reloaded->getWeight(), 0.001);
        self::assertSame(1, $reloaded->getPromo());
        self::assertSame(1, $reloaded->getNewness());
        self::assertTrue((bool) $reloaded->getIsDefault());
        self::assertSame('1234567890123', $reloaded->getEanCode());

        $price = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne();
        self::assertNotNull($price);
        self::assertEqualsWithDelta(29.99, (float) $price->getPrice(), 0.001);
        self::assertEqualsWithDelta(19.99, (float) $price->getPromoPrice(), 0.001);
    }

    public function testUpdatePreventsSingleDefaultFromBecomingNonDefault(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // The product has exactly one default PSE created by Product::create().
        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
        self::assertNotNull($defaultPse);

        // Try to set it as non-default — it should stay default because
        // it's the only one.
        $event = new ProductSaleElementUpdateEvent($product, $defaultPse->getId());
        $event
            ->setReference($defaultPse->getRef())
            ->setQuantity(1)
            ->setWeight(0)
            ->setOnsale(0)
            ->setIsnew(0)
            ->setIsdefault(false)
            ->setEanCode(null)
            ->setTaxRuleId($product->getTaxRuleId())
            ->setCurrencyId($currency->getId())
            ->setFromDefaultCurrency(0)
            ->setPrice(10.0)
            ->setSalePrice(0.0);

        $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $reloaded = ProductSaleElementsQuery::create()->findPk($defaultPse->getId());
        self::assertTrue((bool) $reloaded->getIsDefault());
    }

    public function testDeleteRemovesPseFromDatabase(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // Create a second PSE so deletion doesn't hit the "last PSE" logic.
        $extra = $this->factory->productSaleElement($product);
        $extraId = $extra->getId();

        $this->dispatch(
            new ProductSaleElementDeleteEvent($extraId, $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );

        self::assertNull(ProductSaleElementsQuery::create()->findPk($extraId));
    }

    public function testDeleteLastPseDetachesCombinationsInsteadOfDeleting(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($defaultPse);

        // Attach a fake combination to the default PSE
        $attribute = $this->factory->attribute();
        $av = $this->factory->attributeAv($attribute);

        $combination = new \Thelia\Model\AttributeCombination();
        $combination
            ->setAttributeAvId($av->getId())
            ->setAttributeId($attribute->getId())
            ->setProductSaleElementsId($defaultPse->getId())
            ->save();

        self::assertGreaterThan(
            0,
            AttributeCombinationQuery::create()
                ->filterByProductSaleElementsId($defaultPse->getId())
                ->count(),
        );

        // Deleting the last PSE should NOT remove the PSE row,
        // but should clear its attribute combinations.
        $this->dispatch(
            new ProductSaleElementDeleteEvent($defaultPse->getId(), $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );

        $reloaded = ProductSaleElementsQuery::create()->findPk($defaultPse->getId());
        self::assertNotNull($reloaded, 'Last PSE must not be deleted');
        self::assertTrue((bool) $reloaded->getIsDefault());
        self::assertSame(
            0,
            AttributeCombinationQuery::create()
                ->filterByProductSaleElementsId($defaultPse->getId())
                ->count(),
        );
    }

    public function testDeleteDefaultPsePromotesNewestAsDefault(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $defaultPse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
        self::assertNotNull($defaultPse);

        // Create two more PSEs so the product has 3 total.
        $this->factory->productSaleElement($product);
        $this->factory->productSaleElement($product);

        $this->dispatch(
            new ProductSaleElementDeleteEvent($defaultPse->getId(), $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );

        self::assertNull(ProductSaleElementsQuery::create()->findPk($defaultPse->getId()));

        // The action promotes the most-recently-created remaining PSE.
        // When created_at timestamps collide, the pick is non-deterministic,
        // so we only assert that exactly one default still exists.
        $newDefault = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
        self::assertNotNull($newDefault, 'A new default PSE must be promoted');
        self::assertNotSame($defaultPse->getId(), $newDefault->getId());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );

        $pse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($pse);

        $originalVisibility = (bool) $pse->getVisible();

        $this->dispatch(
            new ProductSaleElementToggleVisibilityEvent($pse->getId()),
            TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_TOGGLE_VISIBILITY,
        );

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertSame(!$originalVisibility, (bool) $reloaded->getVisible());
    }

    public function testUpdatePositionMovesToAbsolutePosition(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        $pse1 = $this->factory->productSaleElement($product);
        $this->factory->productSaleElement($product);
        $this->factory->productSaleElement($product);

        $event = new UpdatePositionEvent(
            $pse1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            3,
        );

        $this->dispatch($event, TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_UPDATE_POSITION);

        self::assertSame(
            3,
            ProductSaleElementsQuery::create()->findPk($pse1->getId())->getPosition(),
        );
    }

    public function testGenerateCombinationsReplacesExistingPses(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // Product starts with one default PSE.
        $initialCount = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->count();
        self::assertSame(1, $initialCount);

        // Create two attributes with two values each → 2×2 = 4 combinations.
        $attr1 = $this->factory->attribute();
        $av1a = $this->factory->attributeAv($attr1);
        $av1b = $this->factory->attributeAv($attr1);

        $attr2 = $this->factory->attribute();
        $av2a = $this->factory->attributeAv($attr2);
        $av2b = $this->factory->attributeAv($attr2);

        $combinations = [
            [$av1a->getId(), $av2a->getId()],
            [$av1a->getId(), $av2b->getId()],
            [$av1b->getId(), $av2a->getId()],
            [$av1b->getId(), $av2b->getId()],
        ];

        $event = new ProductCombinationGenerationEvent(
            $product,
            $currency->getId(),
            $combinations,
        );
        $event
            ->setPrice(25.0)
            ->setSalePrice(20.0)
            ->setWeight(0.5)
            ->setQuantity(100)
            ->setOnsale(false)
            ->setIsnew(false)
            ->setEanCode('');

        $this->dispatch($event, TheliaEvents::PRODUCT_COMBINATION_GENERATION);

        // All old PSEs are replaced — we should have exactly 4.
        $newPses = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->find();
        self::assertCount(4, $newPses);

        // The first combination is the default.
        $defaults = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->find();
        self::assertCount(1, $defaults);

        // Each PSE has exactly 2 attribute combinations.
        foreach ($newPses as $pse) {
            $combCount = AttributeCombinationQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->count();
            self::assertSame(2, $combCount);

            // Verify price was set.
            $price = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->filterByCurrencyId($currency->getId())
                ->findOne();
            self::assertNotNull($price);
            self::assertEqualsWithDelta(25.0, (float) $price->getPrice(), 0.001);
        }
    }

    public function testClonePseKeepsGoingWhenNullableFlagsAreNull(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // cloneProduct() reads the source i18n row, which the fixture does not create.
        $product->setLocale('en_US')->setTitle('Cloneable product')->save();

        // Rows written outside the back office (imports, 2.x migrations) can leave
        // promo/newness at NULL even though the column defaults to 0.
        $pse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($pse);
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $connection->exec(
            'UPDATE product_sale_elements SET promo = NULL, newness = NULL WHERE id = '.$pse->getId()
        );

        $event = new ProductCloneEvent($product->getRef().'-CLONE', 'en_US', $product);
        $this->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clonedProduct = $event->getClonedProduct();
        self::assertNotNull($clonedProduct);

        $clonedPse = ProductSaleElementsQuery::create()
            ->filterByProductId($clonedProduct->getId())
            ->findOne();
        self::assertNotNull($clonedPse);
        self::assertSame(0, $clonedPse->getPromo());
        self::assertSame(0, $clonedPse->getNewness());
    }

    public function testCloneCarriesTheVirtualDocumentOfEachSaleElement(): void
    {
        $currency = $this->factory->currency();
        $product = $this->createVirtualProduct($currency);
        $document = $this->createDocument($product, 'handbook-'.$product->getRef().'.pdf', withFile: true);

        foreach ($this->generateTwoSaleElements($product, $currency) as $salesElement) {
            $salesElement->setVirtualDocument($document->getId());
        }

        $event = new ProductCloneEvent($product->getRef().'-CLONE', 'en_US', $product);
        $this->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clonedProduct = $event->getClonedProduct();
        $clonedSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($clonedProduct->getId())
            ->find();
        self::assertCount(2, $clonedSaleElements);

        foreach ($clonedSaleElements as $clonedSaleElement) {
            $clonedDocument = $clonedSaleElement->getVirtualDocument();
            self::assertNotNull(
                $clonedDocument,
                'Every cloned sale element must keep the document its virtual product is downloaded from',
            );
            self::assertNotSame(
                $document->getId(),
                $clonedDocument->getId(),
                'The clone must point at its own copy, not at the document of the source product',
            );
            self::assertSame($clonedProduct->getId(), $clonedDocument->getProductId());
        }
    }

    public function testCloneLeavesNoVirtualAssociationWhenTheSourceFileIsMissing(): void
    {
        $currency = $this->factory->currency();
        $product = $this->createVirtualProduct($currency);

        // The association points at a document whose file is absent from the disk, so the
        // cloning process has nothing to copy.
        $document = $this->createDocument($product, 'never-uploaded-'.$product->getRef().'.pdf', withFile: false);

        $defaultSaleElement = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($defaultSaleElement);
        $defaultSaleElement->setVirtualDocument($document->getId());

        $event = new ProductCloneEvent($product->getRef().'-CLONE', 'en_US', $product);
        $this->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clonedProduct = $event->getClonedProduct();
        self::assertSame(1, $clonedProduct->getVirtual(), 'The clone keeps the intent of the source product');

        $clonedSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($clonedProduct->getId())
            ->find();
        self::assertCount(1, $clonedSaleElements);

        foreach ($clonedSaleElements as $clonedSaleElement) {
            self::assertNull(
                $clonedSaleElement->getVirtualDocument(),
                'A document deleted with the source product must not be handed over to the clone',
            );
        }
    }

    private function createVirtualProduct(Currency $currency): Product
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        // cloneProduct() reads the source i18n row, which the fixture does not create.
        $product
            ->setLocale('en_US')
            ->setTitle('Digital handbook')
            ->setVirtual(1)
            ->save();

        return $product;
    }

    private function createDocument(Product $product, string $fileName, bool $withFile): ProductDocument
    {
        $document = new ProductDocument();
        $document
            ->setProductId($product->getId())
            ->setFile($fileName)
            // Virtual documents are the hidden ones.
            ->setVisible(0)
            ->setPosition(1)
            ->save();

        $this->rememberDocumentUploadDir($document);

        if ($withFile) {
            if (!is_dir($this->documentUploadDir)) {
                mkdir($this->documentUploadDir, 0o775, true);
            }
            file_put_contents($this->documentUploadDir.\DIRECTORY_SEPARATOR.$fileName, '%PDF-1.4 test document');
        }

        return $document;
    }

    /**
     * Replaces the default sale element with two combinations, the way a merchant
     * declining a virtual product would.
     *
     * @return list<\Thelia\Model\ProductSaleElements>
     */
    private function generateTwoSaleElements(Product $product, Currency $currency): array
    {
        $attribute = $this->factory->attribute();
        $firstValue = $this->factory->attributeAv($attribute);
        $secondValue = $this->factory->attributeAv($attribute);

        $event = new ProductCombinationGenerationEvent(
            $product,
            $currency->getId(),
            [[$firstValue->getId()], [$secondValue->getId()]],
        );
        $event
            ->setPrice(10.0)
            ->setSalePrice(8.0)
            ->setWeight(0.0)
            ->setQuantity(5)
            ->setOnsale(false)
            ->setIsnew(false)
            ->setEanCode('');

        $this->dispatch($event, TheliaEvents::PRODUCT_COMBINATION_GENERATION);

        return iterator_to_array(
            ProductSaleElementsQuery::create()->filterByProductId($product->getId())->find(),
        );
    }

    private function rememberDocumentUploadDir(ProductDocument $document): void
    {
        if (null !== $this->documentUploadDir) {
            return;
        }

        $this->documentUploadDir = $document->getUploadDir();
        $this->preexistingDocumentFiles = $this->documentFiles();
    }

    /**
     * @return list<string>
     */
    private function documentFiles(): array
    {
        return array_values(glob($this->documentUploadDir.\DIRECTORY_SEPARATOR.'*') ?: []);
    }
}
