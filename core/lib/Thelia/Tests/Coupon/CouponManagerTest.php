<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon;

use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Constraint\Rule\AvailableForTotalAmount;
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Coupon\Type\RemoveXAmount;
use Thelia\Tools\PhpUnitUtils;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test CouponManager Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponManagerTest extends \PHPUnit_Framework_TestCase
{
    CONST VALID_CODE = 'XMAS';
    CONST VALID_TITLE = 'XMAS coupon';
    CONST VALID_SHORT_DESCRIPTION = 'Coupon for Christmas removing 10€ if your total checkout is more than 40€';
    CONST VALID_DESCRIPTION = '<h3>Lorem ipsum dolor sit amet</h3>Consectetur adipiscing elit. Cras at luctus tellus. Integer turpis mauris, aliquet vitae risus tristique, pellentesque vestibulum urna. Vestibulum sodales laoreet lectus dictum suscipit. Praesent vulputate, sem id varius condimentum, quam magna tempor elit, quis venenatis ligula nulla eget libero. Cras egestas euismod tellus, id pharetra leo suscipit quis. Donec lacinia ac lacus et ultricies. Nunc in porttitor neque. Proin at quam congue, consectetur orci sed, congue nulla. Nulla eleifend nunc ligula, nec pharetra elit tempus quis. Vivamus vel mauris sed est dictum blandit. Maecenas blandit dapibus velit ut sollicitudin. In in euismod mauris, consequat viverra magna. Cras velit velit, sollicitudin commodo tortor gravida, tempus varius nulla.

Donec rhoncus leo mauris, id porttitor ante luctus tempus.
<script type="text/javascript">
    alert("I am an XSS attempt!");
</script>
Curabitur quis augue feugiat, ullamcorper mauris ac, interdum mi. Quisque aliquam lorem vitae felis lobortis, id interdum turpis mattis. Vestibulum diam massa, ornare congue blandit quis, facilisis at nisl. In tortor metus, venenatis non arcu nec, sollicitudin ornare nisl. Nunc erat risus, varius nec urna at, iaculis lacinia elit. Aenean ut felis tempus, tincidunt odio non, sagittis nisl. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec vitae hendrerit elit. Nunc sit amet gravida risus, euismod lobortis massa. Nam a erat mauris. Nam a malesuada lorem. Nulla id accumsan dolor, sed rhoncus tellus. Quisque dictum felis sed leo auctor, at volutpat lectus viverra. Morbi rutrum, est ac aliquam imperdiet, nibh sem sagittis justo, ac mattis magna lacus eu nulla.

Duis interdum lectus nulla, nec pellentesque sapien condimentum at. Suspendisse potenti. Sed eu purus tellus. Nunc quis rhoncus metus. Fusce vitae tellus enim. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam tempor porttitor erat vitae iaculis. Sed est elit, consequat non ornare vitae, vehicula eget lectus. Etiam consequat sapien mauris, eget consectetur magna imperdiet eget. Nunc sollicitudin luctus velit, in commodo nulla adipiscing fermentum. Fusce nisi sapien, posuere vitae metus sit amet, facilisis sollicitudin dui. Fusce ultricies auctor enim sit amet iaculis. Morbi at vestibulum enim, eget adipiscing eros.

Praesent ligula lorem, faucibus ut metus quis, fermentum iaculis erat. Pellentesque elit erat, lacinia sed semper ac, sagittis vel elit. Nam eu convallis est. Curabitur rhoncus odio vitae consectetur pellentesque. Nam vitae arcu nec ante scelerisque dignissim vel nec neque. Suspendisse augue nulla, mollis eget dui et, tempor facilisis erat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi ac diam ipsum. Donec convallis dui ultricies velit auctor, non lobortis nulla ultrices. Morbi vitae dignissim ante, sit amet lobortis tortor. Nunc dapibus condimentum augue, in molestie neque congue non.

Sed facilisis pellentesque nisl, eu tincidunt erat scelerisque a. Nullam malesuada tortor vel erat volutpat tincidunt. In vehicula diam est, a convallis eros scelerisque ut. Donec aliquet venenatis iaculis. Ut a arcu gravida, placerat dui eu, iaculis nisl. Quisque adipiscing orci sit amet dui dignissim lacinia. Sed vulputate lorem non dolor adipiscing ornare. Morbi ornare id nisl id aliquam. Ut fringilla elit ante, nec lacinia enim fermentum sit amet. Aenean rutrum lorem eu convallis pharetra. Cras malesuada varius metus, vitae gravida velit. Nam a varius ipsum, ac commodo dolor. Phasellus nec elementum elit. Etiam vel adipiscing leo.';

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    /**
     * Test getDiscount() behaviour
     * Entering : 1 valid Coupon (If 40 < total amount 400) - 10euros
     *
     * @covers Thelia\Coupon\CouponManager::getDiscount
     */
    public function testGetDiscountOneCoupon()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        /** @var CouponInterface $coupon */
        $coupon = self::generateValidCoupon();

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter(array($coupon), $cartTotalPrice, $checkoutTotalPrice);

        $couponManager = new CouponManager($stubCouponBaseAdapter);
        $discount = $couponManager->getDiscount();

        $expected = 10.00;
        $actual = $discount;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getDiscount() behaviour
     * Entering : 1 valid Coupon (If 40 < total amount 400) - 10euros
     *            1 valid Coupon (If total amount > 20) - 15euros
     *
     * @covers Thelia\Coupon\CouponManager::getDiscount
     */
    public function testGetDiscountTwoCoupon()
    {
        $adapter = new CouponBaseAdapter();
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        /** @var CouponInterface $coupon1 */
        $coupon1 = self::generateValidCoupon();
        $rule1 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::SUPERIOR,
                    new PriceParam(
                        $adapter, 40.00, 'EUR'
                    )
                )
            )
        );
        $rules = new CouponRuleCollection(array($rule1));
        /** @var CouponInterface $coupon2 */
        $coupon2 = $this->generateValidCoupon('XMAS2', null, null, null, 15.00, null, null, $rules);

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter(array($coupon1, $coupon2), $cartTotalPrice, $checkoutTotalPrice);

        $couponManager = new CouponManager($stubCouponBaseAdapter);
        $discount = $couponManager->getDiscount();

        $expected = 25.00;
        $actual = $discount;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getDiscount() behaviour
     * For a Cart of 21euros
     * Entering : 1 valid Coupon (If total amount > 20) - 30euros
     *
     * @covers Thelia\Coupon\CouponManager::getDiscount
     */
    public function testGetDiscountAlwaysInferiorToPrice()
    {
        $adapter = new CouponBaseAdapter();
        $cartTotalPrice = 21.00;
        $checkoutTotalPrice = 26.00;

        $rule1 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::SUPERIOR,
                    new PriceParam(
                        $adapter, 20.00, 'EUR'
                    )
                )
            )
        );
        $rules = new CouponRuleCollection(array($rule1));
        /** @var CouponInterface $coupon */
        $coupon = $this->generateValidCoupon('XMAS2', null, null, null, 30.00, null, null, $rules);

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter(array($coupon), $cartTotalPrice, $checkoutTotalPrice);

        $couponManager = new CouponManager($stubCouponBaseAdapter);
        $discount = $couponManager->getDiscount();

        $expected = 21.00;
        $actual = $discount;
        $this->assertEquals($expected, $actual);
    }


    /**
     * Check if removing postage on discout is working
     * @covers Thelia\Coupon\CouponManager::isCouponRemovingPostage
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testIsCouponRemovingPostage()
    {
        $adapter = new CouponBaseAdapter();
        $cartTotalPrice = 21.00;
        $checkoutTotalPrice = 27.00;

        $rule1 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::SUPERIOR,
                    new PriceParam(
                        $adapter, 20.00, 'EUR'
                    )
                )
            )
        );
        $rules = new CouponRuleCollection(array($rule1));
        /** @var CouponInterface $coupon */
        $coupon = $this->generateValidCoupon('XMAS2', null, null, null, 30.00, null, null, $rules, null, true);

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter(array($coupon), $cartTotalPrice, $checkoutTotalPrice);

        $couponManager = new CouponManager($stubCouponBaseAdapter);
        $discount = $couponManager->getDiscount();

        $expected = 21.00;
        $actual = $discount;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon not cumulative
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponNotCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false);

        $coupons = array($couponCumulative1);

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = $coupons;
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Array Sorted despite there is only once');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon(null, null, null, null, null, null, null, null, true);

        $coupons = array($couponCumulative1);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = $coupons;
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Array Sorted despite there is only once');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative
     *          1 Coupon cumulative
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationTwoCouponCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, true);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = $coupons;
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Array Sorted despite both Coupon can be accumulated');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative
     *          1 Coupon non cumulative
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponCumulativeOneNonCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, false);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array($couponCumulative2);
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Array Sorted despite both Coupon can be accumulated');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon non cumulative
     *          1 Coupon cumulative
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponNonCumulativeOneCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, false);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, true);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array($couponCumulative2);
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Array Sorted despite both Coupon can be accumulated');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon non cumulative
     *          1 Coupon non cumulative
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationTwoCouponNonCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, false);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, false);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array($couponCumulative2);
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Array Sorted despite both Coupon can be accumulated');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative expired
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponCumulativeExpired()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, new \DateTime(), null, true);

        $coupons = array($couponCumulative1);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array();
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Coupon expired ignored');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative expired
     *          1 Coupon cumulative expired
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationTwoCouponCumulativeExpired()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, new \DateTime(), null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, new \DateTime(), null, true);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array();
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Coupon expired ignored');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative expired
     *          1 Coupon cumulative valid
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponCumulativeExpiredOneNonExpired()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, new \DateTime(), null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, true);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array($couponCumulative2);
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Coupon expired ignored');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative valid
     *          1 Coupon cumulative expired
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationOneCouponCumulativeNonExpiredOneExpired()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, new \DateTime(), null, true);

        $coupons = array($couponCumulative1, $couponCumulative2);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array($couponCumulative1);
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Coupon expired ignored');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative valid
     *          1 Coupon cumulative valid
     *          1 Coupon cumulative valid
     *          1 Coupon cumulative valid
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationFourCouponCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, true);
        $couponCumulative3 = $this->generateValidCoupon('XMAS3', null, null, null, null, null, null, null, true);
        $couponCumulative4 = $this->generateValidCoupon('XMAS4', null, null, null, null, null, null, null, true);

        $coupons = array($couponCumulative1, $couponCumulative2, $couponCumulative3, $couponCumulative4);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = $coupons;
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Coupon cumulative ignored');
    }

    /**
     * Testing how multiple Coupon behaviour
     * Entering 1 Coupon cumulative valid
     *          1 Coupon cumulative valid
     *          1 Coupon cumulative valid
     *          1 Coupon non cumulative valid
     *
     * @covers Thelia\Coupon\CouponManager::sortCoupons
     */
    public function testCouponCumulationThreeCouponCumulativeOneNonCumulative()
    {
        $cartTotalPrice = 100.00;
        $checkoutTotalPrice = 120.00;

        // Given
        /** @var CouponInterface $coupon */
        $couponCumulative1 = $this->generateValidCoupon('XMAS1', null, null, null, null, null, null, null, true);
        $couponCumulative2 = $this->generateValidCoupon('XMAS2', null, null, null, null, null, null, null, true);
        $couponCumulative3 = $this->generateValidCoupon('XMAS3', null, null, null, null, null, null, null, true);
        $couponCumulative4 = $this->generateValidCoupon('XMAS4', null, null, null, null, null, null, null, false);

        $coupons = array($couponCumulative1, $couponCumulative2, $couponCumulative3, $couponCumulative4);
        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->generateFakeAdapter($coupons, $cartTotalPrice, $checkoutTotalPrice);

        // When
        $sortedCoupons = PhpUnitUtils::callMethod(
            new CouponManager($stubCouponBaseAdapter),
            'sortCoupons',
            array($coupons)
        );

        // Then
        $expected = array($couponCumulative4);
        $actual = $sortedCoupons;

        $this->assertSame($expected, $actual, 'Coupon cumulative ignored');
    }


    /**
     * Generate valid CouponRuleInterfaces
     *
     * @return array Array of CouponRuleInterface
     */
    public static function generateValidRules()
    {
        $adapter = new CouponBaseAdapter();
        $rule1 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::SUPERIOR,
                    new PriceParam(
                        $adapter, 40.00, 'EUR'
                    )
                )
            )
        );
        $rule2 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::INFERIOR,
                    new PriceParam(
                        $adapter, 400.00, 'EUR'
                    )
                )
            )
        );
        $rules = new CouponRuleCollection(array($rule1, $rule2));

        return $rules;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * Generate a fake Adapter
     *
     * @param array $coupons            Coupons
     * @param float $cartTotalPrice     Cart total price
     * @param float $checkoutTotalPrice Checkout total price
     * @param float $postagePrice       Checkout postage price
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateFakeAdapter(array $coupons, $cartTotalPrice, $checkoutTotalPrice, $postagePrice = 6.00)
    {
        $stubCouponBaseAdapter = $this->getMock(
            'Thelia\Coupon\CouponBaseAdapter',
            array(
                'getCurrentCoupons',
                'getCartTotalPrice',
                'getCheckoutTotalPrice',
                'getCheckoutPostagePrice'
            ),
            array()
        );

        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCurrentCoupons')
            ->will($this->returnValue(($coupons)));

        // Return Cart product amount = $cartTotalPrice euros
        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        // Return Checkout amount = $checkoutTotalPrice euros
        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCheckoutTotalPrice')
            ->will($this->returnValue($checkoutTotalPrice));

        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCheckoutPostagePrice')
            ->will($this->returnValue($postagePrice));

        return $stubCouponBaseAdapter;
    }

    /**
     * Generate valid CouponInterface
     *
     * @param string               $code                       Coupon Code
     * @param string               $title                      Coupon Title
     * @param string               $shortDescription           Coupon short
     *                                                         description
     * @param string               $description                Coupon description
     * @param float                $amount                     Coupon discount
     * @param bool                 $isEnabled                  Is Coupon enabled
     * @param \DateTime            $expirationDate             Coupon expiration date
     * @param CouponRuleCollection $rules                      Coupon rules
     * @param bool                 $isCumulative               If is cumulative
     * @param bool                 $isRemovingPostage          If is removing postage
     * @param bool                 $isAvailableOnSpecialOffers If is available on
     *                                                         special offers or not
     * @param int                  $maxUsage                   How many time a Coupon
     *                                                         can be used
     *
     * @return CouponInterface
     */
    public static function generateValidCoupon(
        $code = null,
        $title = null,
        $shortDescription = null,
        $description = null,
        $amount = null,
        $isEnabled = null,
        $expirationDate = null,
        $rules = null,
        $isCumulative = null,
        $isRemovingPostage = null,
        $isAvailableOnSpecialOffers = null,
        $maxUsage = null
    ) {
        $adapter = new CouponBaseAdapter();
        if ($code === null) {
            $code = self::VALID_CODE;
        }
        if ($title === null) {
            $title = self::VALID_TITLE;
        }
        if ($shortDescription === null) {
            $shortDescription = self::VALID_SHORT_DESCRIPTION;
        }
        if ($description === null) {
            $description = self::VALID_DESCRIPTION;
        }
        if ($amount === null) {
            $amount = 10.00;
        }
        if ($isEnabled === null) {
            $isEnabled = true;
        }
        if ($isCumulative === null) {
            $isCumulative = true;
        }
        if ($isRemovingPostage === null) {
            $isRemovingPostage = false;
        }
        if ($isAvailableOnSpecialOffers === null) {
            $isAvailableOnSpecialOffers = true;
        }
        if ($maxUsage === null) {
            $maxUsage = 40;
        }

        if ($expirationDate === null) {
            $expirationDate = new \DateTime();
            $expirationDate->setTimestamp(strtotime("today + 2 months"));
        }

        $coupon = new RemoveXAmount($adapter, $code, $title, $shortDescription, $description, $amount, $isCumulative, $isRemovingPostage, $isAvailableOnSpecialOffers, $isEnabled, $maxUsage, $expirationDate);

        if ($rules === null) {
            $rules = self::generateValidRules();
        }

        $coupon->setRules($rules);

        return $coupon;
    }

}
