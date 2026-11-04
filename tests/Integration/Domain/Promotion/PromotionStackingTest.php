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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Selection\PromotionDiscardReason;
use Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter;
use Thelia\Domain\Promotion\Coupon\Service\CouponFreeShippingEvaluator;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Service\OrderCouponUsageManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Cart;
use Thelia\Model\Coupon;
use Thelia\Model\OrderCouponQuery;

/**
 * Which promotions apply together: priority, stop, exclusions, and what the selection feeds
 * (the discount, the free postage, the usages counted, the attribute the cart page reads).
 */
final class PromotionStackingTest extends ActionIntegrationPromotionTestCase
{
    private CouponManager $couponManager;

    protected function setUp(): void
    {
        parent::setUp();

        AttributeAccessService::clearCache();
        $this->couponManager = $this->getService(CouponManager::class);
    }

    public function testThePriorityAndTheStopFlagOfThePromotionReachTheBuiltCoupon(): void
    {
        $this->typedPromotion('HYDRATED', ['priority' => 7, 'stopProcessing' => true]);
        $this->typedPromotion('PLAIN');

        $factory = $this->getService(CouponFactory::class);

        $hydrated = $factory->buildCouponFromCode('HYDRATED');
        $plain = $factory->buildCouponFromCode('PLAIN');

        self::assertSame(7, $hydrated->getPriority());
        self::assertTrue($hydrated->isStopProcessing());
        self::assertSame(0, $plain->getPriority());
        self::assertFalse($plain->isStopProcessing());
    }

    public function testTwoCodesGiveTheSamePromotionsWhateverTheOrderTheyWereTypedIn(): void
    {
        $tenth = $this->typedPromotion('TEN', ['priority' => 10, 'stopProcessing' => true, 'effects' => ['amount' => 10.0]]);
        $twentieth = $this->typedPromotion('TWENTY', ['priority' => 20, 'effects' => ['amount' => 20.0]]);
        $this->cartWithAProduct();

        foreach ([[$tenth, $twentieth], [$twentieth, $tenth]] as $typedOrder) {
            $this->type(...$typedOrder);

            self::assertSame([$tenth->getCode()], $this->keptCodes());
            self::assertSame(10.0, $this->couponManager->getDiscount());

            $discarded = $this->couponManager->getSelection()->discarded;
            self::assertCount(1, $discarded);
            self::assertSame($twentieth->getCode(), $discarded[0]->coupon->getCode());
            self::assertSame(PromotionDiscardReason::Stopped, $discarded[0]->reason);
            self::assertSame($tenth->getCode(), $discarded[0]->winner->getCode());
        }
    }

    public function testTwoIncompatibleCodesKeepOnlyTheOneRankedFirst(): void
    {
        $first = $this->typedPromotion('FIRST', ['priority' => 10, 'effects' => ['amount' => 10.0]]);
        $second = $this->typedPromotion('SECOND', ['priority' => 20, 'effects' => ['amount' => 20.0]]);
        $this->getService(CouponExclusionWriter::class)->replace($first->getId(), [$second->getId()]);
        $this->cartWithAProduct();

        foreach ([[$first, $second], [$second, $first]] as $typedOrder) {
            $this->type(...$typedOrder);

            self::assertSame([$first->getCode()], $this->keptCodes());
            self::assertSame(10.0, $this->couponManager->getDiscount());
            self::assertSame(PromotionDiscardReason::Excluded, $this->couponManager->getSelection()->discarded[0]->reason);
        }
    }

    public function testACumulativeCodeAndTheAutomaticPromotionsStillAddUpWithAllPrioritiesAtZero(): void
    {
        $typed = $this->typedPromotion('TYPED', ['isCumulative' => true, 'effects' => ['amount' => 3.0]]);
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->everyone());
        $this->automaticPromotion(effects: ['amount' => 7.0], conditions: $this->everyone());
        $this->cartWithAProduct();
        $this->type($typed);

