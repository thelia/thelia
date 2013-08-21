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

use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Coupon\Parameter\RepeatedIntervalParam;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Thrown when a Rule receive an invalid Parameter
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RepeatedIntervalParamTest extends \PHPUnit_Framework_TestCase
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
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testInferiorDate()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-07");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);

        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = -1;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeFirstPeriodBegining()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-08");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeFirstPeriodMiddle()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-13");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeFirstPeriodEnding()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-18");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeSecondPeriodBegining()
    {
        $startDateValidator = new \DateTime("2012-08-08");
        $dateToValidate = new \DateTime("2012-08-08");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeSecondPeriodMiddle()
    {
        $startDateValidator = new \DateTime("2012-08-08");
        $dateToValidate = new \DateTime("2012-08-13");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthOneTimeSecondPeriodEnding()
    {
        $startDateValidator = new \DateTime("2012-08-08");
        $dateToValidate = new \DateTime("2012-08-18");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth();

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthFourTimeLastPeriodBegining()
    {
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-10-08");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthFourTimeLastPeriodMiddle()
    {
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-10-13");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testEqualsDateRepeatEveryMonthFourTimeLastPeriodEnding()
    {
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-10-18");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $expected = 0;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testNotEqualsDateRepeatEveryMonthFourTimeInTheBegining()
    {
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-07-19");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $expected = -1;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testNotEqualsDateRepeatEveryMonthFourTimeInTheMiddle()
    {
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-08-01");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $expected = -1;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }


    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testNotEqualsDateRepeatEveryMonthFourTimeInTheEnd()
    {
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-08-07");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $expected = -1;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }



    /**
     *
     * @covers Thelia\Coupon\Parameter\RepeatedIntervalParam::compareTo
     *
     */
    public function testSuperiorDateRepeatEveryMonthFourTime()
    {
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-10-19");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 0);

        $expected = -1;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
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
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam();
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $RepeatedIntervalParam->compareTo($dateToValidate);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
