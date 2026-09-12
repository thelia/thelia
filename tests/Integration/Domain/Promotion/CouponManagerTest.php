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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The coupons applying to a checkout are the codes typed in during the session
 * plus the automatic promotions, built once for the request.
 */
final class CouponManagerTest extends ActionIntegrationTestCase
{
    private CouponManager $couponManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->couponManager = $this->getService(CouponManager::class);
        $this->couponManager->invalidateCurrentCoupons();
        $this->session()->setConsumedCoupons([]);
    }

    public function testTypedCodesAndAutomaticPromotionsApplyTogether(): void
    {
        $typed = $this->factory->coupon(['code' => 'TYPED-'.uniqid(), 'conditions' => $this->alwaysMatching()]);
        $automatic = $this->automaticPromotion();

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

    public function testAnAutomaticPromotionAppliesWithNoCodeTypedIn(): void
    {
        $this->automaticPromotion();

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    public function testADisabledOrExpiredPromotionNeverApplies(): void
    {
        $this->automaticPromotion(['isEnabled' => false]);
        $this->automaticPromotion(['expirationDate' => new \DateTime('-1 day')]);
        $this->automaticPromotion(['startDate' => new \DateTime('+1 day')]);

        self::assertSame([], $this->couponManager->getCurrentCoupons());
    }

    public function testAPromotionStartedInThePastApplies(): void
    {
        $this->automaticPromotion(['startDate' => new \DateTime('-1 day')]);

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    public function testTheCouponsAreBuiltOnceForTheRequest(): void
    {
        $this->automaticPromotion();

        $first = $this->couponManager->getCurrentCoupons();
        $second = $this->couponManager->getCurrentCoupons();

        // The factory hands out a clone per build, so identical instances mean
        // the second call read the memo instead of querying and rebuilding.
        self::assertSame($first, $second);

        // A promotion created after the first call must not appear: the answer
        // is the one the whole evaluation cycle shares.
        $this->automaticPromotion();
        self::assertSame($first, $this->couponManager->getCurrentCoupons());
    }

    public function testInvalidatingTheMemoRebuildsTheCoupons(): void
    {
        $this->automaticPromotion();
        $first = $this->couponManager->getCurrentCoupons();

        $this->automaticPromotion();
        $this->couponManager->invalidateCurrentCoupons();

        $rebuilt = $this->couponManager->getCurrentCoupons();

        self::assertCount(2, $rebuilt);
        self::assertNotSame($first, $rebuilt);
    }

    public function testResettingTheServiceInvalidatesTheMemo(): void
    {
        $this->automaticPromotion();
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
        $this->automaticPromotion(['maxUsage' => 1, 'perCustomerUsageCount' => true]);

        self::assertSame([], $this->couponManager->getCurrentCoupons());
    }

    public function testAPromotionLimitedPerCustomerAppliesForACustomerWhoHasUsagesLeft(): void
    {
        $this->automaticPromotion(['maxUsage' => 1, 'perCustomerUsageCount' => true]);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->couponManager->invalidateCurrentCoupons();

        self::assertCount(1, $this->couponManager->getCurrentCoupons());
    }

    public function testAPromotionWithNoUsageLeftForTheCustomerIsIgnored(): void
    {
        $coupon = $this->automaticPromotion(['maxUsage' => 1, 'perCustomerUsageCount' => true]);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->couponManager->decrementQuantity($coupon, $customer->getId());
        $this->couponManager->invalidateCurrentCoupons();

        self::assertSame([], $this->couponManager->getCurrentCoupons());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function automaticPromotion(array $overrides = []): Coupon
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

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
