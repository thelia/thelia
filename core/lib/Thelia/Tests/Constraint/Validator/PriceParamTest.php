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
use Thelia\Constraint\Validator\PriceParam;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test PriceParam Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class PriceParamTest extends \PHPUnit_Framework_TestCase
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
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     *
     */
    public function testInferiorPrice()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = 1.00;

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = 1;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     *
     */
    public function testInferiorPrice2()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = 42.49;

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = 1;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     *
     */
    public function testEqualsPrice()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = 42.50;

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = 0;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     *
     */
    public function testSuperiorPrice()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = 42.51;

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = -1;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentException()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = '42.50';

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = 0;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentException2()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = -1;

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = 0;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentException3()
    {
        $adapter = new CouponBaseAdapter();

        $priceValidator = 42.50;
        $priceToValidate = 0;

        $integerParam = new PriceParam($adapter, $priceValidator, 'EUR');

        $expected = 0;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\PriceParam::compareTo
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentException4()
    {
        $adapter = new CouponBaseAdapter();
        $priceValidator = 42.50;
        $priceToValidate = 1;

        $integerParam = new PriceParam($adapter, $priceValidator, 'GBP');

        $expected = 0;
        $actual = $integerParam->compareTo($priceToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test is the object is serializable
     * If no data is lost during the process
     */
    public function isSerializableTest()
    {
        $adapter = new CouponBaseAdapter();
        $priceValidator = 42.50;

        $param = new PriceParam($adapter, $priceValidator, 'GBP');

        $serialized = base64_encode(serialize($param));
        /** @var PriceParam $unserialized */
        $unserialized = base64_decode(serialize($serialized));

        $this->assertEquals($param->getValue(), $unserialized->getValue());
        $this->assertEquals($param->getPrice(), $unserialized->getPrice());
        $this->assertEquals($param->getCurrency(), $unserialized->getCurrency());

        $new = new PriceParam($adapter, $unserialized->getPrice(), $unserialized->getCurrency());
        $this->assertEquals($param->getPrice(), $new->getPrice());
        $this->assertEquals($param->getCurrency(), $new->getCurrency());
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
