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

namespace Thelia\Tests\Integration\Domain\Catalog\Product;

use Propel\Runtime\Propel;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Product\Identifier\GtinDuplicateFinder;
use Thelia\Domain\Catalog\Product\Identifier\GtinViolation;
use Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException;
use Thelia\Domain\Catalog\Product\Identifier\InvalidMpnException;
use Thelia\Domain\Catalog\Product\Identifier\ProductIdentifierReader;
use Thelia\Domain\Order\Service\OrderProductFactory;
use Thelia\Domain\Order\Service\TranslationProvider;
use Thelia\Domain\Order\Service\VirtualProductContext;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleI18n;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The GTIN kept in `ean_code`, the manufacturer part number and the manufacturer brand
 * of a combination: checked wherever the row is written, carried to the clone and to the
 * order line, and read back for the feeds.
 */
final class ProductIdentifiersTest extends ActionIntegrationTestCase
{
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
    }

    public function testAValidGtinIsStoredWithoutTheSpacesAndHyphensTyped(): void
    {
        $combination = $this->combination();

        $combination->setEanCode(' 4006381-333931 ')->save();

        self::assertSame('4006381333931', $this->reloaded($combination)->getEanCode());
    }

    public function testAGtinStartingWithZeroKeepsItsLeadingZero(): void
    {
        $combination = $this->combination();

        $combination->setEanCode('036000291452')->save();

        self::assertSame('036000291452', $this->reloaded($combination)->getEanCode());
    }

    public function testAnEmptyOrAbsentCodeIsAccepted(): void
    {
        $combination = $this->combination(['eanCode' => '4006381333931']);

        $combination->setEanCode('')->save();
        self::assertSame('', $this->reloaded($combination)->getEanCode());

        $combination->setEanCode(null)->save();
        self::assertNull($this->reloaded($combination)->getEanCode());
    }

    public function testAWrongCheckDigitIsRefusedAndNothingIsWritten(): void
    {
        $combination = $this->combination(['eanCode' => '4006381333931']);

        try {
            $combination->setEanCode('4006381333932')->setQuantity(77)->save();
            self::fail('A GTIN with a wrong check digit must be refused.');
        } catch (InvalidGtinException $refusal) {
            self::assertSame(GtinViolation::CheckDigit, $refusal->violation);
            self::assertStringContainsString($combination->getRef(), $refusal->getMessage());
        }

        $stored = $this->reloaded($combination);
        self::assertSame('4006381333931', $stored->getEanCode());
        self::assertNotSame(77.0, $stored->getQuantity());
    }

    /**
     * A CSV saved from a French spreadsheet is Windows-1252: its non-breaking space
     * between digit groups is the byte 0xA0, which is not UTF-8.
     */
    public function testACodeThatIsNotUtf8IsRefusedAndKeepsTheStoredGtin(): void
    {
        $combination = $this->combination(['eanCode' => '4006381333931']);

        try {
            $combination->setEanCode("5012345678900\xA0")->save();
            self::fail('A code that is not UTF-8 must be refused.');
        } catch (InvalidGtinException $refusal) {
            self::assertSame(GtinViolation::NotDigits, $refusal->violation);
        }

        self::assertSame('4006381333931', $this->reloaded($combination)->getEanCode());
    }

    public function testACodeOfAnotherLengthIsRefused(): void
    {
        $this->expectException(InvalidGtinException::class);
        $this->expectExceptionMessage('8, 12, 13 or 14');

        $this->combination()->setEanCode('03600029145')->save();
    }

    public function testTheBackOfficeEventRefusesAnInvalidCodeAndRollsTheWholeEditBack(): void
    {
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product);

        $event = $this->updateEvent($product, $combination)->setReference('MUST-NOT-BE-SAVED')->setEanCode('4006381333932');

        try {
            $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
            self::fail('The update event must refuse an invalid GTIN.');
        } catch (InvalidGtinException) {
        }

        self::assertSame($combination->getRef(), $this->reloaded($combination)->getRef());
    }

    /**
     * The back office saves a grid row after row on the same product: a refused row must
     * not stay half-written in memory and be saved again, and refused, with the next one.
     */
    public function testARefusedRowDoesNotRefuseTheNextRowOfTheSameProduct(): void
    {
        // The shop runs with the instance pool on: the product then holds the very
        // instance of the refused row, and its next save cascades to it.
        Propel::enableInstancePooling();

        try {
            $product = $this->product();
            $refused = $this->defaultCombinationOf($product);
            $next = $this->factory->productSaleElement($product);

            try {
                $this->dispatch($this->updateEvent($product, $refused)->setEanCode('4006381333932'), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
                self::fail('The first row must be refused.');
            } catch (InvalidGtinException) {
            }

            $this->dispatch($this->updateEvent($product, $next)->setQuantity(64)->setEanCode('4006381333931'), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
        } finally {
            Propel::disableInstancePooling();
        }

        self::assertSame(64.0, $this->reloaded($next)->getQuantity());
        self::assertSame('4006381333931', $this->reloaded($next)->getEanCode());
        self::assertSame('', (string) $this->reloaded($refused)->getEanCode());
    }

    /**
     * The most delicate rule of the change: shops already hold codes that would not pass,
     * and an edit of the stock must not be blocked by a code nobody touched.
     */
    public function testAStoredInvalidCodeDoesNotBlockAnEditThatLeavesItAlone(): void
    {
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product);
        $this->storeRawCode($combination, '1234567890123');

        $event = $this->updateEvent($product, $combination)->setQuantity(55)->setEanCode('1234567890123');
        $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $stored = $this->reloaded($combination);
        self::assertSame(55.0, $stored->getQuantity());
        self::assertSame('1234567890123', $stored->getEanCode(), 'The stored code is neither erased nor rewritten.');
    }

    public function testAnEditThatLeavesThePartNumberOutKeepsIt(): void
    {
        $brand = $this->factory->brand();
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product, ['mpn' => 'MPN-KEPT', 'manufacturerBrandId' => $brand->getId()]);

        $this->dispatch($this->updateEvent($product, $combination)->setQuantity(3), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $stored = $this->reloaded($combination);
        self::assertSame('MPN-KEPT', $stored->getMpn());
        self::assertSame($brand->getId(), $stored->getManufacturerBrandId());
    }

    public function testTheEventWritesClearsThePartNumberAndTheManufacturerBrand(): void
    {
        $brand = $this->factory->brand();
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product);

        $this->dispatch(
            $this->updateEvent($product, $combination)->setMpn('  SM-G991B  ')->setManufacturerBrandId($brand->getId()),
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT,
        );
        $stored = $this->reloaded($combination);
        self::assertSame('SM-G991B', $stored->getMpn());
        self::assertSame($brand->getId(), $stored->getManufacturerBrandId());

        $this->dispatch(
            $this->updateEvent($product, $combination)->setMpn('')->setManufacturerBrandId(0),
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT,
        );
        $stored = $this->reloaded($combination);
        self::assertNull($stored->getMpn());
        self::assertNull($stored->getManufacturerBrandId());
    }

    public function testAPartNumberLongerThanTheColumnIsRefused(): void
    {
        $this->expectException(InvalidMpnException::class);

        $this->combination()->setMpn(str_repeat('A', 256))->save();
    }

    public function testTheManufacturerBrandDefaultsToTheBrandOfTheProduct(): void
    {
        $productBrand = $this->factory->brand();
        $otherBrand = $this->factory->brand();
        $product = $this->product();
        $product->setBrandId($productBrand->getId())->save();

        $inherited = $this->factory->productSaleElement($product, ['eanCode' => '4006381333931', 'mpn' => 'MPN-1']);
        $own = $this->factory->productSaleElement($product, ['manufacturerBrandId' => $otherBrand->getId()]);

        $identifiers = (new ProductIdentifierReader())->forSaleElements([$inherited->getId(), $own->getId(), 999999999]);

        self::assertCount(2, $identifiers, 'An unknown id is left out.');
        self::assertSame('4006381333931', $identifiers[$inherited->getId()]->gtin);
        self::assertSame('MPN-1', $identifiers[$inherited->getId()]->mpn);
        self::assertSame($productBrand->getId(), $identifiers[$inherited->getId()]->manufacturerBrandId);
        self::assertNull($identifiers[$own->getId()]->gtin);
        self::assertSame($otherBrand->getId(), $identifiers[$own->getId()]->manufacturerBrandId);
        self::assertSame($productBrand->getId(), $this->reloaded($inherited)->getEffectiveManufacturerBrandId());
    }

    public function testCombinationsSharingACodeAreReportedToEachOtherAndStillSaved(): void
    {
        $first = $this->factory->productSaleElement($this->product(), ['eanCode' => '9780306406157']);
        $second = $this->factory->productSaleElement($this->product(), ['eanCode' => '978-0306406157']);
        $alone = $this->factory->productSaleElement($this->product(), ['eanCode' => '4006381333931']);
        $withoutCode = $this->factory->productSaleElement($this->product());

        $finder = new GtinDuplicateFinder();
        $sharers = $finder->sharersAmong([$first->getId(), $second->getId(), $alone->getId(), $withoutCode->getId()]);

        self::assertSame([$first->getId(), $second->getId()], array_keys($sharers));
        self::assertSame($second->getId(), $sharers[$first->getId()][0]->productSaleElementsId);
        self::assertSame($first->getProduct()->getRef(), $finder->sharersOf($second->getId())[0]->productRef);
        self::assertSame([], $finder->sharersOf($alone->getId()));
        self::assertSame([], $finder->sharersOf($withoutCode->getId()));
    }

    public function testTheCloneCarriesThePartNumberTheBrandAndAValidCode(): void
    {
        $brand = $this->factory->brand();
        $product = $this->cloneableProduct();
        $this->defaultCombinationOf($product)
            ->setEanCode('4006381333931')->setMpn('MPN-CLONED')->setManufacturerBrandId($brand->getId())->save();

        $clone = $this->defaultCombinationOf($this->cloneOf($product));

        self::assertSame('4006381333931', $clone->getEanCode());
        self::assertSame('MPN-CLONED', $clone->getMpn());
        self::assertSame($brand->getId(), $clone->getManufacturerBrandId());
    }

    public function testACloneIsNotRefusedForAStoredInvalidCode(): void
    {
        $product = $this->cloneableProduct();
        $original = $this->defaultCombinationOf($product);
        $this->storeRawCode($original, '1234567890123');

        $clone = $this->defaultCombinationOf($this->cloneOf($product));

        self::assertSame('', (string) $clone->getEanCode(), 'The clone starts without the code that does not pass.');
        self::assertSame('1234567890123', $this->reloaded($original)->getEanCode(), 'The original keeps it.');
    }

    public function testTheOrderLineKeepsThePartNumberSoldWhenTheCatalogueChanges(): void
    {
        $product = $this->product();
        $combination = $this->defaultCombinationOf($product);
        $combination->setEanCode('4006381333931')->setMpn('MPN-SOLD')->save();

        $order = $this->factory->order();
        $cartItem = $this->factory->cartItem($this->factory->cart(), $product, $combination);

        $orderProduct = (new OrderProductFactory(new TranslationProvider()))->createOrderProduct(
            $order,
            $product,
            $combination,
            (new ProductI18n())->setTitle('Sold product'),
            $cartItem,
            new VirtualProductContext(false, false, null),
            new TaxRuleI18n(),
            Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME),
        );

        $combination->setEanCode('036000291452')->setMpn('MPN-RENAMED')->save();
        $orderProduct->reload();

        self::assertSame('4006381333931', $orderProduct->getEanCode());
        self::assertSame('MPN-SOLD', $orderProduct->getMpn());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function combination(array $overrides = []): ProductSaleElements
    {
        return $this->factory->productSaleElement($this->product(), $overrides);
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency);
    }

    private function cloneableProduct(): Product
    {
        $product = $this->product();
        // cloneProduct() reads the source i18n row, which the fixture does not create.
        $product->setLocale('en_US')->setTitle('Cloneable product')->save();

        return $product;
    }

    private function cloneOf(Product $product): Product
    {
        $event = new ProductCloneEvent($product->getRef().'-CLONE', 'en_US', $product);
        $this->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        return $event->getClonedProduct();
    }

    private function defaultCombinationOf(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne()
            ?? throw new \RuntimeException('The product has no combination.');
    }

    private function updateEvent(Product $product, ProductSaleElements $combination): ProductSaleElementUpdateEvent
    {
        return (new ProductSaleElementUpdateEvent($product, $combination->getId()))
            ->setReference($combination->getRef())
            ->setQuantity((float) $combination->getQuantity())
            ->setWeight((float) $combination->getWeight())
            ->setOnsale(0)
            ->setIsnew(0)
            ->setIsdefault((bool) $combination->getIsDefault())
            ->setEanCode($combination->getEanCode())
            ->setTaxRuleId((int) $product->getTaxRuleId())
            ->setCurrencyId($this->currency->getId())
            ->setFromDefaultCurrency(0)
            ->setPrice(10.0)
            ->setSalePrice(10.0);
    }

    /**
     * Writes a code the way a shop holds it from before the check: straight in the table.
     */
    private function storeRawCode(ProductSaleElements $combination, string $code): void
    {
        $statement = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME)
            ->prepare('UPDATE product_sale_elements SET ean_code = :code WHERE id = :id');
        $statement->execute(['code' => $code, 'id' => $combination->getId()]);
        ProductSaleElementsTableMap::clearInstancePool();
    }

    private function reloaded(ProductSaleElements $combination): ProductSaleElements
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return ProductSaleElementsQuery::create()->findPk($combination->getId())
            ?? throw new \RuntimeException('The combination is gone.');
    }
}
