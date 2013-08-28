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

use InvalidArgumentException;
use Thelia\Constraint\Validator\DateParam;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test DateParam Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class DateParamTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
     *
     */
    public function testInferiorDate()
    {
        $adapter = new CouponBaseAdapter();
        $dateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-07");

        $dateParam = new DateParam($adapter, $dateValidator);

        $expected = 1;
        $actual = $dateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
     *
     */
    public function testEqualsDate()
    {
        $adapter = new CouponBaseAdapter();
        $dateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-08");

        $dateParam = new DateParam($adapter, $dateValidator);

        $expected = 0;
        $actual = $dateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
     *
     */
    public function testSuperiorDate()
    {
        $adapter = new CouponBaseAdapter();
        $dateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-09");

        $dateParam = new DateParam($adapter, $dateValidator);

        $expected = -1;
        $actual = $dateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentException()
    {
        $adapter = new CouponBaseAdapter();
        $dateValidator = new \DateTime("2012-07-08");
        $dateToValidate = 1377012588;

        $dateParam = new DateParam($adapter, $dateValidator);

        $dateParam->compareTo($dateToValidate);
    }

    /**
     * Test is the object is serializable
     * If no data is lost during the process
     */
    public function isSerializableTest()
    {
        $adapter = new CouponBaseAdapter();
        $dateValidator = new \DateTime("2012-07-08");

        $param = new DateParam($adapter, $dateValidator);

        $serialized = base64_encode(serialize($param));
        /** @var DateParam $unserialized */
        $unserialized = base64_decode(serialize($serialized));

        $this->assertEquals($param->getValue(), $unserialized->getValue());
        $this->assertEquals($param->getDateTime(), $unserialized->getDateTime());

        $new = new DateParam($adapter, $unserialized->getDateTime());
        $this->assertEquals($param->getDateTime(), $new->getDateTime());
    }


    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