        self::assertSame(15.0, $this->couponManager->getDiscount());
        self::assertSame([], $this->couponManager->getSelection()->discarded);
    }

    /**
     * With every priority at 0, one code and the automatic promotions give the discount of the algorithm
     * the shops had before the priorities (CouponManager::sortCoupons, copied in legacyDiscount()), whatever
     * the cumulative flag of each one and whichever was created first, two automatic ones applying alone included.
     *
     * @return iterable<string, array{bool, list<bool>, bool}>
     */
    public static function legacyDiscountCases(): iterable
    {
        foreach ([true, false] as $codeCumulative) {
            foreach ([[true], [false], [true, true], [false, true], [true, false], [false, false]] as $automatics) {
                foreach ([true, false] as $codeCreatedFirst) {
                    yield \sprintf(
                        'code %s + automatics [%s], code created %s',
                        $codeCumulative ? 'cumulative' : 'alone',
                        implode(',', array_map(static fn (bool $cumulative): string => $cumulative ? 'c' : 'nc', $automatics)),
                        $codeCreatedFirst ? 'first' : 'last',
                    ) => [$codeCumulative, $automatics, $codeCreatedFirst];
                }
            }
        }
    }

    /**
     * @param list<bool> $automaticsCumulative
     */
    #[DataProvider('legacyDiscountCases')]
    public function testWithAllPrioritiesAtZeroOneCodeAndAutomaticPromotionsKeepTheLegacyDiscount(bool $codeCumulative, array $automaticsCumulative, bool $codeCreatedFirst): void
    {
        $typed = null;

        if ($codeCreatedFirst) {
            $typed = $this->typedPromotion('TYPED', ['isCumulative' => $codeCumulative, 'effects' => ['amount' => 3.0]]);
        }

        $amounts = [5.0, 7.0];

        foreach ($automaticsCumulative as $index => $cumulative) {
            $this->automaticPromotion(effects: ['amount' => $amounts[$index]], conditions: $this->everyone(), overrides: ['isCumulative' => $cumulative]);
        }

        $typed ??= $this->typedPromotion('TYPED', ['isCumulative' => $codeCumulative, 'effects' => ['amount' => 3.0]]);
        $this->cartWithAProduct();
        $this->type($typed);

        // The previous algorithm was fed the codes typed first, then the automatic promotions by id.
        $fed = [[3.0, $codeCumulative]];

        foreach ($automaticsCumulative as $index => $cumulative) {
            $fed[] = [$amounts[$index], $cumulative];
        }

        self::assertSame(self::legacyDiscount($fed), $this->couponManager->getDiscount());
    }

    public function testTwoAutomaticPromotionsOneOfThemAloneKeepTheLegacyDiscount(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->everyone(), overrides: ['isCumulative' => false]);
        $this->automaticPromotion(effects: ['amount' => 7.0], conditions: $this->everyone());
        $this->cartWithAProduct();
        $this->couponManager->invalidateCurrentCoupons();

        // The last non cumulative one used to win, and a cumulative one after it replaced it.
        self::assertSame(7.0, self::legacyDiscount([[5.0, false], [7.0, true]]));
        self::assertSame(7.0, $this->couponManager->getDiscount());
    }

    public function testTwoAutomaticPromotionsWithAPriorityKeepTheNewOrder(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->everyone(), overrides: ['isCumulative' => false, 'priority' => 1]);
        $this->automaticPromotion(effects: ['amount' => 7.0], conditions: $this->everyone(), overrides: ['priority' => 1]);
        $this->cartWithAProduct();
        $this->couponManager->invalidateCurrentCoupons();

        self::assertSame(5.0, $this->couponManager->getDiscount());
    }

    /**
     * @param list<array{float, bool}> $fed [amount, cumulative]
     */
    private static function legacyDiscount(array $fed): float
    {
        $kept = [];

        foreach ($fed as $promotion) {
            $kept = $promotion[1] && [] !== $kept && $kept[0][1] ? [...$kept, $promotion] : [$promotion];
        }

        return array_sum(array_column($kept, 0));
    }

    public function testADiscardedPromotionIsNeverRecordedOnTheOrderNorCounted(): void
    {
        $winner = $this->typedPromotion('WIN', ['priority' => 1, 'maxUsage' => 5, 'stopProcessing' => true]);
        $loser = $this->typedPromotion('LOSE', ['priority' => 2, 'maxUsage' => 5]);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->cartWithAProduct();
        $this->type($loser, $winner);

        $order = $this->factory->order($customer);
        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        self::assertSame(
            [$winner->getCode()],
            array_map(
                static fn ($orderCoupon): string => (string) $orderCoupon->getCode(),
                iterator_to_array(OrderCouponQuery::create()->filterByOrderId($order->getId())->find(), false),
            ),
        );

        $this->getService(OrderCouponUsageManager::class)->consume($order);

        $winner->reload();
        $loser->reload();

        self::assertSame(4, $winner->getMaxUsage());
        self::assertSame(5, $loser->getMaxUsage());
    }

    public function testTheFreePostageFollowsTheSelectionOnBothSides(): void
    {
        $other = $this->typedPromotion('OTHER', ['priority' => 1]);
        $freePostage = $this->typedPromotion('FREE', ['priority' => 2, 'isCumulative' => true, 'isRemovingPostage' => true]);
        $this->getService(CouponExclusionWriter::class)->replace($other->getId(), [$freePostage->getId()]);
        $cart = $this->cartWithAProduct();
        $evaluator = $this->getService(CouponFreeShippingEvaluator::class);

        $this->type($other, $freePostage);

        self::assertFalse($this->couponManager->isCouponRemovingPostage($cart), 'The promotion offering the postage is excluded by the one kept.');
        self::assertFalse($evaluator->isCouponRemovingPostage(1, 1));

        $this->getService(CouponExclusionWriter::class)->replace($other->getId(), []);
        $this->couponManager->invalidateCurrentCoupons();

        self::assertTrue($this->couponManager->isCouponRemovingPostage($cart));
        self::assertTrue($evaluator->isCouponRemovingPostage(1, 1));
    }

    public function testTheSelectionIsMadeOncePerStateAndMadeAgainWhenTheStateChanges(): void
    {
        $this->typedPromotion('MEMO', ['isCumulative' => true]);
        $cart = $this->cartWithAProduct();
        $this->session()->setConsumedCoupons(['MEMO']);
        $this->couponManager->invalidateCurrentCoupons();

        $first = $this->couponManager->getSelection();

        self::assertSame($first, $this->couponManager->getSelection(), 'Same state: the selector does not run again.');
        self::assertSame($first->kept, $this->couponManager->getCouponsKept());
        self::assertGreaterThan(0.0, $this->couponManager->getDiscount());
        self::assertSame($first, $this->couponManager->getSelection());

        $this->addItem($cart, $this->product(), append: false);
        $afterCartChange = $this->couponManager->getSelection();

        self::assertNotSame($first, $afterCartChange, 'A line added to the cart changes what the conditions read.');
        self::assertSame($afterCartChange, $this->couponManager->getSelection());

        // Nobody invalidated the coupons here: the fingerprint alone notices the change.
        $line = $this->getService(FacadeInterface::class)->getCart()?->getCartItems()->getFirst();
        self::assertNotNull($line);
        $line->setQuantity($line->getQuantity() + 2)->save();

        self::assertNotSame($afterCartChange, $this->couponManager->getSelection(), 'A quantity changed without any event is noticed.');
        $afterCartChange = $this->couponManager->getSelection();

        $this->couponManager->invalidateCurrentCoupons();

        self::assertNotSame($afterCartChange, $this->couponManager->getSelection());
    }

    public function testTheSelectionIsMadeAgainWhenTheCurrencyOrTheDefaultAddressOfTheCustomerChanges(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->cartWithAProduct();
        $this->type($this->typedPromotion('MEMO'));

        $first = $this->couponManager->getSelection();
        self::assertSame($first, $this->couponManager->getSelection());

        // Nobody invalidated the coupons: the fingerprint alone notices what the conditions read.
        $previousCurrency = $this->session()->getCurrency(true);
        $this->session()->setCurrency($this->factory->currency(['code' => 'XTS']));

        try {
            $afterCurrencyChange = $this->couponManager->getSelection();
            self::assertNotSame($first, $afterCurrencyChange, 'A currency change alone is noticed.');
        } finally {
            $this->session()->setCurrency($previousCurrency);
        }

        $address = $this->factory->address($customer);
        $address->setIsDefault(1)->save();
        $customer->clearAddresses();
        $withDefaultAddress = $this->couponManager->getSelection();
        self::assertNotSame($afterCurrencyChange, $withDefaultAddress, 'A default address alone is noticed.');

        $address->setCountryId($this->factory->country()->getId())->save();
        $customer->clearAddresses();
        self::assertNotSame($withDefaultAddress, $this->couponManager->getSelection(), 'The country of the default address alone is noticed.');
    }

    public function testATypedCodePushedAsideIsListedWithTheTitleOfTheWinner(): void
    {
        $this->typedPromotion('BLACK', ['priority' => 1, 'title' => 'Black Friday -20 %', 'stopProcessing' => true]);
        $summer = $this->typedPromotion('SUMMER', ['priority' => 2, 'title' => 'Été -10 %']);
        $this->cartWithAProduct();
        $this->session()->setConsumedCoupons(['SUMMER', 'BLACK']);
        $this->couponManager->invalidateCurrentCoupons();

        self::assertSame(
            [['code' => 'SUMMER', 'title' => 'Été -10 %', 'winner_title' => 'Black Friday -20 %']],
            $this->getService(AttributeAccessService::class)->attributeCart('excluded_promotions'),
        );
        self::assertSame('SUMMER', $summer->getCode());
    }

    public function testATypedCodeWhoseConditionsAreNotMetIsNotAnnouncedAsPushedAside(): void
    {
        $black = $this->typedPromotion('BLACK', ['priority' => 1, 'title' => 'Black Friday -20 %']);
        $summer = $this->typedPromotion('SUMMER', ['priority' => 2, 'title' => 'Summer -10 %', 'conditions' => $this->totalAtLeast(1000.0)]);
        $this->getService(CouponExclusionWriter::class)->replace($black->getId(), [$summer->getId()]);
        $this->cartWithAProduct();
        $this->type($summer, $black);

        self::assertSame([$black->getCode()], $this->keptCodes());
        self::assertSame([], $this->couponManager->getSelection()->discarded, 'A code that would not apply anyway is ignored, not discarded.');
        self::assertSame([], $this->getService(AttributeAccessService::class)->attributeCart('excluded_promotions'));
    }

    public function testNothingIsListedWhenNoPromotionIsPushedAside(): void
    {
        $this->typedPromotion('ALONE');
        $this->cartWithAProduct();
        $this->session()->setConsumedCoupons(['ALONE']);
        $this->couponManager->invalidateCurrentCoupons();

        self::assertSame([], $this->getService(AttributeAccessService::class)->attributeCart('excluded_promotions'));
    }

    public function testAnAutomaticPromotionPushedAsideIsNeverListed(): void
    {
        $this->typedPromotion('STOPPER', ['priority' => 1, 'stopProcessing' => true]);
        $this->automaticPromotion(conditions: $this->everyone(), title: 'Automatic', overrides: ['priority' => 2]);
        // An automatic promotion may carry a code too: the buyer still did not ask for it.
        $this->automaticPromotion(conditions: $this->everyone(), title: 'Automatic with a code', overrides: ['priority' => 3, 'code' => 'AUTO-WITH-CODE']);
        $this->cartWithAProduct();
        $this->session()->setConsumedCoupons(['STOPPER']);
        $this->couponManager->invalidateCurrentCoupons();

        self::assertCount(2, $this->couponManager->getSelection()->discarded, 'Both automatic promotions are pushed aside.');
        self::assertSame([], $this->getService(AttributeAccessService::class)->attributeCart('excluded_promotions'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function typedPromotion(string $code, array $overrides = []): Coupon
    {
        return $this->factory->coupon(array_merge([
            'code' => $code,
            'conditions' => $this->everyone(),
            'isCumulative' => true,
            'effects' => ['amount' => 5.0],
        ], $overrides));
    }

    private function type(Coupon ...$coupons): void
    {
        $this->session()->setConsumedCoupons(array_map(static fn (Coupon $coupon): string => (string) $coupon->getCode(), $coupons));
        $this->couponManager->invalidateCurrentCoupons();
    }

    private function cartWithAProduct(): Cart
    {
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));

        return $cart;
    }

    /**
     * @return list<string>
     */
    private function keptCodes(): array
    {
        return array_map(static fn (CouponInterface $coupon): string => $coupon->getCode(), $this->couponManager->getSelection()->kept);
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

    private function everyone(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
