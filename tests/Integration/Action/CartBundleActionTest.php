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
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Bundle\CartCompositionResolver;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Pricing\BundleEffectivePriceSource;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The cart and the bundles, with the figures of the plan: a bike (1), two helmets and up to three
 * locks, 10 % off. Two locks cost 600 and are sold 540.
 */
final class CartBundleActionTest extends ActionIntegrationTestCase
{
    use BuildsBundles;

    private BundleCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
        $this->getService(PricingActivityChecker::class)->reset();
        ConfigQuery::write('check-available-stock', '1');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        $this->getService(PricingActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testTheLineOfABundleIsPricedFromItsComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $event = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2));

        $line = $event->getCartItem();
        self::assertSame(600.0, (float) $line->getPrice(), 'The struck price is the sum of the components.');
        self::assertSame(540.0, (float) $line->getPromoPrice());
        self::assertSame(1, (int) $line->getPromo());
        self::assertSame($this->composition($example, 2)->toCanonicalJson(), $line->getBundleComposition());
    }

    public function testWithoutCompositionTheDefaultOneIsTaken(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $line = $this->add($this->cart(), $example['bundle'], 1)->getCartItem();

        // 500 + 2 x 30 = 560, 504 once 10 % are off.
        self::assertSame(560.0, (float) $line->getPrice());
        self::assertSame(504.0, (float) $line->getPromoPrice());
        self::assertNotNull($line->getBundleComposition());
    }

    public function testABundleWithoutDiscountIsNotOnPromotion(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, discount: '0');

        $line = $this->add($this->cart(), $example['bundle'], 1, $this->composition($example, 2))->getCartItem();

        self::assertSame(600.0, $line->getRealPrice());
        self::assertSame(0, (int) $line->getPromo());
    }

    public function testTheFixedPriceOfABundleIsThePriceOfItsSaleElement(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, BundlePriceMode::Fixed);

        $line = $this->add($this->cart(), $example['bundle'], 1, $this->composition($example, 2))->getCartItem();

        self::assertSame(999.0, (float) $line->getPrice());
        self::assertSame(0, (int) $line->getPromo());
    }

    public function testThePriceFollowsAChangeOfAComponentPrice(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();

        // The lock goes from 20 to 30: 500 + 60 + 2 x 30 = 620, 558 once 10 % are off.
        $price = ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementsOf($example['lockProduct'])->getId())->findOne();
        $price->setPrice('30')->setPromoPrice('30')->save();

        // Touching a cart that holds a bundle line settles that line.
        $this->change($cart, (int) $line->getId(), 2);

        $reloaded = CartItemQuery::create()->findPk($line->getId());
        self::assertSame(620.0, (float) $reloaded->getPrice());
        self::assertSame(558.0, (float) $reloaded->getPromoPrice());
    }

    public function testASimpleLineKeepsItsPriceInAShopThatHasABundlePricedByItsComponents(): void
    {
        $this->exampleBundle($this->factory, $this->service);
        $simple = $this->component($this->factory, '25', 5);
        $other = $this->component($this->factory, '12', 5);
        $cart = $this->cart();
        $line = $this->add($cart, $simple, 1)->getCartItem();
        self::assertSame(25.0, (float) $line->getPrice());

        $price = ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementsOf($simple)->getId())->findOne();
        $price->setPrice('30')->setPromoPrice('30')->save();

        // The shop sells a bundle, the cart holds none: touching it settles nothing, as it never did.
        $this->add($cart, $other, 1);

        self::assertSame(25.0, (float) CartItemQuery::create()->findPk($line->getId())->getPrice());
    }

    public function testOnlyTheBundleLinesAreSettledWhenTheCartHoldsOne(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $simple = $this->component($this->factory, '25', 5);
        $other = $this->component($this->factory, '12', 5);
        $cart = $this->cart();
        $simpleLine = $this->add($cart, $simple, 1)->getCartItem();
        $bundleLine = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();

        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementsOf($simple)->getId())->findOne()->setPrice('30')->setPromoPrice('30')->save();
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementsOf($example['lockProduct'])->getId())->findOne()->setPrice('30')->setPromoPrice('30')->save();

        $this->add($cart, $other, 1);

        self::assertSame(558.0, (float) CartItemQuery::create()->findPk($bundleLine->getId())->getPromoPrice(), 'The bundle line follows its components.');
        self::assertSame(25.0, (float) CartItemQuery::create()->findPk($simpleLine->getId())->getPrice(), 'The simple line is none of its business.');
    }

    public function testALineWhoseComponentIsGoneKeepsItsPriceAndBreaksNothing(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $other = $this->component($this->factory, '12', 5);
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();
        self::assertSame(540.0, (float) $line->getPromoPrice());

        // The lock leaves the catalog: its item stays in the bundle, without sale element.
        ProductSaleElementsQuery::create()->filterById($this->saleElementsOf($example['lockProduct'])->getId())->delete();

        // Touching the cart, then changing its currency: neither raises, the line keeps its last price.
        $this->add($cart, $other, 1);
        $this->getService(\Thelia\Action\Cart::class)->updateCartPrices($cart, Currency::getDefaultCurrency());

        $reloaded = CartItemQuery::create()->findPk($line->getId());
        self::assertSame(600.0, (float) $reloaded->getPrice());
        self::assertSame(540.0, (float) $reloaded->getPromoPrice());
    }

    public function testASourceOfPricesAnswersNullForACompositionTheBundleCannotHoldAndPricesTheOthers(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $source = $this->getService(BundleEffectivePriceSource::class);
        $bundleId = (int) $example['bundle']->getId();
        $saleElementsId = (int) $this->saleElementsOf($example['bundle'])->getId();
        $valid = $this->composition($example, 2);
        $ghost = BundleComposition::fromArray([['bundleItemId' => 999999999, 'quantity' => 1]]);

        $prices = $source->forLines([
            'valid' => [$bundleId, $saleElementsId, $valid],
            'ghost' => [$bundleId, $saleElementsId, $ghost],
        ], Currency::getDefaultCurrency());

        self::assertSame(540.0, $prices['valid']->untaxedPromoPrice);
        self::assertArrayHasKey('ghost', $prices);
        self::assertNull($prices['ghost']);
    }

    public function testAFixedBundleWhosePromotionEndsGoesBackToItsCatalogPriceLikeASimpleLine(): void
    {
        $this->assertTheOfferOfAFixedBundleEndsWithTheOneOfItsSaleElement();
    }

    public function testAFixedBundleWhosePromotionEndsGoesBackToItsCatalogPriceInAShopThatAlsoComputesBundles(): void
    {
        $this->exampleBundle($this->factory, $this->service);
        self::assertTrue($this->getService(PricingActivityChecker::class)->hasComputedBundles());

        $this->assertTheOfferOfAFixedBundleEndsWithTheOneOfItsSaleElement();
    }

    private function assertTheOfferOfAFixedBundleEndsWithTheOneOfItsSaleElement(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, BundlePriceMode::Fixed);
        $bundleSaleElements = $this->saleElementsOf($example['bundle']);
        $bundleSaleElements->setPromo(1)->save();
        ProductPriceQuery::create()->filterByProductSaleElementsId($bundleSaleElements->getId())->findOne()->setPromoPrice('799')->save();
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();
        self::assertSame(1, (int) $line->getPromo());
        self::assertSame(799.0, (float) $line->getPromoPrice());

        // The promotion of the sale element of the bundle is over; the visitor touches the cart, in
        // a later request, which reads the cart again.
        $bundleSaleElements->setPromo(0)->save();
        $this->add(CartQuery::create()->findPk($cart->getId()), $this->component($this->factory, '12', 5), 1);

        $reloaded = CartItemQuery::create()->findPk($line->getId());
        self::assertSame(0, (int) $reloaded->getPromo(), 'A fixed bundle is settled as a simple line: its promotion ends with the one of its sale element.');
        self::assertSame(999.0, (float) $reloaded->getPrice());
    }

    public function testALineHoldingACompositionIsPricedFromItsComponentsWhileTheCatalogStateLagsBehind(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $source = $this->getService(BundleEffectivePriceSource::class);
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();
        self::assertSame(540.0, (float) $line->getPromoPrice());

        $this->catalogStateSaysNoBundle();

        $prices = $source->forLines([0 => [(int) $example['bundle']->getId(), (int) $this->saleElementsOf($example['bundle'])->getId(), $this->composition($example, 2)]], Currency::getDefaultCurrency());
        self::assertSame(540.0, $prices[0]?->untaxedPromoPrice, 'A composition says the line is a bundle: its price never waits for the state of the catalog.');

        // The lock goes from 20 to 30, the visitor touches the line: 620, 558 once 10 % are off.
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementsOf($example['lockProduct'])->getId())->findOne()->setPrice('30')->setPromoPrice('30')->save();
        $this->change($cart, (int) $line->getId(), 2);

        $reloaded = CartItemQuery::create()->findPk($line->getId());
        self::assertSame(620.0, (float) $reloaded->getPrice());
        self::assertSame(558.0, (float) $reloaded->getPromoPrice());
    }

    public function testACompositionIsResolvedWhileTheCatalogStateLagsBehind(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->catalogStateSaysNoBundle();

        $resolved = $this->getService(CartCompositionResolver::class)->resolveAndHandOver((int) $example['bundle']->getId(), $this->composition($example, 2));

        self::assertSame($this->composition($example, 2)->toCanonicalJson(), $resolved?->toCanonicalJson(), 'The bundle is judged on its data, not refused as a plain product.');
    }

    public function testThePriceOfABundleWithoutDiscountFollowsAComponentPriceToo(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, discount: '0');
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();
        self::assertSame(0, (int) $line->getPromo());

        $price = ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementsOf($example['lockProduct'])->getId())->findOne();
        $price->setPrice('30')->setPromoPrice('30')->save();
        $this->change($cart, (int) $line->getId(), 2);

        self::assertSame(620.0, (float) CartItemQuery::create()->findPk($line->getId())->getPrice());
    }

    public function testAnItemOfAnotherBundleIsRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $other = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $falsified = BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $other['lock']->getId(), 'quantity' => 1],
        ]);

        $this->expectException(InvalidBundleCompositionException::class);

        try {
            $this->add($cart, $example['bundle'], 1, $falsified);
        } finally {
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        }
    }

    public function testAQuantityOutOfBoundsIsRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $this->expectException(InvalidBundleCompositionException::class);

        try {
            $this->add($cart, $example['bundle'], 1, $this->composition($example, 4));
        } finally {
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        }
    }

    public function testARequiredComponentLeftOutIsRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $withoutTheBike = BundleComposition::fromArray([
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
        ]);

        $this->expectException(InvalidBundleCompositionException::class);

        try {
            $this->add($cart, $example['bundle'], 1, $withoutTheBike);
        } finally {
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        }
    }

    public function testAProductThatIsNotABundleRefusesAComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $this->expectException(InvalidBundleCompositionException::class);

        try {
            $this->add($cart, $example['bikeProduct'], 1, $this->composition($example, 2));
        } finally {
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        }
    }

    public function testARequiredComponentOutOfStockRefusesTheAddition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['helmetProduct'])->setQuantity(1)->save();
        $cart = $this->cart();

        $this->expectException(NotEnoughStockException::class);

        try {
            $this->add($cart, $example['bundle'], 1);
        } finally {
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        }
    }

    public function testTheStockOfTheSaleElementOfTheBundleIsNeverRead(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        // The sale element of the bundle claims a plenty and the components have none.
        $this->saleElementsOf($example['bundle'])->setQuantity(100)->save();
        $this->saleElementsOf($example['bikeProduct'])->setQuantity(0)->save();
        $cart = $this->cart();

        $this->expectException(NotEnoughStockException::class);

        $this->add($cart, $example['bundle'], 1);
    }

    public function testTheBundleIsAddedFromTheStockOfItsComponentsWhateverItsOwnStock(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        self::assertSame(0.0, (float) $this->saleElementsOf($example['bundle'])->getQuantity());

        $line = $this->add($this->cart(), $example['bundle'], 2, $this->composition($example, 2))->getCartItem();

        self::assertSame(2.0, (float) $line->getQuantity());
    }

    public function testAnAdditionBeyondTheAvailabilityIsRefusedOutLoudAndTheQuantityStays(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();
        $composition = $this->composition($example, 2);
        // min(7, floor(5 / 2), floor(10 / 2)) = 2
        $line = $this->add($cart, $example['bundle'], 2, $composition)->getCartItem();

        try {
            $this->add($cart, $example['bundle'], 1, $composition);
            self::fail('The third bundle must be refused.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(2.0, (float) CartItemQuery::create()->findPk($line->getId())->getQuantity(), 'Nothing is capped silently.');
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testANewLineBeyondTheAvailabilityIsRefusedWhole(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $this->expectException(NotEnoughStockException::class);

        try {
            $this->add($cart, $example['bundle'], 3, $this->composition($example, 2));
        } finally {
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        }
    }

    public function testTheShopThatDoesNotCheckTheStockAcceptsTheQuantity(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        $example = $this->exampleBundle($this->factory, $this->service);

        $line = $this->add($this->cart(), $example['bundle'], 9, $this->composition($example, 2))->getCartItem();

        self::assertSame(9.0, (float) $line->getQuantity());
    }

    public function testAQuantityChangeBeyondTheAvailabilityIsRefusedOutLoud(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();

        try {
            $this->change($cart, (int) $line->getId(), 3);
            self::fail('Three bundles are more than the components supply.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(1.0, (float) CartItemQuery::create()->findPk($line->getId())->getQuantity());

        $this->change($cart, (int) $line->getId(), 2);
        self::assertSame(2.0, (float) CartItemQuery::create()->findPk($line->getId())->getQuantity());
    }

    public function testTwoCompositionsAreTwoLines(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $first = $this->add($cart, $example['bundle'], 1, $this->composition($example, 1))->getCartItem();
        $second = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();
        $again = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();

        self::assertNotSame($first->getId(), $second->getId());
        self::assertSame($second->getId(), $again->getId(), 'The same composition joins its line.');
        self::assertSame(2.0, (float) CartItemQuery::create()->findPk($second->getId())->getQuantity());
        self::assertSame(2, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testADefaultAdditionJoinsALineHoldingTheDefaultComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();

        $first = $this->add($cart, $example['bundle'], 1)->getCartItem();
        $second = $this->add($cart, $example['bundle'], 1)->getCartItem();

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(2.0, (float) CartItemQuery::create()->findPk($first->getId())->getQuantity());
    }

    public function testASimpleProductIsStrictlyUnchanged(): void
    {
        $product = $this->component($this->factory, '12.5', 3);
        $cart = $this->cart();

        $line = $this->add($cart, $product, 2)->getCartItem();

        self::assertNull($line->getBundleComposition());
        self::assertSame(12.5, (float) $line->getPrice());
        self::assertSame(2.0, (float) $line->getQuantity());

        // The silent cap of an addition to a line, as before.
        $capped = $this->add($cart, $product, 5)->getCartItem();
        self::assertSame($line->getId(), $capped->getId());
        self::assertSame(2.0, (float) CartItemQuery::create()->findPk($line->getId())->getQuantity());
    }

    public function testADuplicatedCartKeepsTheCompositionAndThePrice(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();
        $line = $this->add($cart, $example['bundle'], 1, $this->composition($example, 2))->getCartItem();
        $cart->clearCartItems();

        $copy = $cart->duplicate('duplicated-'.uniqid(), null, Currency::getDefaultCurrency(), $this->dispatcher, null, $this->getService(CartLineAvailability::class));

        self::assertInstanceOf(Cart::class, $copy);
        $copied = CartItemQuery::create()->filterByCartId($copy->getId())->find();
        self::assertCount(1, $copied);
        self::assertSame($this->composition($example, 2)->toCanonicalJson(), $copied[0]->getBundleComposition());
        self::assertNotSame($line->getId(), $copied[0]->getId());
    }

    public function testTheCartDuplicatedByTheShopPricesABundleLineFromItsComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();
        $this->add($cart, $example['bundle'], 1, $this->composition($example, 2));
        $cart->clearCartItems();
        $action = $this->getService(\Thelia\Action\Cart::class);

        $dispatcher = $this->dispatcher;
        $copy = \Closure::bind(fn (): Cart => $this->duplicateCart($dispatcher, $cart), $action, $action::class)();

        $copied = CartItemQuery::create()->filterByCartId($copy->getId())->find();
        self::assertCount(1, $copied);
        self::assertSame(600.0, (float) $copied[0]->getPrice(), 'Priced from its composition, not from the sale element of the bundle.');
        self::assertSame(540.0, (float) $copied[0]->getPromoPrice());
    }

    public function testADuplicatedCartDropsABundleTheComponentsCannotSupplyAnyMore(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $cart = $this->cart();
        $this->add($cart, $example['bundle'], 2, $this->composition($example, 2));
        $cart->clearCartItems();
        $this->saleElementsOf($example['bikeProduct'])->setQuantity(1)->save();

        $copy = $cart->duplicate('duplicated-'.uniqid(), null, Currency::getDefaultCurrency(), $this->dispatcher, null, $this->getService(CartLineAvailability::class));

        self::assertSame(0, CartItemQuery::create()->filterByCartId($copy->getId())->count());
    }

    public function testTheWeightIsTheWeightOfTheRetainedComponentsWhenTheBundleHasNone(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['bikeProduct'])->setWeight(10)->save();
        $this->saleElementsOf($example['helmetProduct'])->setWeight(1)->save();
        $this->saleElementsOf($example['lockProduct'])->setWeight(0.5)->save();
        $cart = $this->cart();
        $this->add($cart, $example['bundle'], 2, $this->composition($example, 2));
        $cart->clearCartItems();

        // One bundle: 10 + 2 x 1 + 2 x 0.5 = 13, and two of them.
        self::assertEqualsWithDelta(26.0, $cart->getWeight(), 0.0001);
    }

    public function testABundleLineAPromotionOffersWeighsItsDefaultComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['bikeProduct'])->setWeight(10)->save();
        $this->saleElementsOf($example['helmetProduct'])->setWeight(1)->save();
        $this->saleElementsOf($example['lockProduct'])->setWeight(0.5)->save();
        $cart = $this->cart();
        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bundle']->getId())
            ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity(2)
            ->setPrice('600')
            ->setPromoPrice('540')
            ->setPromo(1)
            ->setIsOffered(1)
            ->save($this->getPropelConnection());
        $cart->clearCartItems();

        // The line has no composition: it weighs what the default one does, 10 + 2 x 1 = 12, and two of them.
        self::assertEqualsWithDelta(24.0, $cart->getWeight(), 0.0001);
    }

    public function testTheWeightOfTheSaleElementOfTheBundleWinsWhenItWeighsSomething(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['bundle'])->setWeight(5)->save();
        $this->saleElementsOf($example['bikeProduct'])->setWeight(10)->save();
        $cart = $this->cart();
        $this->add($cart, $example['bundle'], 2, $this->composition($example, 2));
        $cart->clearCartItems();

        self::assertEqualsWithDelta(10.0, $cart->getWeight(), 0.0001);
    }

    public function testAnOptionalComponentTheBuyerLeftOutDoesNotWeigh(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['bikeProduct'])->setWeight(10)->save();
        $this->saleElementsOf($example['lockProduct'])->setWeight(3)->save();
        $cart = $this->cart();
        $this->add($cart, $example['bundle'], 1, $this->composition($example, 0));
        $cart->clearCartItems();

        self::assertEqualsWithDelta(10.0, $cart->getWeight(), 0.0001);
    }

    public function testACartOfBundlesOfVirtualComponentsIsVirtual(): void
    {
        $bike = $this->component($this->factory, '500', 7)->setVirtual(1);
        $bike->save();
        $helmet = $this->component($this->factory, '30', 5)->setVirtual(1);
        $helmet->save();
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $bikeItem = $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($bike)->getId(), '1');
        $helmetItem = $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($helmet)->getId(), '2');
        $cart = $this->cart();

        $this->add($cart, $bundle, 1, BundleComposition::fromArray([
            ['bundleItemId' => $bikeItem->getId(), 'quantity' => 1],
            ['bundleItemId' => $helmetItem->getId(), 'quantity' => 2],
        ]));
        $cart->clearCartItems();

        self::assertTrue($cart->isVirtual());
    }

    public function testACartOfABundleHoldingARealComponentIsNotVirtual(): void
    {
        $bike = $this->component($this->factory, '500', 7)->setVirtual(1);
        $bike->save();
        $helmet = $this->component($this->factory, '30', 5);
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $bikeItem = $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($bike)->getId(), '1');
        $helmetItem = $this->service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($helmet)->getId(), '2');
        $cart = $this->cart();

        $this->add($cart, $bundle, 1, BundleComposition::fromArray([
            ['bundleItemId' => $bikeItem->getId(), 'quantity' => 1],
            ['bundleItemId' => $helmetItem->getId(), 'quantity' => 2],
        ]));
        $cart->clearCartItems();

        self::assertFalse($cart->isVirtual());
    }

    public function testAProductWithSeveralSaleElementsCannotBecomeABundle(): void
    {
        $product = $this->component($this->factory, '10', 5);
        $this->factory->productSaleElement($product);

        $this->expectException(BundleDefinitionException::class);

        $this->service->setBundleSettings((int) $product->getId(), true);
    }

    public function testASaleElementCannotBeCreatedForABundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        // The only sale element of the bundle carries a combination: creating one would add another.
        $attributeAv = $this->factory->attributeAv($this->factory->attribute());
        $this->factory->attributeCombination($this->saleElementsOf($example['bundle']), $attributeAv);
        $otherAttributeAv = $this->factory->attributeAv($this->factory->attribute());

        $this->expectException(BundleDefinitionException::class);

        $this->dispatcher->dispatch(
            new ProductSaleElementCreateEvent($example['bundle'], [$otherAttributeAv->getId()], (int) Currency::getDefaultCurrency()->getId()),
            TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT,
        );
    }

    public function testSeveralCombinationsCannotBeGeneratedForABundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);

        $this->dispatcher->dispatch(
            new ProductCombinationGenerationEvent($example['bundle'], (int) Currency::getDefaultCurrency()->getId(), [[1], [2]]),
            TheliaEvents::PRODUCT_COMBINATION_GENERATION,
        );
    }

    public function testNoQueryIsAskedPerPageOrPerRowWhileNoBundleIsPricedByItsComponents(): void
    {
        $products = [$this->component($this->factory, '10', 5), $this->component($this->factory, '20', 5), $this->component($this->factory, '30', 5)];
        $saleElementsIds = array_map(fn ($product): int => (int) $this->saleElementsOf($product)->getId(), $products);
        $checker = $this->getService(PricingActivityChecker::class);
        $catalog = $this->getService(EffectivePriceCatalog::class);
        $source = $this->getService(BundleEffectivePriceSource::class);
        $checker->reset();
        $connection = Propel::getConnection('TheliaMain');
        $connection->useDebug(true);

        $before = $connection->getQueryCount();
        self::assertFalse($checker->hasComputedBundles());
        $existence = $connection->getQueryCount() - $before;

        // The page: the catalog of prices and the source of the bundles. Whatever the shop rules
        // cost the first time, the second ask - another page, another row - costs nothing.
        $source->forSaleElements($saleElementsIds, Currency::getDefaultCurrency());
        $catalog->warm($saleElementsIds, Currency::getDefaultCurrency(), null);
        $before = $connection->getQueryCount();
        self::assertSame([], $source->forSaleElements(\array_slice($saleElementsIds, 0, 1), Currency::getDefaultCurrency()));
        $catalog->warm($saleElementsIds, Currency::getDefaultCurrency(), null);
        $page = $connection->getQueryCount() - $before;
        $connection->useDebug(false);

        self::assertSame(0, $existence, 'Read from the configuration: the catalog state is no query.');
        self::assertSame(0, $page, 'Neither the page nor a row asks anything more.');
    }

    /**
     * What a request reads while the write that made the first bundle is not committed yet, or
     * from a configuration cache rebuilt before that commit.
     */
    private function catalogStateSaysNoBundle(): void
    {
        ConfigQuery::write(BundleCatalogState::CONFIG_KEY, '0');
        self::assertFalse($this->getService(PricingActivityChecker::class)->hasComputedBundles());
    }

    private function cart(): Cart
    {
        return $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
    }

    private function composition(array $example, int $locks): BundleComposition
    {
        return BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => $locks],
        ]);
    }

    private function add(Cart $cart, \Thelia\Model\Product $product, int $quantity, ?BundleComposition $composition = null): CartEvent
    {
        $event = (new CartEvent($cart))
            ->setProductId((int) $product->getId())
            ->setProductSaleElementsId((int) ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne()->getId())
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true)
            ->setBundleComposition($composition);

        return $this->dispatch($event, TheliaEvents::CART_ADDITEM);
    }

    private function change(Cart $cart, int $lineId, int $quantity): CartEvent
    {
        $event = (new CartEvent($cart))->setCartItemId($lineId)->setQuantity($quantity);

        return $this->dispatch($event, TheliaEvents::CART_UPDATEITEM);
    }
}
