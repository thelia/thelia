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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\Currency;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test MatchForEveryoneManager Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForEveryoneManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var FacadeInterface $stubTheliaAdapter */
    protected $stubTheliaAdapter = null;

    /**
     * Generate adapter stub
     *
     * @param int    $cartTotalPrice   Cart total price
     * @param string $checkoutCurrency Checkout currency
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateAdapterStub($cartTotalPrice = 400, $checkoutCurrency = 'EUR')
    {
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue($checkoutCurrency));

        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $currency1 = new Currency();
        $currency1->setCode('EUR');
        $currency2 = new Currency();
        $currency2->setCode('USD');
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue(array($currency1, $currency2)));

        return $stubFacade;
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForEveryoneManager::setValidators
     *
     */
    public function testValidBackOfficeInputOperator()
    {
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryoneManager($stubFacade);
        $operators = array();
        $values = array();
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if condition is always matching
     *
     * @covers Thelia\Condition\Implementation\MatchForEveryoneManager::isMatching
     *
     */
    public function testIsMatching()
    {
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryoneManager($stubFacade);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

}
