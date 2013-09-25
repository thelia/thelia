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
use Thelia\Constraint\Validator\IntervalParam;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test IntervalParam Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class IntervalParamTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {
        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

//    /**
//     * Sets up the fixture, for example, opens a network connection.
//     * This method is called before a test is executed.
//     */
//    protected function setUp()
//    {
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Parameter\IntervalParam::compareTo
//     *
//     */
//    public function testInferiorDate()
//    {
//        $adapter = new BaseAdapter();
//        $dateValidatorStart = new \DateTime("2012-07-08");
//        $dateValidatorInterval = new \DateInterval("P1M"); //1month
//        $dateToValidate = new \DateTime("2012-07-07");
//
//        $dateParam = new IntervalParam($adapter, $dateValidatorStart, $dateValidatorInterval);
//
//        $expected = 1;
//        $actual = $dateParam->compareTo($dateToValidate);
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Parameter\IntervalParam::compareTo
//     *
//     */
//    public function testEqualsDate()
//    {
//        $adapter = new BaseAdapter();
//        $dateValidatorStart = new \DateTime("2012-07-08");
//        $dateValidatorInterval = new \DateInterval("P1M"); //1month
//        $dateToValidate = new \DateTime("2012-07-08");
//
//        echo '1 ' . date_format($dateValidatorStart, 'g:ia \o\n l jS F Y') . "\n";
//        echo '2 ' . date_format($dateToValidate, 'g:ia \o\n l jS F Y') . "\n";
//
//        $dateParam = new IntervalParam($adapter, $dateValidatorStart, $dateValidatorInterval);
//
//        $expected = 0;
//        $actual = $dateParam->compareTo($dateToValidate);
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Parameter\IntervalParam::compareTo
//     *
//     */
//    public function testEqualsDate2()
//    {
//        $adapter = new BaseAdapter();
//        $dateValidatorStart = new \DateTime("2012-07-08");
//        $dateValidatorInterval = new \DateInterval("P1M"); //1month
//        $dateToValidate = new \DateTime("2012-08-08");
//
//        $dateParam = new IntervalParam($adapter, $dateValidatorStart, $dateValidatorInterval);
//
//        $expected = 0;
//        $actual = $dateParam->compareTo($dateToValidate);
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Parameter\IntervalParam::compareTo
//     *
//     */
//    public function testSuperiorDate()
//    {
//        $adapter = new BaseAdapter();
//        $dateValidatorStart = new \DateTime("2012-07-08");
//        $dateValidatorInterval = new \DateInterval("P1M"); //1month
//        $dateToValidate = new \DateTime("2012-08-09");
//
//        $dateParam = new IntervalParam($adapter, $dateValidatorStart, $dateValidatorInterval);
//
//        $expected = -1;
//        $actual = $dateParam->compareTo($dateToValidate);
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
//     * @expectedException InvalidArgumentException
//     */
//    public function testInvalidArgumentException()
//    {
//        $adapter = new BaseAdapter();
//        $dateValidatorStart = new \DateTime("2012-07-08");
//        $dateValidatorInterval = new \DateInterval("P1M"); //1month
//        $dateToValidate = 1377012588;
//
//        $dateParam = new IntervalParam($adapter, $dateValidatorStart, $dateValidatorInterval);
//
//        $dateParam->compareTo($dateToValidate);
//    }
//
//    /**
//     * Test is the object is serializable
//     * If no data is lost during the process
//     */
//    public function isSerializableTest()
//    {
//        $adapter = new BaseAdapter();
//        $dateValidatorStart = new \DateTime("2012-07-08");
//        $dateValidatorInterval = new \DateInterval("P1M"); //1month
//
//        $param = new IntervalParam($adapter, $dateValidatorStart, $dateValidatorInterval);
//
//        $serialized = base64_encode(serialize($param));
//        /** @var IntervalParam $unserialized */
//        $unserialized = base64_decode(serialize($serialized));
//
//        $this->assertEquals($param->getValue(), $unserialized->getValue());
//        $this->assertEquals($param->getDatePeriod(), $unserialized->getDatePeriod());
//
//        $new = new IntervalParam($adapter, $unserialized->getStart(), $unserialized->getInterval());
//        $this->assertEquals($param->getDatePeriod(), $new->getDatePeriod());
//    }
//
//    /**
//     * Tears down the fixture, for example, closes a network connection.
//     * This method is called after a test is executed.
//     */
//    protected function tearDown()
//    {
//    }

}
