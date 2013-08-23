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

use PHPUnit_Framework_TestCase;
use Thelia\Coupon\Type\RemoveXPercent;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test RemoveXPercent Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXPercentTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }


    protected function generateValidCumulativeRemovingPostageCoupon()
    {
        $coupon = new RemoveXPercent(
            self::VALID_COUPON_CODE,
            self::VALID_COUPON_TITLE,
            self::VALID_COUPON_SHORT_DESCRIPTION,
            self::VALID_COUPON_DESCRIPTION,
            30.00,
            true,
            true
        );

        return $coupon;
    }

    protected function generateValidNonCumulativeNonRemovingPostageCoupon()
    {
        $coupon = new RemoveXPercent(
            self::VALID_COUPON_CODE,
            self::VALID_COUPON_TITLE,
            self::VALID_COUPON_SHORT_DESCRIPTION,
            self::VALID_COUPON_DESCRIPTION,
            30.00,
            false,
            false
        );

        return $coupon;
    }

    /**
     *
     * @covers Thelia\Coupon\Type\RemoveXPercent::getCode
     * @covers Thelia\Coupon\Type\RemoveXPercent::getTitle
     * @covers Thelia\Coupon\Type\RemoveXPercent::getShortDescription
     * @covers Thelia\Coupon\Type\RemoveXPercent::getDescription
     *
     */
    public function testDisplay()
    {

        $coupon = $this->generateValidCumulativeRemovingPostageCoupon();

        $expected = self::VALID_COUPON_CODE;
        $actual = $coupon->getCode();
        $this->assertEquals($expected, $actual);

        $expected = self::VALID_COUPON_TITLE;
        $actual = $coupon->getTitle();
        $this->assertEquals($expected, $actual);

        $expected = self::VALID_COUPON_SHORT_DESCRIPTION;
        $actual = $coupon->getShortDescription();
        $this->assertEquals($expected, $actual);

        $expected = self::VALID_COUPON_DESCRIPTION;
        $actual = $coupon->getDescription();
        $this->assertEquals($expected, $actual);

    }


    /**
     *
     * @covers Thelia\Coupon\Type\RemoveXPercent::isCumulative
     *
     */
    public function testIsCumulative()
    {

        $coupon = $this->generateValidCumulativeRemovingPostageCoupon();

        $actual = $coupon->isCumulative();
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Type\RemoveXPercent::isCumulative
     *
     */
    public function testIsNotCumulative()
    {

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        $actual = $coupon->isCumulative();
        $this->assertFalse($actual);
    }


    /**
     *
     * @covers Thelia\Coupon\Type\RemoveXPercent::isRemovingPostage
     *
     */
    public function testIsRemovingPostage()
    {

        $coupon = $this->generateValidCumulativeRemovingPostageCoupon();

        $actual = $coupon->isRemovingPostage();
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Type\RemoveXPercent::isRemovingPostage
     *
     */
    public function testIsNotRemovingPostage()
    {

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        $actual = $coupon->isRemovingPostage();
        $this->assertFalse($actual);
    }


    /**
     *
     * @covers Thelia\Coupon\Type\RemoveXPercent::getEffect
     *
     */
    public function testGetEffect()
    {

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
