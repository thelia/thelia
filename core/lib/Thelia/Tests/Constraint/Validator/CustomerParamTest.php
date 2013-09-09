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
use Thelia\Constraint\Validator\CustomerParam;
use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\QuantityParam;
use Thelia\Model\Customer;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test CustomerParam Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CustomerParamTest extends \PHPUnit_Framework_TestCase
{

    public function testSomething()
    {
        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

//    /** @var CouponAdapterInterface $stubTheliaAdapter */
//    protected $stubTheliaAdapter = null;
//
//    /**
//     * Sets up the fixture, for example, opens a network connection.
//     * This method is called before a test is executed.
//     */
//    protected function setUp()
//    {
//        /** @var CouponAdapterInterface $stubTheliaAdapter */
//        $this->stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();
//    }
//
//    /**
//     * Generate valid CouponBaseAdapter
//     *
//     * @param int $customerId Customer id
//     *
//     * @return CouponAdapterInterface
//     */
//    protected function generateValidCouponBaseAdapterMock($customerId = 4521)
//    {
//        $customer = new Customer();
//        $customer->setId($customerId);
//        $customer->setFirstname('Firstname');
//        $customer->setLastname('Lastname');
//        $customer->setEmail('em@il.com');
//
//        /** @var CouponAdapterInterface $stubTheliaAdapter */
//        $stubTheliaAdapter = $this->getMock(
//            'Thelia\Coupon\CouponBaseAdapter',
//            array('getCustomer'),
//            array()
//        );
//        $stubTheliaAdapter->expects($this->any())
//            ->method('getCustomer')
//            ->will($this->returnValue($customer));
//
//        return $stubTheliaAdapter;
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Parameter\QuantityParam::compareTo
//     *
//     */
//    public function testCanUseCoupon()
//    {
//        $customerId = 4521;
//        $couponValidForCustomerId = 4521;
//
//        $adapter = $this->generateValidCouponBaseAdapterMock($customerId);
//
//        $customerParam = new CustomerParam($adapter, $couponValidForCustomerId);
//
//        $expected = 0;
//        $actual = $customerParam->compareTo($customerId);
//        $this->assertEquals($expected, $actual);
//    }
//
////    /**
////     *
////     * @covers Thelia\Coupon\Parameter\QuantityParam::compareTo
////     *
////     */
////    public function testCanNotUseCouponTest()
////    {
////
////    }
////
////    /**
////     *
////     * @covers Thelia\Coupon\Parameter\QuantityParam::compareTo
////     * @expectedException InvalidArgumentException
////     *
////     */
////    public function testCanNotUseCouponCustomerNotFoundTest()
////    {
////
////    }
//
//
//
//
////    /**
////     * Test is the object is serializable
////     * If no data is lost during the process
////     */
////    public function isSerializableTest()
////    {
////        $adapter = new CouponBaseAdapter();
////        $intValidator = 42;
////        $intToValidate = -1;
////
////        $param = new QuantityParam($adapter, $intValidator);
////
////        $serialized = base64_encode(serialize($param));
////        /** @var QuantityParam $unserialized */
////        $unserialized = base64_decode(serialize($serialized));
////
////        $this->assertEquals($param->getValue(), $unserialized->getValue());
////        $this->assertEquals($param->getInteger(), $unserialized->getInteger());
////
////        $new = new QuantityParam($adapter, $unserialized->getInteger());
////        $this->assertEquals($param->getInteger(), $new->getInteger());
////    }
//
//    /**
//     * Tears down the fixture, for example, closes a network connection.
//     * This method is called after a test is executed.
//     */
//    protected function tearDown()
//    {
//    }

}
