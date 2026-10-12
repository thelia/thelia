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
use Thelia\Domain\Promotion\Coupon\Service\CouponFreeShippingEvaluator;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A coupon can offer the postage, everywhere or only for some delivery
 * countries and some delivery modules. Both the cart check (order and cart
 * postage events, the API) and the estimate (best postage for a country) read
 * the coupon's lists.
 */
final class CouponFreePostageTest extends ActionIntegrationTestCase
{
    private CouponManager $couponManager;

    private Country $deliveryCountry;

    private Country $otherCountry;

    private Module $deliveryModule;

    private Module $otherModule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->couponManager = $this->getService(CouponManager::class);
        $this->couponManager->invalidateCurrentCoupons();
        $this->session()->setConsumedCoupons([]);

        $countries = CountryQuery::create()->orderById()->limit(2)->find();
        $modules = ModuleQuery::create()->orderById()->limit(2)->find();
        self::assertCount(2, $countries);
        self::assertCount(2, $modules);

        [$this->deliveryCountry, $this->otherCountry] = [$countries[0], $countries[1]];
        [$this->deliveryModule, $this->otherModule] = [$modules[0], $modules[1]];
    }

    public function testACouponOfferingThePostageEverywhereMakesItFree(): void
    {
        $this->freePostagePromotion();

        self::assertTrue($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertTrue($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
    }

    public function testACouponOfferingThePostageForAnEmptyCartMakesItFree(): void
    {
        $this->freePostagePromotion();

        self::assertTrue($this->couponManager->isCouponRemovingPostage($this->factory->cart()));
    }

    public function testACouponNotOfferingThePostageLeavesItCharged(): void
    {
        $this->automaticPromotion(['isRemovingPostage' => false]);

        self::assertFalse($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertFalse($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
    }

    public function testThePostageIsFreeWhenDeliveringToACountryOfTheCoupon(): void
    {
        $this->freePostagePromotion(countries: [$this->deliveryCountry]);

        self::assertTrue($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertTrue($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
    }

    public function testThePostageIsChargedWhenDeliveringToACountryOutsideTheCoupon(): void
    {
        $this->freePostagePromotion(countries: [$this->otherCountry]);

        self::assertFalse($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertFalse($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
    }

    public function testThePostageIsChargedWhenTheCartHasNoDeliveryAddressYetAndTheCouponNamesCountries(): void
    {
        $this->freePostagePromotion(countries: [$this->deliveryCountry]);

        self::assertFalse($this->couponManager->isCouponRemovingPostage($this->factory->cart()));
    }

    public function testThePostageIsFreeWhenDeliveringWithAModuleOfTheCoupon(): void
    {
        $this->freePostagePromotion(modules: [$this->deliveryModule]);

        self::assertTrue($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertTrue($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
    }

    public function testThePostageIsChargedWhenDeliveringWithAModuleOutsideTheCoupon(): void
    {
        $this->freePostagePromotion(modules: [$this->otherModule]);

        self::assertFalse($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertFalse($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
    }

    public function testThePostageIsFreeOnlyWhenBothTheCountryAndTheModuleBelongToTheCoupon(): void
    {
        $this->freePostagePromotion(countries: [$this->deliveryCountry], modules: [$this->otherModule]);

        self::assertFalse($this->couponManager->isCouponRemovingPostage($this->deliveringCart()));
        self::assertFalse($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->deliveryModule->getId()));
        self::assertTrue($this->evaluator()->isCouponRemovingPostage($this->deliveryCountry->getId(), $this->otherModule->getId()));
    }

    /**
     * @param list<Country> $countries
     * @param list<Module>  $modules
     */
    private function freePostagePromotion(array $countries = [], array $modules = []): Coupon
    {
        $coupon = $this->automaticPromotion(['isRemovingPostage' => true]);

        foreach ($countries as $country) {
            (new CouponCountry())->setCouponId($coupon->getId())->setCountryId($country->getId())->save();
        }

        foreach ($modules as $module) {
            (new CouponModule())->setCouponId($coupon->getId())->setModuleId($module->getId())->save();
        }

        $this->couponManager->invalidateCurrentCoupons();

        return $coupon;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function automaticPromotion(array $overrides): Coupon
    {
        return $this->factory->coupon(array_merge([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'conditions' => $this->alwaysMatching(),
        ], $overrides));
    }

    private function deliveringCart(): Cart
    {
        $cart = $this->factory->cart();
        $cart->setAddressDeliveryId($this->factory->cartAddress(country: $this->deliveryCountry)->getId());
        $cart->setDeliveryModuleId($this->deliveryModule->getId());
        $cart->save();

        return $cart;
    }

    private function evaluator(): CouponFreeShippingEvaluator
    {
        return $this->getService(CouponFreeShippingEvaluator::class);
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
