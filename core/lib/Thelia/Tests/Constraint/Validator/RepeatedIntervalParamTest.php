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
use Thelia\Constraint\Validator\RepeatedIntervalParam;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test RepeatedIntervalParam Class
 *
 * @package Constraint
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-07");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
    public function testEqualsDateRepeatEveryMonthOneTimeFirstPeriodBeginning()
    {
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-08");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-13");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-07-18");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
    public function testEqualsDateRepeatEveryMonthOneTimeSecondPeriodBeginning()
    {
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-08-08");
        $dateToValidate = new \DateTime("2012-08-08");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-08-08");
        $dateToValidate = new \DateTime("2012-08-13");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-08-08");
        $dateToValidate = new \DateTime("2012-08-18");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
    public function testEqualsDateRepeatEveryMonthFourTimeLastPeriodBeginning()
    {
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-10-08");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-10-13");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-10-18");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
    public function testNotEqualsDateRepeatEveryMonthFourTimeInTheBeginning()
    {
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-07-19");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-08-01");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-10-08");
        $dateToValidate = new \DateTime("2012-08-07");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
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
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = new \DateTime("2012-10-19");
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 0);

        $expected = -1;
        $actual = $RepeatedIntervalParam->compareTo($dateToValidate);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Thelia\Coupon\Parameter\DateParam::compareTo
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidArgumentException()
    {
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = 1377012588;
        $duration = 10;

        $RepeatedIntervalParam = new RepeatedIntervalParam($adapter);
        $RepeatedIntervalParam->setFrom($startDateValidator);
        $RepeatedIntervalParam->setDurationInDays($duration);
        $RepeatedIntervalParam->repeatEveryMonth(1, 4);

        $RepeatedIntervalParam->compareTo($dateToValidate);
    }

    /**
     * Test is the object is serializable
     * If no data is lost during the process
     */
    public function isSerializableTest()
    {
        $adapter = new CouponBaseAdapter();
        $startDateValidator = new \DateTime("2012-07-08");
        $dateToValidate = 1377012588;
        $duration = 10;

        $param = new RepeatedIntervalParam($adapter);
        $param->setFrom($startDateValidator);
        $param->setDurationInDays($duration);
        $param->repeatEveryMonth(1, 4);

        $serialized = base64_encode(serialize($param));
        /** @var RepeatedIntervalParam $unserialized */
        $unserialized = base64_decode(serialize($serialized));

        $this->assertEquals($param->getValue(), $unserialized->getValue());
        $this->assertEquals($param->getDatePeriod(), $unserialized->getDatePeriod());

        $new = new RepeatedIntervalParam($adapter);
        $new->setFrom($unserialized->getFrom());
        $new->repeatEveryMonth($unserialized->getFrequency(), $unserialized->getNbRepetition());
        $new->setDurationInDays($unserialized->getDurationInDays());
        $this->assertEquals($param->getDatePeriod(), $new->getDatePeriod());
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
