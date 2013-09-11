<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\TaxEngine;

use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Tax;
use Thelia\TaxEngine\Calculator;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class CalculatorTest extends \PHPUnit_Framework_TestCase
{
    protected function getMethod($name)
    {
        $class = new \ReflectionClass('\Thelia\TaxEngine\Calculator');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    protected function getProperty($name)
    {
        $class = new \ReflectionClass('\Thelia\TaxEngine\Calculator');
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 501
     */
    public function testLoadEmptyProductException()
    {
        $calculator = new Calculator();
        $calculator->load(new Product(), CountryQuery::create()->findOne());
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 502
     */
    public function testLoadEmptyCountryException()
    {
        $calculator = new Calculator();
        $calculator->load(ProductQuery::create()->findOne(), new Country());
    }

    public function testLoad()
    {
        $productQuery = ProductQuery::create()->findOneById(1);
        $countryQuery = CountryQuery::create()->findOneById(64);

        $calculator = new Calculator();

        $taxRuleQuery = $this->getMock('\Thelia\Model\TaxRuleQuery', array('getTaxCalculatorCollection'));
        $taxRuleQuery->expects($this->once())
            ->method('getTaxCalculatorCollection')
            ->with($productQuery, $countryQuery)
            ->will($this->returnValue('foo'));

        $rewritingUrlQuery = $this->getProperty('taxRuleQuery');
        $rewritingUrlQuery->setValue($calculator, $taxRuleQuery);

        $calculator->load($productQuery, $countryQuery);

        $this->assertEquals(
            $productQuery,
            $this->getProperty('product')->getValue($calculator)
        );
        $this->assertEquals(
            $countryQuery,
            $this->getProperty('country')->getValue($calculator)
        );
        $this->assertEquals(
            'foo',
            $this->getProperty('taxRulesCollection')->getValue($calculator)
        );
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 503
     */
    public function testGetTaxAmountBadTaxRulesCollection()
    {
        $calculator = new Calculator();
        $calculator->getTaxAmount(500);
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 601
     */
    public function testGetTaxAmountBadAmount()
    {
        $taxRulesCollection = new ObjectCollection();

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $calculator->getTaxAmount('foo');
    }

    public function testGetTaxAmountAndGetTaxedPrice()
    {
        $taxRulesCollection = new ObjectCollection();
        $taxRulesCollection->setModel('\Thelia\Model\Tax');

        $tax = new Tax();
        $tax->setType('PricePercentTaxType')
            ->setRequirements(array('percent' => 10));

        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('PricePercentTaxType')
            ->setRequirements(array('percent' => 8));

        $taxRulesCollection->append($tax);

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $taxAmount = $calculator->getTaxAmount(500);
        $taxedPrice = $calculator->getTaxedPrice(500);

        /*
         * expect :
         *  tax 1 = 500*0.10 = 50 // amout with tax 1 : 550
         *  tax 2 = 550*0.08 = 44 // amout with tax 2 : 594
         * total tax amount = 94
         */
        $this->assertEquals(94, $taxAmount);
        $this->assertEquals(594, $taxedPrice);
    }
}
