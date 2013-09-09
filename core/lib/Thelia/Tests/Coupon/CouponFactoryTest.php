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
use Thelia\Exception\CouponExpiredException;
use Thelia\Model\Coupon;

require_once 'CouponManagerTest.php';

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test CouponFactory Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    /**
     * Fake CouponQuery->findByCode
     *
     * @param string               $code              Coupon code
     * @param string               $type              Coupon type (object)
     * @param string               $title             Coupon title
     * @param string               $shortDescription  Coupon short description
     * @param string               $description       Coupon description
     * @param float                $amount            Coupon amount
     * @param bool                 $isUsed            If Coupon has been used yet
     * @param bool                 $isEnabled         If Coupon is enabled
     * @param \DateTime            $expirationDate    When Coupon expires
     * @param CouponRuleCollection $rules             Coupon rules
     * @param bool                 $isCumulative      If Coupon is cumulative
     * @param bool                 $isRemovingPostage If Coupon is removing postage
     *
     * @return Coupon
     */
    public function generateCouponModelMock(
        $code = null,
        $type = null,
        $title = null,
        $shortDescription = null,
        $description = null,
        $amount = null,
        $isUsed = null,
        $isEnabled = null,
        $expirationDate = null,
        $rules = null,
        $isCumulative = null,
        $isRemovingPostage = null
    ) {
        $coupon = $this->generateValidCoupon(
            $code,
            $type,
            $title,
            $shortDescription,
            $description,
            $amount,
            $isUsed,
            $isEnabled,
            $expirationDate,
            $rules,
            $isCumulative,
            $isRemovingPostage
        );

        /** @var CouponAdapterInterface $stubCouponBaseAdapter */
        $stubCouponBaseAdapter = $this->getMock(
            'Thelia\Coupon\CouponBaseAdapter',
            array('findOneCouponByCode'),
            array()
        );
        $stubCouponBaseAdapter->expects($this->any())
            ->method('findOneCouponByCode')
            ->will($this->returnValue($coupon));

        return $stubCouponBaseAdapter;
    }



    /**
     * Test if an expired Coupon is build or not (superior)
     *
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     * @expectedException \Thelia\Exception\CouponExpiredException
     */
    public function testBuildCouponFromCodeExpiredDateBefore()
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime("today - 2 months"));

        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock(null, null, null, null, null, null, null, null, $date);
        $couponFactory = new CouponFactory($mockAdapter);
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');
    }

    /**
     * Test if an expired Coupon is build or not (equal)
     *
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     * @expectedException \Thelia\Exception\CouponExpiredException
     */
    public function testBuildCouponFromCodeExpiredDateEquals()
    {
        $date = new \DateTime();

        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock(null, null, null, null, null, null, null, null, $date);
        $couponFactory = new CouponFactory($mockAdapter);
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');
    }

    /**
     * Test if an expired Coupon is build or not (equal)
     *
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     * @expectedException \Thelia\Exception\InvalidRuleException
     */
    public function testBuildCouponFromCodeWithoutRule()
    {
        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock(null, null, null, null, null, null, null, null, null, new CouponRuleCollection(array()));
        $couponFactory = new CouponFactory($mockAdapter);
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');
    }

    /**
     * Test if a CouponInterface can be built from database
     *
     * @covers Thelia\Coupon\CouponFactory::buildCouponFromCode
     */
    public function testBuildCouponFromCode()
    {
        /** @var CouponAdapterInterface $mockAdapter */
        $mockAdapter = $this->generateCouponModelMock();
        $couponFactory = new CouponFactory($mockAdapter);
        /** @var CouponInterface $coupon */
        $coupon = $couponFactory->buildCouponFromCode('XMAS1');

        $this->assertEquals('XMAS1', $coupon->getCode());
        $this->assertEquals('Thelia\Coupon\Type\RemoveXAmount', get_class($coupon));
        $this->assertEquals(CouponManagerTest::VALID_TITLE, $coupon->getTitle());
        $this->assertEquals(CouponManagerTest::VALID_SHORT_DESCRIPTION, $coupon->getShortDescription());
        $this->assertEquals(CouponManagerTest::VALID_DESCRIPTION, $coupon->getDescription());
        $this->assertEquals(10.00, $coupon->getDiscount());
        $this->assertEquals(1, $coupon->isEnabled());

        $date = new \DateTime();
        $date->setTimestamp(strtotime("today + 2 months"));
        $this->assertEquals($date, $coupon->getExpirationDate());

        $rules = $this->generateValidRules();
        $this->assertEquals($rules, $coupon->getRules());

        $this->assertEquals(1, $coupon->isCumulative());
        $this->assertEquals(0, $coupon->isRemovingPostage());
    }

    /**
     * Generate valid CouponRuleInterfaces
     *
     * @return CouponRuleCollection Set of CouponRuleInterface
     */
    protected function generateValidRules()
    {
//        $rule1 = new AvailableForTotalAmount(
//            , array(
//                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
//                    Operators::SUPERIOR,
//                    new PriceParam(
//                        , 40.00, 'EUR'
//                    )
//                )
//            )
//        );
//        $rule2 = new AvailableForTotalAmount(
//            , array(
//                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
//                    Operators::INFERIOR,
//                    new PriceParam(
//                        , 400.00, 'EUR'
//                    )
//                )
//            )
//        );
//        $rules = new CouponRuleCollection(array($rule1, $rule2));
//
//        return $rules;
    }

    /**
     * Generate valid CouponInterface
     *
     * @param string               $code              Coupon code
     * @param string               $type              Coupon type (object)
     * @param string               $title             Coupon title
     * @param string               $shortDescription  Coupon short description
     * @param string               $description       Coupon description
     * @param float                $amount            Coupon amount
     * @param bool                 $isUsed            If Coupon has been used yet
     * @param bool                 $isEnabled         If Coupon is enabled
     * @param \DateTime            $expirationDate    When Coupon expires
     * @param CouponRuleCollection $rules             Coupon rules
     * @param bool                 $isCumulative      If Coupon is cumulative
     * @param bool                 $isRemovingPostage If Coupon is removing postage
     *
     * @return Coupon
     */
    public function generateValidCoupon(
        $code = null,
        $type = null,
        $title = null,
        $shortDescription = null,
        $description = null,
        $amount = null,
        $isUsed = null,
        $isEnabled = null,
        $expirationDate = null,
        $rules = null,
        $isCumulative = null,
        $isRemovingPostage = null
    ) {
        $coupon = new Coupon();

        if ($code === null) {
            $code = 'XMAS1';
        }
        $coupon->setCode($code);

        if ($type === null) {
            $type = 'Thelia\Coupon\Type\RemoveXAmount';
        }
        $coupon->setType($type);

        if ($title === null) {
            $title = CouponManagerTest::VALID_TITLE;
        }
        $coupon->setTitle($title);

        if ($shortDescription === null) {
            $shortDescription = CouponManagerTest::VALID_SHORT_DESCRIPTION;
        }
        $coupon->setShortDescription($shortDescription);

        if ($description === null) {
            $description = CouponManagerTest::VALID_DESCRIPTION;
        }
        $coupon->setDescription($description);

        if ($amount === null) {
            $amount = 10.00;
        }
        $coupon->setAmount($amount);

        if ($isUsed === null) {
            $isUsed = 1;
        }
        $coupon->setIsUsed($isUsed);

        if ($isEnabled === null) {
            $isEnabled = 1;
        }
        $coupon->setIsEnabled($isEnabled);

        if ($isCumulative === null) {
            $isCumulative = 1;
        }
        if ($isRemovingPostage === null) {
            $isRemovingPostage = 0;
        }

        if ($expirationDate === null) {
            $date = new \DateTime();
            $coupon->setExpirationDate(
                $date->setTimestamp(strtotime("today + 2 months"))
            );
        }

        if ($rules === null) {
            $rules = $this->generateValidRules();
        }

        $coupon->setSerializedRules(base64_encode(serialize($rules)));

        $coupon->setIsCumulative($isCumulative);
        $coupon->setIsRemovingPostage($isRemovingPostage);

        return $coupon;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
