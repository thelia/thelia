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

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Exception\PropelException;
use Thelia\Api\Bridge\Propel\Extension\ProductBundleItemVisibilityExtension;
use Thelia\Api\Resource\ProductBundleItem as ProductBundleItemResource;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Catalog\Bundle\BundleAvailability;
use Thelia\Domain\Catalog\Bundle\BundleComponentReader;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundleItemRepository;
use Thelia\Domain\Catalog\Bundle\BundlePriceCalculator;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Domain\Catalog\Bundle\Exception\BundleWriteConflictException;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductBundleItem;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The rules the review of the bundles asked for, one by one: what a bundle with nothing to
 * sell does, an optional component that is offline or reserved, the price of a composition that
 * lost a component, the unique line per sale element, and the carts of a product that changes shape.
 */
final class BundleReviewRulesTest extends ActionIntegrationTestCase
{
    use BuildsBundles;

    private BundleCompositionService $service;

    private BundleCompositionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
        $this->resolver = $this->getService(BundleCompositionResolver::class);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    // M3

    public function testABundleWithoutAnyItemIsRefusedEvenWhenTheStockIsNotChecked(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        $bundle = $this->bundleProduct($this->factory, $this->service);

        try {
            $this->resolver->resolve((int) $bundle->getId());
            self::fail('A bundle with nothing in it was sold.');
        } catch (InvalidBundleCompositionException $exception) {
            self::assertStringContainsString('no component to sell', $exception->getMessage());
        }

        self::assertSame([(int) $bundle->getId() => 0], $this->getService(BundleAvailability::class)->forBundles([(int) $bundle->getId()]));
    }

    public function testABundleWhoseDefaultCompositionIsEmptyIsRefusedUntilTheBuyerChoosesSomething(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $housse = $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($this->component($this->factory, '10', 5))->getId(), '1', '0', '2', false);

        try {
            $this->resolver->resolve((int) $bundle->getId());
            self::fail('An empty default composition was sold.');
        } catch (InvalidBundleCompositionException) {
        }

        $chosen = $this->resolver->resolve((int) $bundle->getId(), BundleComposition::fromArray([['bundleItemId' => $housse->getId(), 'quantity' => 1]]));

