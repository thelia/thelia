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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Category;
use Thelia\Model\Coupon;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;

/**
 * An automatic promotion applies on its own: the cart matching its conditions is
 * enough. Every case here drives the real cart events, so the whole chain runs —
 * the coupons are rebuilt, the offered lines reconciled, the discount written.
 */
final class AutomaticPromotionCartTest extends ActionIntegrationPromotionTestCase
{
    public function testAPromotionAppliesAsSoonAsTheCartMeetsItsCondition(): void
    {
        $this->automaticPromotion(conditions: $this->atLeastLines(2));

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertSame(0.0, $this->cartDiscount($cart), 'One line is short of the condition.');

        $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart));
    }

    public function testRemovingAnItemTakesTheDiscountBack(): void
    {
        $this->automaticPromotion(conditions: $this->atLeastLines(2));

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());
        $lastLine = $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart));

        $this->deleteItem($cart, $lastLine);

        self::assertSame(0.0, $this->cartDiscount($cart));
    }

    public function testTheOfferedProductIsPutInTheCartWhenTheOfferIsTriggered(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $coupon = $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $offered = $this->offeredLines($cart);

        self::assertCount(1, $offered);
        self::assertSame($gift->getId(), $offered[0]->getProductId());
        self::assertSame(1, (int) $offered[0]->getIsOffered());
        self::assertSame($coupon->getId(), $offered[0]->getOfferedByCouponId());
        self::assertSame(1.0, (float) $offered[0]->getQuantity());
    }

    public function testTheOfferedLineIsNeverDuplicatedByRepeatedEvaluations(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        // What a checkout page does over and over: recompute the discount.
        $this->recomputeDiscount();
        $this->recomputeDiscount();

        self::assertCount(1, $this->offeredLines($cart));
    }

    public function testTheOfferedQuantityFollowsTheNumberOfLots(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $line = $this->addItem($cart, $trigger, quantity: 2);

        self::assertSame(1.0, (float) $this->offeredLines($cart)[0]->getQuantity());

        $this->changeItemQuantity($cart, $line, 4);

        self::assertSame(2.0, (float) $this->offeredLines($cart)[0]->getQuantity());
    }

    public function testTheOfferedLineGoesAwayWhenTheCartDropsBelowTheLot(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $line = $this->addItem($cart, $trigger, quantity: 2);

        self::assertCount(1, $this->offeredLines($cart));

        $this->changeItemQuantity($cart, $line, 1);

        self::assertSame([], $this->offeredLines($cart));
    }

    public function testAnOfferWhoseProductIsOutOfStockAddsNoLineAndIsReported(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['baseQuantity' => 0]);
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2, title: 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        self::assertSame([], $this->offeredLines($cart), 'Nothing may be offered out of thin air.');
        self::assertSame(
            ['Two bought, one offered'],
            $this->getService(OfferedCartLineService::class)->getUnavailablePromotions(),
        );
        // The cart the customer built is still theirs to check out.
        self::assertCount(1, CartItemQuery::create()->filterByCartId($cart->getId())->find());
    }

    public function testAnOfferedLineThatRunsOutOfStockIsTakenBack(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);
        self::assertCount(1, $this->offeredLines($cart));

        $this->defaultPseFor($gift)->setQuantity(0)->save();

        $this->recomputeDiscount();

        self::assertSame([], $this->offeredLines($cart));
        self::assertNotSame([], $this->getService(OfferedCartLineService::class)->getUnavailablePromotions());
    }

    public function testTheServerRefusesToChangeTheQuantityOfAnOfferedLine(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);
        $offeredLine = $this->offeredLines($cart)[0];

        $event = $this->changeItemQuantity($cart, $offeredLine, 10);

        // The listener returns before touching the line, so the event never
        // carries the updated item it would otherwise expose.
        self::assertNull($event->getCartItem());
        self::assertSame(1.0, (float) $this->reloadLine($offeredLine->getId())?->getQuantity());
    }

    public function testTheServerRefusesToDeleteAnOfferedLine(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);
        $offeredLine = $this->offeredLines($cart)[0];

        $this->deleteItem($cart, $offeredLine);

        self::assertNotNull($this->reloadLine($offeredLine->getId()));
    }

    /**
     * The offer prices what the customer actually gets: the offered line, taxes
     * included, since that is what the cart discount is stored as.
     */
    public function testTheDiscountMatchesTheOfferedLine(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 12.0]);
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $taxedGiftPrice = round(
            $this->offeredLines($cart)[0]->getRealTaxedPrice($this->getService(TaxEngine::class)->getDeliveryCountry()),
            2,
        );

        self::assertGreaterThan(12.0, $taxedGiftPrice, 'The fixture tax rule is expected to tax the gift.');
        self::assertSame($taxedGiftPrice, $this->cartDiscount($cart));
    }

    /**
     * FreeProduct is the coupon that offered a product before automatic
     * promotions existed. It works off a typed code and its own session marker,
     * and nothing here may change that.
     */
    public function testACodedFreeProductCouponStillWorks(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $coupon = $this->factory->coupon([
            'code' => 'FREEPROD-'.uniqid(),
            'type' => 'thelia.coupon.type.free_product',
            'effects' => [
                'products' => [$trigger->getId()],
                'category_id' => 0,
                'offered_product_id' => $gift->getId(),
                'offered_category_id' => 0,
            ],
            'conditions' => $this->atLeastLines(1),
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->recomputeDiscount();

        // The coupon is retained through its code, and it never marks an
        // is_offered line: that mechanism belongs to the automatic offers.
        self::assertCount(1, $this->getService(CouponManager::class)->getCouponsKept());
        self::assertSame([], $this->offeredLines($cart));
    }

    /**
     * The category scope resolves the triggering products through
     * product_category, the one branch of the offer that reads the database.
     */
    public function testALotCanBeFormedFromAWholeCategory(): void
    {
        $category = $this->factory->category();
        $gift = $this->product();
        $this->categoryOfferPromotion($category, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->productIn($category));

        self::assertSame([], $this->offeredLines($cart), 'One product of the category is short of the lot.');

        $this->addItem($cart, $this->productIn($category));

        self::assertCount(1, $this->offeredLines($cart));
    }

    public function testAProductOutsideTheCategoryDoesNotFeedTheLot(): void
    {
        $category = $this->factory->category();
        $gift = $this->product();
        $this->categoryOfferPromotion($category, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->productIn($category));
        // Belongs to the shared category of the test case, not the triggering one.
        $this->addItem($cart, $this->product());

        self::assertSame([], $this->offeredLines($cart));
    }

    /**
     * A promotion whose conditions the cart does not meet takes no part in the
     * evaluation at all: it must not, through the cumulative rule, evict the
     * coupon the customer typed in and is entitled to.
     */
    public function testANonMatchingNonCumulativePromotionNeverEvictsATypedCoupon(): void
    {
        $this->automaticPromotion(
            conditions: $this->atLeastLines(5),
            title: 'Not eligible today',
            overrides: ['isCumulative' => false],
        );
        $typed = $this->factory->coupon(['code' => 'KEEP-'.uniqid(), 'conditions' => $this->atLeastLines(1)]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $this->session()->setConsumedCoupons([$typed->getCode()]);
        $this->recomputeDiscount();

        self::assertSame(
            [$typed->getCode()],
            array_map(
                static fn ($coupon): string => $coupon->getCode(),
                $this->getService(CouponManager::class)->getCouponsKept(),
            ),
        );
        self::assertSame(5.0, $this->cartDiscount($cart));
    }

    public function testTwoCumulativeAutomaticPromotionsBothApply(): void
    {
        $this->automaticPromotion(
            effects: ['amount' => 5.0],
            conditions: $this->atLeastLines(1),
            title: 'Five off',
        );
        $this->automaticPromotion(
            effects: ['amount' => 7.0],
            conditions: $this->atLeastLines(1),
            title: 'Seven off',
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertSame(
            12.0,
            $this->cartDiscount($cart),
            'Two promotions the merchant marked as combinable must both be taken into account.',
        );
    }

    public function testACumulativeAutomaticPromotionKeepsTheCodeTheBuyerTyped(): void
    {
        $this->automaticPromotion(
            effects: ['amount' => 5.0],
            conditions: $this->atLeastLines(1),
            title: 'Five off',
        );
        $typed = $this->factory->coupon([
            'code' => 'KEEP-'.uniqid(),
            'conditions' => $this->atLeastLines(1),
            'isCumulative' => true,
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $this->session()->setConsumedCoupons([$typed->getCode()]);
        $this->recomputeDiscount();

        self::assertSame(
            10.0,
            $this->cartDiscount($cart),
            'An automatic promotion must not swallow the code the buyer typed when both are combinable.',
        );
    }

    /**
     * Signing in duplicates the cart. The offered line belongs to the promotion,
     * not to the customer: copied as a regular line it would be PAID, and the
     * reconciliation would then add the gift a second time on top of it.
     */
    public function testDuplicatingACartNeverCopiesTheOfferedLineAsAPaidOne(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);
        self::assertCount(1, $this->offeredLines($cart));

        // The duplication happens at sign-in: the session knows the customer, or the
        // duplicated cart would fail its ownership check on the next read.
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        $newCart = $cart->duplicate(null, $customer, $cart->getCurrency(), $this->dispatcher);

        self::assertInstanceOf(Cart::class, $newCart);
        self::assertCount(
            0,
            CartItemQuery::create()
                ->filterByCartId($newCart->getId())
                ->filterByProductId($gift->getId())
                ->filterByIsOffered(0)
                ->find(),
            'The gift must never turn into a line the customer pays.',
        );

        // The next evaluation puts exactly one offered line back on the new cart.
        $this->session()->setSessionCart($newCart);
        $this->recomputeDiscount();

        self::assertCount(1, $this->offeredLines($newCart));
        self::assertCount(2, CartItemQuery::create()->filterByCartId($newCart->getId())->find());
    }

    /**
     * A customer adding the very product a promotion offers wants one MORE of
     * it, paid: appending their quantity to the offered line would give it away.
     */
    public function testACustomerAddingTheOfferedProductByHandOpensAPaidLineOfItsOwn(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);
        self::assertCount(1, $this->offeredLines($cart));

        $this->addItem($cart, $gift, quantity: 1, append: true);

        $paidGiftLines = CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByProductId($gift->getId())
            ->filterByIsOffered(0)
            ->find();

        self::assertCount(1, $paidGiftLines, 'The paid unit opens a regular line of its own.');
        self::assertSame(1.0, (float) $paidGiftLines->getFirst()?->getQuantity());
        self::assertSame(1.0, (float) $this->offeredLines($cart)[0]->getQuantity(), 'The offered line is left alone.');
    }

    /**
     * The reviewer's scenario: threshold promotion, the gift pushes the total
     * back over the threshold it no longer meets. The gift must never sustain
     * the very condition that grants it.
     */
    public function testTheGiftNeverSustainsTheThresholdThatGrantsIt(): void
    {
        $main = $this->product(['basePrice' => 100.0]);
        $appoint = $this->product(['basePrice' => 20.0]);
        $gift = $this->product(['basePrice' => 40.0]);

        $cart = $this->newEmptyCart();
        $taxCountry = $this->getService(TaxEngine::class)->getDeliveryCountry();
        $mainLine = $this->addItem($cart, $main);
        $appointLine = $this->addItem($cart, $appoint);

        // A threshold the cart only meets WITH the appoint line, whatever the tax rate.
        $threshold = round(
            $mainLine->getTotalRealTaxedPrice($taxCountry) + $appointLine->getTotalRealTaxedPrice($taxCountry) - 0.01,
            2,
        );

        $this->automaticPromotion(
            type: 'thelia.coupon.type.buy_x_get_y',
            effects: [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [$main->getId()],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 1,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => $gift->getId(),
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ],
            conditions: $this->totalAtLeast($threshold),
            title: 'Spend enough, get a gift',
        );

        $this->recomputeDiscount();
        self::assertCount(1, $this->offeredLines($cart), 'Control: the threshold is met, the gift is there.');

        $this->deleteItem($cart, $appointLine);

        self::assertSame([], $this->offeredLines($cart), 'Below the threshold the promotion no longer applies, gift included.');
        self::assertSame(0.0, $this->cartDiscount($cart));
    }

    /**
     * A key can hold several offered rows when past writes went wrong: only the
     * first is the promotion's line, the supernumerary ones are corruption.
     */
    public function testASupernumeraryOfferedRowOfTheSameOfferIsCleanedUp(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $line = $this->offeredLines($cart)[0];

        $duplicate = new CartItem();
        $duplicate
            ->setCartId($cart->getId())
            ->setProductId($line->getProductId())
            ->setProductSaleElementsId($line->getProductSaleElementsId())
            ->setQuantity($line->getQuantity())
            ->setIsOffered(1)
            ->setOfferedByCouponId($line->getOfferedByCouponId())
            ->setPrice($line->getPrice())
            ->setPromoPrice($line->getPromoPrice())
            ->setPromo($line->getPromo())
            ->save();

        self::assertCount(2, $this->offeredLines($cart), 'Control: the corruption is in place.');

        $this->recomputeDiscount();

        self::assertCount(1, $this->offeredLines($cart));
        self::assertSame($line->getId(), $this->offeredLines($cart)[0]->getId(), 'The first row is the one kept.');
    }

    public function testAPartialStockGrantsWhatIsLeftInsteadOfNothing(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['baseQuantity' => 1]);
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        // Two lots ask for two offered units; the stock holds one.
        $this->addItem($cart, $trigger, quantity: 4);

        $offered = $this->offeredLines($cart);

        self::assertCount(1, $offered);
        self::assertSame(1.0, (float) $offered[0]->getQuantity());
        self::assertSame([], $this->getService(OfferedCartLineService::class)->getUnavailablePromotions());
    }

    public function testAnOfferedLineShrinksToTheRemainingStock(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 4);
        self::assertSame(2.0, (float) $this->offeredLines($cart)[0]->getQuantity());

        $this->defaultPseFor($gift)->setQuantity(1)->save();
        $this->recomputeDiscount();

        $offered = $this->offeredLines($cart);

        self::assertCount(1, $offered, 'One unit left grants one unit, not nothing.');
        self::assertSame(1.0, (float) $offered[0]->getQuantity());
        self::assertSame([], $this->getService(OfferedCartLineService::class)->getUnavailablePromotions());
    }

    /**
     * The out-of-stock announcement is written to the session on every
     * reconciliation: the page rendered by the NEXT request — the redirect after
     * the cart mutation — still has it to show.
     */
    public function testTheUnavailablePromotionsSurviveIntoTheNextRequest(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['baseQuantity' => 0]);
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2, title: 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $service = $this->getService(OfferedCartLineService::class);
        self::assertSame(['Two bought, one offered'], $service->getUnavailablePromotions());

        // The next request starts on a reset service: the state comes from the session.
        $service->reset();

        self::assertSame(['Two bought, one offered'], $service->getUnavailablePromotions());
        self::assertNotNull($cart->getId());
    }

    public function testAReconciliationThatGrantsEverythingClearsThePersistedState(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['baseQuantity' => 0]);
        $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $service = $this->getService(OfferedCartLineService::class);
        self::assertNotSame([], $service->getUnavailablePromotions(), 'Control: the shortage is recorded.');

        $this->defaultPseFor($gift)->setQuantity(10)->save();
        $this->recomputeDiscount();

        $service->reset();

        self::assertSame([], $service->getUnavailablePromotions(), 'The session holds the LAST reconciliation, nothing older.');
        self::assertCount(1, $this->offeredLines($cart));
    }

    /**
     * The announcement names the promotion in the language the visitor browses
     * in, the way the discount labels of the cart page do.
     */
    public function testTheUnavailablePromotionIsNamedInTheLanguageOfTheSession(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['baseQuantity' => 0]);
        $coupon = $this->buyXGetYPromotion($trigger, $gift, triggerQuantity: 2, title: 'Two bought, one offered');
        $coupon
            ->setLocale('fr_FR')
            ->setTitle('Deux achetés, un offert')
            ->setShortDescription('')
            ->setDescription('')
            ->save();

        $originalLang = $this->session()->getLang();
        $french = LangQuery::create()->findOneByLocale('fr_FR')
            ?? $this->factory->lang(['locale' => 'fr_FR', 'code' => 'fr', 'title' => 'Français']);
        $this->session()->setLang($french);

        try {
            $cart = $this->newEmptyCart();
            $this->addItem($cart, $trigger, quantity: 2);

            $service = $this->getService(OfferedCartLineService::class);
            $service->reset();

            self::assertSame(['Deux achetés, un offert'], $service->getUnavailablePromotions());
        } finally {
            if (null !== $originalLang) {
                $this->session()->setLang($originalLang);
            }
        }
    }

    private function totalAtLeast(float $amount): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_total_amount',
            [
                MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR_OR_EQUAL,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => $amount,
                MatchForTotalAmount::CART_CURRENCY => $this->session()->getCurrency(true)->getCode(),
            ],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    /**
     * @return list<CartItem>
     */
    private function offeredLines(Cart $cart): array
    {
        CartItemTableMap::clearInstancePool();

        return iterator_to_array(
            CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterByIsOffered(1)
                ->orderById()
                ->find(),
            false,
        );
    }

    private function reloadLine(int $cartItemId): ?CartItem
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->findPk($cartItemId);
    }

    private function cartDiscount(Cart $cart): float
    {
        $cart->reload();

        return (float) $cart->getDiscount();
    }

    private function categoryOfferPromotion(Category $category, Product $gift, int $triggerQuantity): Coupon
    {
        return $this->offerPromotion(
            BuyXGetY::TRIGGER_SCOPE_CATEGORY,
            [$category->getId()],
            $gift,
            $triggerQuantity,
            'Buy X from the category, get Y',
        );
    }

    private function buyXGetYPromotion(
        Product $trigger,
        Product $gift,
        int $triggerQuantity,
        string $title = 'Buy X get Y',
    ): Coupon {
        return $this->offerPromotion(
            BuyXGetY::TRIGGER_SCOPE_PRODUCT,
            [$trigger->getId()],
            $gift,
            $triggerQuantity,
            $title,
        );
    }

    /**
     * « One gift from eighty euros » — the scenario the category and product scopes
     * cannot express, because they hand out one gift per complete lot found in the
     * cart. The cart scope forms a single lot, so a seven-article cart gets one gift.
     */
    public function testTheWholeCartScopeOffersOneGiftWhateverTheNumberOfArticles(): void
    {
        $gift = $this->product();
        $this->offerPromotion(
            BuyXGetY::TRIGGER_SCOPE_CART,
            [],
            $gift,
            triggerQuantity: 1,
            title: 'One gift for the whole cart',
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(), quantity: 4);
        $this->addItem($cart, $this->product(), quantity: 3);

        $offered = $this->offeredLines($cart);

        self::assertCount(1, $offered, 'The whole cart is one lot, so one line is offered.');
        self::assertSame($gift->getId(), $offered[0]->getProductId());
        self::assertSame(1.0, (float) $offered[0]->getQuantity(), 'One gift, not one per article.');
    }

    public function testTheWholeCartScopeTakesTheGiftBackWhenTheCartEmpties(): void
    {
        $gift = $this->product();
        $this->offerPromotion(
            BuyXGetY::TRIGGER_SCOPE_CART,
            [],
            $gift,
            triggerQuantity: 2,
            title: 'One gift from two articles',
        );

        $cart = $this->newEmptyCart();
        $line = $this->addItem($cart, $this->product(), quantity: 2);

        self::assertCount(1, $this->offeredLines($cart));

        $this->changeItemQuantity($cart, $line, 1.0);

        self::assertCount(0, $this->offeredLines($cart), 'Below the floor, the gift goes away.');
    }

    private function offerPromotion(
        string $scope,
        array $triggerIds,
        Product $gift,
        int $triggerQuantity,
        string $title,
    ): Coupon {
        return $this->automaticPromotion(
            type: 'thelia.coupon.type.buy_x_get_y',
            effects: [
                BuyXGetY::TRIGGER_SCOPE_FIELD => $scope,
                BuyXGetY::TRIGGER_IDS_FIELD => $triggerIds,
                BuyXGetY::TRIGGER_QUANTITY_FIELD => $triggerQuantity,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => $gift->getId(),
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ],
            conditions: $this->atLeastLines(1),
            title: $title,
        );
    }

    private function atLeastLines(int $lines): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => $lines],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
