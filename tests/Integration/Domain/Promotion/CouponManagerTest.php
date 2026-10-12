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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCustomerCountQuery;
use Thelia\Model\Customer;

/**
 * The coupons applying to a checkout are the codes typed in during the session
 * plus the automatic promotions, built once for the request.
 */
final class CouponManagerTest extends ActionIntegrationPromotionTestCase
{
    private CouponManager $couponManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->couponManager = $this->getService(CouponManager::class);
    }

    public function testTypedCodesAndAutomaticPromotionsApplyTogether(): void
    {
        $typed = $this->factory->coupon(['code' => 'TYPED-'.uniqid(), 'conditions' => $this->alwaysMatching()]);
        $automatic = $this->promotionForEveryone();

        $this->session()->setConsumedCoupons([$typed->getCode()]);

        self::assertSame(
            [$typed->getCode(), ''],
            array_map(static fn (CouponInterface $coupon): string => $coupon->getCode(), $this->couponManager->getCurrentCoupons()),
        );
        self::assertNotNull($automatic->getId());
    }

    public function testACouponIsNotCountedTwiceWhenItsCodeIsAlsoTypedIn(): void
    {
        // A promotion can carry both: it applies on its own, and the code still works.
        $coupon = $this->factory->coupon([
            'code' => 'BOTH-'.uniqid(),
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'conditions' => $this->alwaysMatching(),
        ]);

        $this->session()->setConsumedCoupons([$coupon->getCode()]);

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    public function testAnAutomaticPromotionCarryingACodeAppliesWithoutTheCodeTypedIn(): void
    {
        $coupon = $this->promotionForEveryone(['code' => 'AUTO-'.uniqid()]);

        self::assertSame(
            [$coupon->getCode()],
            array_map(static fn (CouponInterface $coupon): string => $coupon->getCode(), $this->couponManager->getCurrentCoupons()),
        );
    }

    public function testAnAutomaticPromotionAppliesWithNoCodeTypedIn(): void
    {
        $this->promotionForEveryone();

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    public function testADisabledOrExpiredPromotionNeverApplies(): void
    {
        $this->promotionForEveryone(['isEnabled' => false]);
        $this->promotionForEveryone(['expirationDate' => new \DateTime('-1 day')]);
        $this->promotionForEveryone(['startDate' => new \DateTime('+1 day')]);

        self::assertSame([], $this->couponManager->getCurrentCoupons());
    }

    public function testAPromotionStartedInThePastApplies(): void
    {
        $this->promotionForEveryone(['startDate' => new \DateTime('-1 day')]);

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    /**
     * An expired, disabled or used up code can never apply again in this session:
     * it leaves the session instead of being ignored on every calculation.
     */
    public function testATypedCodeThatCanNoLongerApplyIsTakenOutOfTheSession(): void
    {
        // A usage limit is only checked against a signed-in customer: without one the
        // used up code would be refused for the missing customer, and kept.
        $this->getService(SecurityContext::class)->setCustomerUser($this->factory->customer($this->factory->customerTitle()));
        $valid = $this->factory->coupon(['code' => 'VALID-'.uniqid(), 'conditions' => $this->alwaysMatching()]);
        $otherValid = $this->factory->coupon(['code' => 'OTHER-'.uniqid(), 'conditions' => $this->alwaysMatching()]);
        $expired = $this->factory->coupon([
            'code' => 'EXPIRED-'.uniqid(),
            'conditions' => $this->alwaysMatching(),
            'expirationDate' => new \DateTime('-1 day'),
        ]);
        $disabled = $this->factory->coupon([
            'code' => 'DISABLED-'.uniqid(),
            'conditions' => $this->alwaysMatching(),
            'isEnabled' => false,
        ]);
        $usedUp = $this->factory->coupon([
            'code' => 'USED-UP-'.uniqid(),
            'conditions' => $this->alwaysMatching(),
            'maxUsage' => 0,
        ]);

        $this->session()->setConsumedCoupons([
            $expired->getCode(),
            $valid->getCode(),
            $disabled->getCode(),
            $usedUp->getCode(),
            $otherValid->getCode(),
        ]);

        self::assertSame(
            [$valid->getCode(), $otherValid->getCode()],
            array_map(static fn (CouponInterface $coupon): string => $coupon->getCode(), $this->couponManager->getCurrentCoupons()),
        );
        self::assertSame([$valid->getCode(), $otherValid->getCode()], $this->session()->getConsumedCoupons());
    }

    /**
     * A code whose start date is still to come may become valid later in the
     * session: it is left out of the calculation but stays in the session.
     */
    public function testATypedCodeNotStartedYetIsIgnoredButKeptInTheSession(): void
    {
        $notStarted = $this->factory->coupon([
            'code' => 'SOON-'.uniqid(),
            'conditions' => $this->alwaysMatching(),
            'startDate' => new \DateTime('+1 day'),
        ]);

        $this->session()->setConsumedCoupons([$notStarted->getCode()]);

        self::assertSame([], $this->couponManager->getCurrentCoupons());
        self::assertSame([$notStarted->getCode()], $this->session()->getConsumedCoupons());
    }

    public function testTheCouponsAreBuiltOnceForTheRequest(): void
    {
        $this->promotionForEveryone();

        $first = $this->couponManager->getCurrentCoupons();
        $second = $this->couponManager->getCurrentCoupons();

        // The factory hands out a clone per build, so identical instances mean
        // the second call read the memo instead of querying and rebuilding.
        self::assertSame($first, $second);

        // A promotion created after the first call must not appear: the answer
        // is the one the whole evaluation cycle shares.
        $this->promotionForEveryone();
        self::assertSame($first, $this->couponManager->getCurrentCoupons());
    }

    public function testInvalidatingTheMemoRebuildsTheCoupons(): void
    {
        $this->promotionForEveryone();
        $first = $this->couponManager->getCurrentCoupons();

        $this->promotionForEveryone();
        $this->couponManager->invalidateCurrentCoupons();

        $rebuilt = $this->couponManager->getCurrentCoupons();

        self::assertCount(2, $rebuilt);
        self::assertNotSame($first, $rebuilt);
    }

    public function testResettingTheServiceInvalidatesTheMemo(): void
    {
        $this->promotionForEveryone();
        $first = $this->couponManager->getCurrentCoupons();

        $this->couponManager->reset();

        self::assertNotSame($first, $this->couponManager->getCurrentCoupons());
    }

    public function testPushingACodeInSessionInvalidatesTheMemo(): void
    {
        $coupon = $this->factory->coupon(['code' => 'PUSH-'.uniqid(), 'conditions' => $this->alwaysMatching()]);

        self::assertSame([], $this->couponManager->getCurrentCoupons());

        $this->couponManager->pushCouponInSession($coupon->getCode());

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    /**
     * A per-customer limit is counted against a customer. With nobody signed in
     * there is nothing to count against, so the promotion steps aside instead of
     * blocking the cart.
     */
    public function testAPromotionLimitedPerCustomerIsIgnoredWithoutACustomer(): void
    {
        $this->promotionForEveryone(['maxUsage' => 1, 'perCustomerUsageCount' => true]);

        self::assertSame([], $this->couponManager->getCurrentCoupons());
    }

    public function testAPromotionLimitedPerCustomerAppliesForACustomerWhoHasUsagesLeft(): void
    {
        $this->promotionForEveryone(['maxUsage' => 1, 'perCustomerUsageCount' => true]);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->couponManager->invalidateCurrentCoupons();

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    /**
     * Two promotions step aside: one whose code is already typed in, and one limited
     * per customer while nobody is signed in. The promotions after them still apply.
     */
    public function testAnAutomaticPromotionSteppingAsideLeavesTheNextOnesApplying(): void
    {
        $typedPromotion = $this->promotionForEveryone(['code' => 'TYPED-'.uniqid()]);
        $this->promotionForEveryone(['maxUsage' => 1, 'perCustomerUsageCount' => true]);
        $this->promotionForEveryone();

        $this->session()->setConsumedCoupons([$typedPromotion->getCode()]);

        self::assertSame(
            [$typedPromotion->getCode(), ''],
            array_map(static fn (CouponInterface $coupon): string => $coupon->getCode(), $this->couponManager->getCurrentCoupons()),
        );
    }

    public function testAPromotionUsedUpByTheCustomerLeavesTheNextOnesApplying(): void
    {
        $usedUp = $this->promotionForEveryone(['maxUsage' => 1, 'perCustomerUsageCount' => true]);
        $this->promotionForEveryone();

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->couponManager->decrementQuantity($usedUp, $customer->getId());
        $this->couponManager->invalidateCurrentCoupons();

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    /**
     * A console command or a cold error page has no session, sometimes no request
     * at all: no code was typed in, and the automatic promotions still apply.
     */
    public function testWithoutASessionOnlyTheAutomaticPromotionsApply(): void
    {
        $this->promotionForEveryone();

        $noRequest = new RequestStack();
        $requestWithoutSession = new RequestStack();
        $requestWithoutSession->push(new Request());

        foreach ([$noRequest, $requestWithoutSession] as $requestStack) {
            $couponManager = new CouponManager(
                $this->getService(FacadeInterface::class),
                $this->getService(CouponFactory::class),
                $requestStack,
            );

            self::assertCount(1, $couponManager->getCurrentCoupons());
        }
    }

    public function testAPromotionWithNoUsageLeftForTheCustomerIsIgnored(): void
    {
        $coupon = $this->promotionForEveryone(['maxUsage' => 1, 'perCustomerUsageCount' => true]);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->couponManager->decrementQuantity($coupon, $customer->getId());
        $this->couponManager->invalidateCurrentCoupons();

        self::assertSame([], $this->couponManager->getCurrentCoupons());
    }

    public function testCountingAPerCustomerUsageReturnsTheUsagesTheCustomerHasLeft(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 3, 'perCustomerUsageCount' => true]);
        $customerId = $this->factory->customer($this->factory->customerTitle())->getId();

        self::assertSame(2, $this->couponManager->decrementQuantity($coupon, $customerId));
        self::assertSame(1, $this->couponManager->decrementQuantity($coupon, $customerId));
        self::assertSame(1, $coupon->getUsagesLeft($customerId));

        self::assertSame(2, $this->couponManager->incrementQuantity($coupon, $customerId));
        self::assertSame(2, $coupon->getUsagesLeft($customerId));
    }

    public function testTheDiscountIsRoundedToTheCent(): void
    {
        $this->promotionForEveryone(['effects' => ['amount' => 1.234]]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());
        $this->couponManager->invalidateCurrentCoupons();

        self::assertSame(1.23, $this->couponManager->getDiscount());
    }

    /**
     * The coupon totals leave the offered lines out, but the customer still pays
     * for them before the discount: the discount may go as far as the taxed total
     * of every line of the cart, offered ones included, and no further.
     */
    public function testTheDiscountIsCappedAtTheTaxedTotalOfEveryLineOfferedOnesIncluded(): void
    {
        $this->promotionForEveryone(['effects' => ['amount' => 1000.0], 'isCumulative' => true]);
        $this->promotionForEveryone(['effects' => ['amount' => 1000.0], 'isCumulative' => true]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(), quantity: 3);
        $firstGift = $this->addItem($cart, $this->product(['basePrice' => 2.0]));
        $secondGift = $this->addItem($cart, $this->product(['basePrice' => 3.0]));
        $firstGift->setIsOffered(1)->save();
        $secondGift->setIsOffered(1)->save();
        $this->couponManager->invalidateCurrentCoupons();

        $country = $this->getService(TaxEngine::class)->getDeliveryCountry();
        $paidLines = $this->getService(FacadeInterface::class)->getCartTotalTaxPrice();
        $offeredLines = $firstGift->getTotalRealTaxedPrice($country) + $secondGift->getTotalRealTaxedPrice($country);

        // Each coupon is worth the paid lines (RemoveXAmount stops there), so the two
        // together ask for twice the paid lines: more than paid + offered as long as
        // the offered lines cost less than the paid ones. The cap is what is left.
        self::assertGreaterThan($offeredLines, $paidLines, 'Two coupons each worth the paid lines must exceed the cap.');
        self::assertSame(round($paidLines + $offeredLines, 2), $this->couponManager->getDiscount());
    }

    public function testNoCouponMeansNoFreePostage(): void
    {
        self::assertFalse($this->couponManager->isCouponRemovingPostage($this->newEmptyCart()));
    }

    /**
     * Whatever hands the coupons over, an expired one never makes it into the
     * coupons kept for the cart.
     */
    public function testAnExpiredCouponIsNeverKept(): void
    {
        $expired = $this->getService(CouponFactory::class)->buildCouponFromModel(
            $this->factory->coupon([
                'conditions' => $this->alwaysMatching(),
                'expirationDate' => new \DateTime('-1 day'),
                'isCumulative' => true,
            ]),
        );
        $valid = $this->getService(CouponFactory::class)->buildCouponFromModel(
            $this->factory->coupon(['conditions' => $this->alwaysMatching(), 'isCumulative' => true]),
        );
        self::assertTrue($expired->isMatching());

        // The factory and the automatic promotions query both turn an expired coupon
        // away, so the isExpired() check of the sorting is only reached through a
        // manager handed its coupons by an extension, as this one is.
        $couponManager = new class($this->getService(FacadeInterface::class), $this->getService(CouponFactory::class), $this->getService(RequestStack::class), [$expired, $valid]) extends CouponManager {
            /**
             * @param CouponInterface[] $coupons
             */
            public function __construct(
                FacadeInterface $facade,
                CouponFactory $couponFactory,
                RequestStack $requestStack,
                private readonly array $coupons,
            ) {
                parent::__construct($facade, $couponFactory, $requestStack);
            }

            public function getCurrentCoupons(): array
            {
                return $this->coupons;
            }
        };

        self::assertSame([$valid], $couponManager->getCouponsKept());
    }

    /**
     * Clearing the coupons lets each one drop what it keeps in the session: a
     * free product coupon forgets the cart lines it put in.
     */
    public function testClearingTheCouponsLetsEachOneForgetWhatItKept(): void
    {
        $coupon = $this->factory->coupon([
            'code' => 'FREE-'.uniqid(),
            'type' => 'thelia.coupon.type.free_product',
            'effects' => [
                'products' => [],
                'category_id' => 0,
                'offered_product_id' => $this->product()->getId(),
                'offered_category_id' => 0,
            ],
            'conditions' => $this->alwaysMatching(),
        ]);
        $sessionVarName = 'coupon.free_product.cart_items.'.$coupon->getCode();

        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->session()->set($sessionVarName, [42]);

        $this->couponManager->clear();

        self::assertFalse($this->session()->has($sessionVarName));
    }

    public function testDecrementingACouponWithNoUsageLeftChangesNothing(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 0]);

        self::assertFalse($this->couponManager->decrementQuantity($coupon));
        self::assertSame(0, $this->reloadCoupon($coupon)->getMaxUsage());
    }

    public function testDecrementingASharedLimitTakesOneUsageFromTheCoupon(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 3]);

        self::assertSame(2, $this->couponManager->decrementQuantity($coupon));
        self::assertSame(2, $this->reloadCoupon($coupon)->getMaxUsage());
    }

    public function testDecrementingAPerCustomerLimitCountsTheUsageForThatCustomerOnly(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 3, 'perCustomerUsageCount' => true]);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $otherCustomer = $this->factory->customer($this->factory->customerTitle());

        self::assertSame(2, $this->couponManager->decrementQuantity($coupon, $customer->getId()));
        self::assertSame(1, $this->usageCount($coupon, $customer));

        $this->couponManager->decrementQuantity($coupon, $customer->getId());

        $reloaded = $this->reloadCoupon($coupon);
        self::assertSame(2, $this->usageCount($coupon, $customer));
        self::assertSame(1, $reloaded->getUsagesLeft($customer->getId()));
        self::assertSame(3, $reloaded->getUsagesLeft($otherCustomer->getId()));
        self::assertSame(3, $reloaded->getMaxUsage());
    }

    public function testIncrementingASharedLimitGivesOneUsageBackToTheCoupon(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 2]);

        self::assertSame(3, $this->couponManager->incrementQuantity($coupon));
        self::assertSame(3, $this->reloadCoupon($coupon)->getMaxUsage());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function promotionForEveryone(array $overrides = []): Coupon
    {
        return $this->factory->coupon(array_merge([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'conditions' => $this->alwaysMatching(),
        ], $overrides));
    }

    private function alwaysMatching(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    private function reloadCoupon(Coupon $coupon): Coupon
    {
        $coupon->reload();

        return $coupon;
    }

    private function usageCount(Coupon $coupon, Customer $customer): ?int
    {
        return CouponCustomerCountQuery::create()
            ->filterByCouponId($coupon->getId())
            ->filterByCustomerId($customer->getId())
            ->findOne()
            ?->getCount();
    }
}
