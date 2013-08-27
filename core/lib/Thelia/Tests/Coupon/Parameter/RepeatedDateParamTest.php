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
use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Coupon\Validator\RepeatedDateParam;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test RepeatedDateParam Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RepeatedDateParamTest extends \PHPUnit_Framework_TestCase
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
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testInferiorDate()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-07");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth();

        $expected = -1;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeFirstPeriod()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth();

        $expected = 0;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeSecondPeriod()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-08-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth();

        $expected = 0;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthTenTimesThirdPeriod()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-09-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(1, 10);

        $expected = 0;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthTenTimesTensPeriod()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2013-05-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(1, 10);

        $expected = 0;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryFourMonthTwoTimesSecondPeriod()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-11-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(4, 2);

        $expected = 0;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryFourMonthTwoTimesLastPeriod()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2013-03-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(4, 2);

        $expected = 0;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testNotEqualsDateRepeatEveryFourMonthTwoTimes1()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-08-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(4, 2);

        $expected = -1;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testNotEqualsDateRepeatEveryFourMonthTwoTimes2()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-12-08");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(4, 2);

        $expected = -1;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedDateParam::compareTo
     *
     */
    public function testSuperiorDateRepeatEveryFourMonthTwoTimes()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2013-03-09");

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(4, 2);

        $expected = -1;
        $actual = $repeatedDateParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentException()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = 1377012588;

        $repeatedDateParam = new RepeatedDateParam();
        $repeatedDateParam->setFrom($startDateValidator);
        $repeatedDateParam->repeatEveryMonth(4, 2);

        $repeatedDateParam->compareTo($dateToValidate);
    }



    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
