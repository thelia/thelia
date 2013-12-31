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
 * Unit Test MatchForEveryone Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForEveryoneTest extends \PHPUnit_Framework_TestCase
{
    /** @var FacadeInterface $stubTheliaAdapter */
    protected $stubTheliaAdapter = null;

    /**
     * Generate adapter stub
     *
     * @param int    $cartTotalPrice   Cart total price
     * @param string $checkoutCurrency Checkout currency
     * @param string $i18nOutput       Output from each translation
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateFacadeStub($cartTotalPrice = 400, $checkoutCurrency = 'EUR', $i18nOutput = '')
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

        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnValue($i18nOutput));

        $stubFacade->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

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
     * @covers Thelia\Condition\Implementation\MatchForEveryone::setValidators
     *
     */
    public function testValidBackOfficeInputOperator()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryone($stubFacade);
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
     * @covers Thelia\Condition\Implementation\MatchForEveryone::isMatching
     *
     */
    public function testIsMatching()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryone($stubFacade);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check getName i18n
     *
     * @covers Thelia\Condition\Implementation\MatchForEveryone::getName
     *
     */
    public function testGetName()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Everybody can use it (no condition)');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryone($stubFacade);

        $actual = $condition1->getName();
        $expected = 'Everybody can use it (no condition)';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check tooltip i18n
     *
     * @covers Thelia\Condition\Implementation\MatchForEveryone::getToolTip
     *
     */
    public function testGetToolTip()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Will return always true');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryone($stubFacade);

        $actual = $condition1->getToolTip();
        $expected = 'Will return always true';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check validator
     *
     * @covers Thelia\Condition\Implementation\MatchForEveryone::generateInputs
     * @covers Thelia\Condition\Implementation\MatchForEveryone::setValidatorsFromForm
     */
    public function testGetValidator()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForEveryone($stubFacade);
        $actual1 = $condition1->setValidatorsFromForm(array(), array());
        $expected1 = $condition1;
        $actual2 = $condition1->getValidators();

        $validators = array();
        $validators['inputs'] = array();
        $validators['setOperators'] = array();
        $validators['setValues'] = array();
        $expected2 = $validators;

        $this->assertEquals($expected1, $actual1);
        $this->assertEquals($expected2, $actual2);


    }
}