        self::assertSame('1', $chosen->quantityOf((int) $housse->getId()));
    }

    // M4

    public function testAnOfflineOptionalComponentWithAMinimumIsLeftOutOfTheDefaultCompositionInsteadOfSinkingTheBundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $housseProduct = $this->component($this->factory, '15', 4);
        $housse = $this->service->addItem((int) $example['bundle']->getId(), (int) $this->saleElementsOf($housseProduct)->getId(), '1', '1', '2', false);
        $bundleId = (int) $example['bundle']->getId();

        self::assertSame('1', $this->resolver->resolve($bundleId)->quantityOf((int) $housse->getId()), 'Control: online, the optional component is in at its minimum.');

        $housseProduct->setVisible(0)->save();

        $composition = $this->resolver->resolve($bundleId);

        self::assertSame('0', $composition->quantityOf((int) $housse->getId()));
        self::assertSame('1', $composition->quantityOf((int) $example['bike']->getId()));
        self::assertSame([$bundleId => 2], $this->getService(BundleAvailability::class)->forBundles([$bundleId]));
    }

    public function testAnOfflineOptionalComponentTheBuyerAsksForIsStillRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $example['lockProduct']->setVisible(0)->save();

        $this->expectException(InvalidBundleCompositionException::class);

        $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => 1],
        ]));
    }

    public function testAnOfflineRequiredComponentStillSinksTheBundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $example['bikeProduct']->setVisible(0)->save();

        $this->expectException(InvalidBundleCompositionException::class);

        $this->resolver->resolve((int) $example['bundle']->getId());
    }

    // M5

    public function testAComponentReservedToOthersCannotBeBoughtInAPublicBundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $resolver = $this->resolverHiding($example['helmetProduct']);

        try {
            $resolver->resolve((int) $example['bundle']->getId());
            self::fail('A required component reserved to others was sold.');
        } catch (InvalidBundleCompositionException $exception) {
            self::assertSame((int) $example['helmet']->getId(), $exception->bundleItemId, 'The item stays on the exception; the buyer reads no id.');
        }

        self::assertSame('2', $this->resolver->resolve((int) $example['bundle']->getId())->quantityOf((int) $example['helmet']->getId()), 'Control: for a visitor the rule lets through, the bundle is sold.');
    }

    public function testAReservedOptionalComponentIsLeftOutOfTheDefaultCompositionAndRefusedWhenAskedFor(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $housseProduct = $this->component($this->factory, '15', 4);
        $housse = $this->service->addItem((int) $example['bundle']->getId(), (int) $this->saleElementsOf($housseProduct)->getId(), '1', '1', '2', false);
        $resolver = $this->resolverHiding($housseProduct);
        $bundleId = (int) $example['bundle']->getId();

        self::assertSame('0', $resolver->resolve($bundleId)->quantityOf((int) $housse->getId()));

        $this->expectException(InvalidBundleCompositionException::class);

        $resolver->resolve($bundleId, BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $housse->getId(), 'quantity' => 1],
        ]));
    }

    public function testAComponentReservedToOthersIsNotListedByTheFrontEndpoint(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();
        $extension = new ProductBundleItemVisibilityExtension($this->visibilityHiding($example['helmetProduct']));
        $operation = new GetCollection(uriTemplate: '/front/product_bundle_items');

        $query = ProductBundleItemQuery::create()->filterByBundleProductId($bundleId);
        $extension->applyToCollection($query, ProductBundleItemResource::class, $operation);
        $listed = array_map(static fn (ProductBundleItem $item): int => (int) $item->getId(), iterator_to_array($query->find(), false));

        self::assertEqualsCanonicalizing([(int) $example['bike']->getId(), (int) $example['lock']->getId()], $listed);

        $itemQuery = ProductBundleItemQuery::create()->filterById($example['helmet']->getId());
        $extension->applyToItem($itemQuery, ProductBundleItemResource::class, new Get(uriTemplate: '/front/product_bundle_items/{id}'));

        self::assertNull($itemQuery->findOne(), 'A line read by its id answers 404 like a line that does not exist.');
    }

    // B1: the price of a composition that lost a component

    public function testAPriceIsNeverComputedWithoutAComponentThatWasDeleted(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();
        $calculator = $this->getService(BundlePriceCalculator::class);
        $composition = BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
        ]);
        self::assertSame('540.000000', $calculator->forCompositions([[$bundleId, BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => 2],
        ])]], Currency::getDefaultCurrency())[0]?->untaxedPrice, 'Control: the full composition is 540.');

        $example['helmetProduct']->delete();

        try {
            $calculator->forCompositions([[$bundleId, $composition]], Currency::getDefaultCurrency());
            self::fail('A price that left a deleted component out was answered.');
        } catch (InvalidBundleCompositionException $exception) {
            self::assertSame((int) $example['helmet']->getId(), $exception->bundleItemId, 'The item stays on the exception; the buyer reads no id.');
        }
    }

    public function testAPriceIsRefusedForAnItemTheBundleNoLongerHas(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();
        $composition = BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => 1],
        ]);

        $this->service->removeItem((int) $example['lock']->getId());

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('A component chosen is not part of this bundle.');

        $this->getService(BundlePriceCalculator::class)->forCompositions([0 => [$bundleId, $composition]], Currency::getDefaultCurrency());
    }

    public function testACatalogueListingLeavesAnUnpriceableBundleOutInsteadOfFailing(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $healthy = $this->exampleBundle($this->factory, $this->service);
        $example['helmetProduct']->delete();
        $calculator = $this->getService(BundlePriceCalculator::class);

        $prices = $calculator->forBundles([(int) $example['bundle']->getId(), (int) $healthy['bundle']->getId()], Currency::getDefaultCurrency());

        self::assertSame([(int) $healthy['bundle']->getId()], array_keys($prices));
        self::assertSame('504.000000', $prices[(int) $healthy['bundle']->getId()]->untaxedPrice);
    }

    // M7

    public function testASaleElementIsOneLineOfABundleForTheDatabaseToo(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $duplicate = (new ProductBundleItem())
            ->setBundleProductId($example['bundle']->getId())
            ->setProductSaleElementsId($example['bike']->getProductSaleElementsId())
            ->setQuantity('1');

        $this->expectException(PropelException::class);

        $duplicate->save();
    }

    public function testManyLinesWithoutASaleElementAreNotDuplicates(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();

        $example['helmetProduct']->delete();
        $example['lockProduct']->delete();
        $another = (new ProductBundleItem())->setBundleProductId($bundleId)->setProductSaleElementsId(null)->setQuantity('1');
        $another->save();

        self::assertSame(3, ProductBundleItemQuery::create()->filterByBundleProductId($bundleId)->filterByProductSaleElementsId(null)->count());
    }

    public function testTwoWritesThatBothPassedTheCheckMeetAtTheIndexAndTheSecondIsARefusal(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $duplicate = (new ProductBundleItem())
            ->setBundleProductId($example['bundle']->getId())
            ->setProductSaleElementsId($example['bike']->getProductSaleElementsId())
            ->setQuantity('1');

        $save = new \ReflectionMethod($this->service, 'saveItem');

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('already a component');

        $save->invoke($this->service, $duplicate);
    }

    // I5, on the admin side: nothing is stored that a customer could never choose or that the column cannot hold

    public function testAnItemQuantityAboveWhatACustomerMayAskForIsRefused(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $saleElements = $this->saleElementsOf($this->component($this->factory, '10', 5));
        $this->service->addItem((int) $bundle->getId(), (int) $saleElements->getId(), '1000000');

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('at most 1000000');

        $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($this->component($this->factory, '10', 5))->getId(), '1000001');
    }

    public function testADiscountThatTheColumnCannotHoldIsRefused(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);
        $this->expectExceptionMessage('too large');

        $this->service->setBundleSettings((int) $bundle->getId(), null, null, '10000000000', BundleDiscountType::Amount);
    }

    // I4

    public function testTurningAProductIntoABundleDiscardsItsLinesInEveryCartAndNothingFailsAfterwards(): void
    {
        $product = $this->component($this->factory, '25', 10);
        $other = $this->component($this->factory, '12', 10);
        $first = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $second = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $this->factory->cartItem($first, $product);
        $this->factory->cartItem($first, $other);
        $this->factory->cartItem($second, $product);

        $this->service->setBundleSettings((int) $product->getId(), true);

        self::assertSame(0, CartItemQuery::create()->filterByProductId($product->getId())->count());
        self::assertSame(1, CartItemQuery::create()->filterByProductId($other->getId())->count(), 'The lines of the other products stay.');
        $copy = $first->duplicate('switched-'.uniqid(), null, Currency::getDefaultCurrency(), $this->dispatcher);
        self::assertInstanceOf(Cart::class, $copy);
        self::assertSame([(int) $other->getId()], $this->productsOfTheLines($copy));
    }

    public function testTurningABundleBackIntoAProductDiscardsItsLinesToo(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $this->bundleLine($cart, $example['bundle'], $example);

        $this->service->setBundleSettings((int) $example['bundle']->getId(), false);

        self::assertSame(0, CartItemQuery::create()->filterByProductId($example['bundle']->getId())->count());
    }

    public function testSettingsThatDoNotSwitchTheShapeKeepTheLines(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $this->bundleLine($cart, $example['bundle'], $example);

        $this->service->setBundleSettings((int) $example['bundle']->getId(), true, null, '20');

        self::assertSame(1, CartItemQuery::create()->filterByProductId($example['bundle']->getId())->count());
    }

    public function testTheProbeOfAValidationDiscardsNothing(): void
    {
        $product = $this->component($this->factory, '25', 10);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $this->factory->cartItem($cart, $product);

        $this->service->applySettings(ProductQuery::create()->findPk($product->getId()), true, null, null, null);

        self::assertSame(1, CartItemQuery::create()->filterByProductId($product->getId())->count());
    }

    /**
     * The shape of a line is what its product sells: when something other than the service
     * switched the product, the copy of the cart skips the line and never fails on it.
     */
    public function testTheCopyOfACartSkipsALineWhoseProductChangedShapeInsteadOfFailing(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $plain = $this->component($this->factory, '25', 10);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $this->bundleLine($cart, $example['bundle'], $example);
        $this->factory->cartItem($cart, $plain);
        $this->factory->cartItem($cart, $example['bikeProduct']);

        // Written under the service, as a module or a SQL fix would.
        ProductQuery::create()->filterById($example['bundle']->getId())->update(['IsBundle' => 0]);
        ProductQuery::create()->filterById($plain->getId())->update(['IsBundle' => 1]);
        ProductTableMap::clearInstancePool();

        $copy = $cart->duplicate('shape-'.uniqid(), null, Currency::getDefaultCurrency(), $this->dispatcher, null, $this->getService(CartLineAvailability::class));

        self::assertInstanceOf(Cart::class, $copy);
        self::assertSame([(int) $example['bikeProduct']->getId()], $this->productsOfTheLines($copy));
    }

    public function testTheCopyOfACartSkipsALineWhoseCompositionIsNoLongerReadable(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $line = $this->bundleLine($cart, $example['bundle'], $example);
        CartItemQuery::create()->filterById($line->getId())->update(['BundleComposition' => '{not json']);
        CartItemTableMap::clearInstancePool();

        $copy = $cart->duplicate('unreadable-'.uniqid(), null, Currency::getDefaultCurrency(), $this->dispatcher);

        self::assertInstanceOf(Cart::class, $copy);
        self::assertSame([], $this->productsOfTheLines($copy));
    }

    public function testALineCannotBeMovedToABundleWithoutItsComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $plain = $this->component($this->factory, '25', 10);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $line = $this->factory->cartItem($cart, $plain);

        $line->setProductId($example['bundle']->getId())->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId());

        $this->expectException(InvalidBundleCompositionException::class);
        $line->save($this->getPropelConnection());
    }

    public function testABundleLineCannotBeMovedToAPlainProductKeepingItsComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $plain = $this->component($this->factory, '25', 10);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $line = $this->bundleLine($cart, $example['bundle'], $example);

        $line->setProductId($plain->getId())->setProductSaleElementsId($this->saleElementsOf($plain)->getId());

        $this->expectException(InvalidBundleCompositionException::class);
        $line->save($this->getPropelConnection());
    }

    public function testALineOfTheSameProductIsStillUpdated(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $line = $this->bundleLine($cart, $example['bundle'], $example);

        $line->setQuantity(3)->save($this->getPropelConnection());

        self::assertEqualsWithDelta(3.0, (float) CartItemQuery::create()->findPk($line->getId())?->getQuantity(), 0.0001);
    }

    public function testASecondSaleElementIsRefusedForABundleWhoeverCreatesIt(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);
        (new ProductSaleElements())->setProductId($example['bundle']->getId())->setRef('SECOND-'.uniqid())->save($this->getPropelConnection());
    }

    public function testASaleElementMovedToABundleThatHasOneIsRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $other = $this->component($this->factory, '10', 3);
        $saleElements = $this->saleElementsOf($other);

        try {
            $saleElements->setProductId($example['bundle']->getId())->save($this->getPropelConnection());
            self::fail('The bundle already has its sale element.');
        } catch (BundleDefinitionException) {
            self::assertSame((int) $other->getId(), (int) ProductSaleElementsQuery::create()->findPk($saleElements->getId())?->getProductId(), 'The sale element stays where it was.');
        }
    }

    public function testASaleElementWrittenWithoutMovingItselfIsNotJudgedAgainstItsBundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $saleElements = $this->saleElementsOf($example['bundle']);
        $saleElements->setWeight(2.5)->save($this->getPropelConnection());

        self::assertEqualsWithDelta(2.5, (float) ProductSaleElementsQuery::create()->findPk($saleElements->getId())?->getWeight(), 0.0001);
    }

    public function testALockConflictOfTheDatabaseIsABusinessRefusal(): void
    {
        $failing = new \PDOException('Deadlock found when trying to get lock');
        $failing->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
        $write = (new \ReflectionMethod(BundleCompositionService::class, 'inTransaction'));

        try {
            $write->invoke($this->service, static function () use ($failing): never {
                throw new PropelException('wrapped', 0, $failing);
            });
            self::fail('The deadlock is a refusal.');
        } catch (BundleWriteConflictException $refusal) {
            self::assertSame($failing, $refusal->getPrevious()?->getPrevious());
        }

        $other = new \RuntimeException('not a conflict');
        $this->expectExceptionObject($other);
        $write->invoke($this->service, static function () use ($other): never {
            throw $other;
        });
    }

    public function testTheMessagesTheBuyerReadsAreInTheFrenchCatalog(): void
    {
        $translator = Translator::getInstance();

        foreach ([
            'The pack %ref can no longer be ordered as it is in your cart. Please remove it and add it again.' => 'Le pack X ne peut plus être commandé tel qu\'il est dans votre panier. Merci de le retirer puis de le rajouter.',
            'Another order took the same products at the same moment. Please try again.' => 'Une autre commande a pris les mêmes produits au même moment. Merci de réessayer.',
            ReturnEligibilityChecker::BUNDLE_COMPONENT_NOT_RETURNABLE_MESSAGE => 'Cet article fait partie d\'un pack et ne peut pas être retourné seul.',
        ] as $key => $expected) {
            self::assertSame($expected, $translator->trans($key, ['%ref' => 'X'], 'core', 'fr_FR', false, false));
        }
    }

    // M9 and I6 live next to the code they prove: ProductBundleItemApiTest and OfferedCartLineServiceTest.

    /**
     * @return list<int>
     */
    private function productsOfTheLines(Cart $cart): array
    {
        CartItemTableMap::clearInstancePool();

        return array_map('intval', CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->select('ProductId')->find()->toArray());
    }

    /**
     * @param array<string, mixed> $example
     */
    private function bundleLine(Cart $cart, Product $bundle, array $example): CartItem
    {
        $line = (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($bundle->getId())
            ->setProductSaleElementsId($this->saleElementsOf($bundle)->getId())
            ->setQuantity(1)
            ->setPrice('600')
            ->setPromoPrice('540')
            ->setPromo(1)
            ->setBundleCompositionLines([
                ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => 1],
                ['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => 2],
            ]);
        $line->save($this->getPropelConnection());

        return $line;
    }

    private function resolverHiding(Product $hidden): BundleCompositionResolver
    {
        return new BundleCompositionResolver(
            $this->getService(BundleItemRepository::class),
            new BundleComponentReader($this->getService(SaleUnitAvailableStock::class), $this->visibilityHiding($hidden)),
        );
    }

    /**
     * What a module rule does, as a module declares it: the product is out of the catalogue of every visitor.
     */
    private function visibilityHiding(Product $hidden): ProductVisibility
    {
        $rule = new class((int) $hidden->getId()) implements ProductVisibilityRuleInterface {
            public function __construct(private readonly int $hiddenProductId)
            {
            }

            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return \sprintf('%s <> %d', $productIdColumn, $this->hiddenProductId);
            }
        };

        $customers = $this->getService(CurrentCustomerProvider::class);

        return new ProductVisibility(new ReservedSaleProductRule($this->getService(SaleAudienceChecker::class), $customers), $customers, [$rule]);
    }
}
