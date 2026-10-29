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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Catalog\Bundle\BundleAvailability;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class BundleCompositionServiceTest extends ActionIntegrationTestCase
{
    use BuildsBundles;

    private BundleCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
    }

    public function testABundleCannotHoldAnotherBundle(): void
    {
        $inner = $this->bundleProduct($this->factory, $this->service);
        $outer = $this->bundleProduct($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('a bundle cannot hold another bundle');

        $this->service->addItem((int) $outer->getId(), (int) $this->saleElementsOf($inner)->getId());
    }

    public function testTheRefusalsOfTheAdminNameTheProductByItsReferenceAndNeverByItsId(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $inner = $this->bundleProduct($this->factory, $this->service);
        $plain = $this->component($this->factory, '10', 1);
        $bundleId = (int) $example['bundle']->getId();
        $bundleRef = (string) $example['bundle']->getRef();
        $bikeRef = (string) $example['bikeProduct']->getRef();
        $bikeItemId = (int) $example['bike']->getId();
        $bikeSaleElementsId = (int) $this->saleElementsOf($example['bikeProduct'])->getId();
        $otherBundleId = (int) $inner->getId();

        $refusals = [
            'a bundle as a component' => [fn () => $this->service->addItem($bundleId, (int) $this->saleElementsOf($inner)->getId()), \sprintf('Product %s is a bundle: a bundle cannot hold another bundle.', $inner->getRef())],
            'a bundle as its own component' => [fn () => $this->service->addItem($bundleId, (int) $this->saleElementsOf($example['bundle'])->getId()), \sprintf('Product %s cannot be a component of itself.', $bundleRef)],
            'a component made a bundle' => [fn () => $this->service->setBundleSettings((int) $example['bikeProduct']->getId(), true), \sprintf('Product %s is a component of a bundle: it cannot become a bundle.', $bikeRef)],
            'a component added to a plain product' => [fn () => $this->service->addItem((int) $plain->getId(), $bikeSaleElementsId), \sprintf('Product %s is not a bundle: turn it into one before adding components.', $plain->getRef())],
            'a component added twice' => [fn () => $this->service->addItem($bundleId, $bikeSaleElementsId), \sprintf('Product %s is already a component of bundle %s.', $bikeRef, $bundleRef)],
            'an item moved to another bundle' => [fn () => $this->service->updateItem($bikeItemId, $bikeSaleElementsId, '1', null, null, true, $otherBundleId), \sprintf('Component %s belongs to bundle %s and cannot be moved to another: delete it and add a new one.', $bikeRef, $bundleRef)],
            'an item given another sale element' => [fn () => $this->service->updateItem($bikeItemId, (int) $this->saleElementsOf($example['helmetProduct'])->getId(), '1', null, null, true), \sprintf('Component %s keeps its sale element: delete it and add a new one.', $bikeRef)],
        ];

        foreach ($refusals as $case => [$gesture, $expected]) {
            try {
                $gesture();
                self::fail($case.' is refused.');
            } catch (BundleDefinitionException $refusal) {
                self::assertSame($expected, $refusal->getMessage(), $case);
            }
        }
    }

    public function testABundleCannotBeItsOwnComponent(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('cannot be a component of itself');

        $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($bundle)->getId());
    }

    public function testAComponentOfABundleCannotBecomeABundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('is a component of a bundle');

        $this->service->setBundleSettings((int) $example['bikeProduct']->getId(), true);
    }

    public function testComponentsAreOnlyAddedToABundle(): void
    {
        $plain = $this->component($this->factory, '10', 1);
        $other = $this->component($this->factory, '10', 1);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('is not a bundle');

        $this->service->addItem((int) $plain->getId(), (int) $this->saleElementsOf($other)->getId());
    }

    public function testTheSameSaleElementIsNotAddedTwice(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('already a component');

        $this->service->addItem((int) $example['bundle']->getId(), (int) $this->saleElementsOf($example['bikeProduct'])->getId());
    }

    public function testAnUnknownSaleElementIsRefused(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);

        $this->service->addItem((int) $bundle->getId(), 999_999_999);
    }

    /**
     * @return iterable<string, array{string, string|null, string|null, bool}>
     */
    public static function badQuantities(): iterable
    {
        yield 'zero quantity' => ['0', null, null, true];
        yield 'negative quantity' => ['-1', null, null, true];
        yield 'text quantity' => ['many', null, null, true];
        yield 'quantity under the minimum' => ['1', '2', '3', false];
        yield 'quantity above the maximum' => ['5', '1', '3', false];
        yield 'zero maximum' => ['1', null, '0', false];
        yield 'required with a minimum of zero' => ['1', '0', '3', true];
    }

    #[DataProvider('badQuantities')]
    public function testAnIncoherentQuantityIsRefused(string $quantity, ?string $min, ?string $max, bool $required): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $component = $this->component($this->factory, '10', 1);

        $this->expectException(BundleDefinitionException::class);

        $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($component)->getId(), $quantity, $min, $max, $required);

        self::fail('unreachable');
    }

    public function testAMinimumAboveTheMaximumIsRefusedAsSuch(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $component = $this->component($this->factory, '10', 1);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('the minimum is above the maximum');

        $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($component)->getId(), '2', '3', '1', false);
    }

    public function testItemsTakeTheNextPositionAndCanBeMoved(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        self::assertSame([0, 1, 2], [$example['bike']->getPosition(), $example['helmet']->getPosition(), $example['lock']->getPosition()]);

        $this->service->updatePosition((int) $example['lock']->getId(), 0);

        self::assertSame(0, ProductBundleItemQuery::create()->findPk($example['lock']->getId())?->getPosition());
    }

    public function testAnItemIsUpdatedAndRemoved(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $updated = $this->service->updateItem((int) $example['lock']->getId(), (int) $this->saleElementsOf($example['lockProduct'])->getId(), '2', '1', '4', false);

        $reloaded = ProductBundleItemQuery::create()->findPk($updated->getId());
        self::assertSame('2.000000', $reloaded?->getQuantity());
        self::assertSame('1.000000', $reloaded?->getMinQuantity());
        self::assertSame('4.000000', $reloaded?->getMaxQuantity());

        $this->service->removeItem((int) $example['lock']->getId());

        self::assertNull(ProductBundleItemQuery::create()->findPk($example['lock']->getId()));
    }

    /**
     * The carts name an item by its id: an item that changed its sale element would sell the new
     * component under the composition chosen for the old one.
     */
    public function testAnItemKeepsItsSaleElement(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $other = $this->component($this->factory, '15', 5);

        try {
            $this->service->updateItem((int) $example['lock']->getId(), (int) $this->saleElementsOf($other)->getId(), '1', '0', '3', false);
            self::fail('The sale element of an item is not changed.');
        } catch (BundleDefinitionException $refusal) {
            self::assertStringContainsString('delete it and add a new one', $refusal->getMessage());
        }

        self::assertSame((int) $this->saleElementsOf($example['lockProduct'])->getId(), (int) ProductBundleItemQuery::create()->findPk($example['lock']->getId())?->getProductSaleElementsId());
    }

    public function testAnItemWhoseSaleElementWasDeletedIsNotGivenAnother(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        ProductSaleElementsQuery::create()->filterById($this->saleElementsOf($example['lockProduct'])->getId())->delete();
        $other = $this->component($this->factory, '15', 5);

        $this->expectException(BundleDefinitionException::class);

        $this->service->updateItem((int) $example['lock']->getId(), (int) $this->saleElementsOf($other)->getId(), '1', '0', '3', false);
    }

    public function testTheDiscountOfAComputedBundleIsKeptWithinItsType(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);

        $this->service->setBundleSettings((int) $bundle->getId(), null, BundlePriceMode::Computed, '12.5', BundleDiscountType::Percent);
        $reloaded = ProductQuery::create()->findPk($bundle->getId());

        self::assertSame('12.500000', $reloaded?->getBundleDiscount());
        self::assertSame('percent', $reloaded?->getBundleDiscountType());

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('cannot exceed 100');

        $this->service->setBundleSettings((int) $bundle->getId(), null, null, '120');
    }

    public function testDeletingTheSaleElementOfAComponentMakesTheBundleUnsellable(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();
        $availability = $this->getService(BundleAvailability::class);
        $resolver = $this->getService(BundleCompositionResolver::class);
        self::assertSame([$bundleId => 2], $availability->forBundles([$bundleId]));

        // Hard delete of the product of the helmet: its sale element goes, the line stays with a null sale element.
        $example['helmetProduct']->delete();

        $line = ProductBundleItemQuery::create()->findPk($example['helmet']->getId());
        self::assertNotNull($line, 'The line of the deleted sale element stays.');
        self::assertNull($line->getProductSaleElementsId());
        self::assertSame([$bundleId => 0], $availability->forBundles([$bundleId]));

        $this->expectException(InvalidBundleCompositionException::class);

        $resolver->resolve($bundleId);
    }

    public function testDeletingTheBundleDeletesItsLinesButNotTheComponents(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $example['bundle']->delete();

        self::assertSame(0, ProductBundleItemQuery::create()->filterByBundleProductId($example['bundle']->getId())->count());
        self::assertNotNull(ProductQuery::create()->findPk($example['bikeProduct']->getId()));
    }

    public function testTheItemsOfABundleAreCopiedForItsClone(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $clone = $this->bundleProduct($this->factory, $this->service);

        $this->service->copyItems((int) $example['bundle']->getId(), (int) $clone->getId());

        $copies = ProductBundleItemQuery::create()->filterByBundleProductId($clone->getId())->orderByPosition()->find();
        self::assertCount(3, $copies);
        self::assertSame($example['bike']->getProductSaleElementsId(), $copies[0]->getProductSaleElementsId());
        self::assertSame('3.000000', $copies[2]->getMaxQuantity());
        self::assertSame(0, (int) $copies[2]->getRequired());
    }
}
