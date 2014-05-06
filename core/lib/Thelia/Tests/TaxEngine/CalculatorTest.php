<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
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
        $productQuery = ProductQuery::create()->findOne();
        $countryQuery = CountryQuery::create()->findOneById(64);

        $calculator = new Calculator();

        $taxRuleQuery = $this->getMock('\Thelia\Model\TaxRuleQuery', array('getTaxCalculatorCollection'));
        $taxRuleQuery->expects($this->once())
            ->method('getTaxCalculatorCollection')
            ->with($productQuery->getTaxRule(), $countryQuery)
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
    public function testGetTaxedPriceBadTaxRulesCollection()
    {
        $calculator = new Calculator();
        $calculator->getTaxedPrice(500);
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 601
     */
    public function testGetTaxedPriceBadAmount()
    {
        $taxRulesCollection = new ObjectCollection();

        $aProduct = ProductQuery::create()->findOne();
        if (null === $aProduct) {
            return;
        }

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $product = $this->getProperty('product');
        $product->setValue($calculator, $aProduct);

        $calculator->getTaxedPrice('foo');
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 501
     */
    public function testGetUntaxedPriceAndGetTaxAmountFromTaxedPriceWithNoProductLoaded()
    {
        $taxRulesCollection = new ObjectCollection();
        $taxRulesCollection->setModel('\Thelia\Model\Tax');

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $calculator->getTaxAmountFromTaxedPrice(600.95);
    }

    /**
     * @expectedException \Thelia\Exception\TaxEngineException
     * @expectedExceptionCode 507
     */
    public function testGetUntaxedPriceAndGetTaxAmountFromTaxedPriceWithEmptyTaxRuleCollection()
    {
        $taxRulesCollection = new ObjectCollection();
        $taxRulesCollection->setModel('\Thelia\Model\Tax');

        $aProduct = ProductQuery::create()->findOne();
        if (null === $aProduct) {
            return;
        }

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $product = $this->getProperty('product');
        $product->setValue($calculator, $aProduct);

        $calculator->getTaxAmountFromTaxedPrice(600.95);
    }

    public function testGetTaxedPriceAndGetTaxAmountFromUntaxedPrice()
    {
        $taxRulesCollection = new ObjectCollection();
        $taxRulesCollection->setModel('\Thelia\Model\Tax');

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(array('percent' => 10))
            ->setVirtualColumn('taxRuleCountryPosition', 1);
        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(array('percent' => 8))
            ->setVirtualColumn('taxRuleCountryPosition', 1);
        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\FixAmountTaxType')
            ->setRequirements(array('amount' => 5))
            ->setVirtualColumn('taxRuleCountryPosition', 2);
        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(array('percent' => 1))
            ->setVirtualColumn('taxRuleCountryPosition', 3);
        $taxRulesCollection->append($tax);

        $aProduct = ProductQuery::create()->findOne();
        if (null === $aProduct) {
            return;
        }

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $product = $this->getProperty('product');
        $product->setValue($calculator, $aProduct);

        $taxAmount = $calculator->getTaxAmountFromUntaxedPrice(500);
        $taxedPrice = $calculator->getTaxedPrice(500);

        /*
         * expect :
         *  tax 1 = 500*0.10 = 50 + 500*0.08 = 40 // amount with tax 1 : 590
         *  tax 2 = 5 // amount with tax 2 : 595
         *  tax 3 = 595 * 0.01 = 5.95 // amount with tax 3 : 600.95
         * total tax amount = 100.95
         */
        $this->assertEquals(100.95, $taxAmount);
        $this->assertEquals(600.95, $taxedPrice);
    }

    public function testGetUntaxedPriceAndGetTaxAmountFromTaxedPrice()
    {
        $taxRulesCollection = new ObjectCollection();
        $taxRulesCollection->setModel('\Thelia\Model\Tax');

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(array('percent' => 10))
            ->setVirtualColumn('taxRuleCountryPosition', 1);
        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(array('percent' => 8))
            ->setVirtualColumn('taxRuleCountryPosition', 1);
        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\FixAmountTaxType')
            ->setRequirements(array('amount' => 5))
            ->setVirtualColumn('taxRuleCountryPosition', 2);
        $taxRulesCollection->append($tax);

        $tax = new Tax();
        $tax->setType('\Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(array('percent' => 1))
            ->setVirtualColumn('taxRuleCountryPosition', 3);
        $taxRulesCollection->append($tax);

        $aProduct = ProductQuery::create()->findOne();
        if (null === $aProduct) {
            return;
        }

        $calculator = new Calculator();

        $rewritingUrlQuery = $this->getProperty('taxRulesCollection');
        $rewritingUrlQuery->setValue($calculator, $taxRulesCollection);

        $product = $this->getProperty('product');
        $product->setValue($calculator, $aProduct);

        $taxAmount = $calculator->getTaxAmountFromTaxedPrice(600.95);
        $untaxedPrice = $calculator->getUntaxedPrice(600.95);

        /*
         * expect :
         *  tax 3 = 600.95 - 600.95 / (1 + 0.01) = 5,95 // amount without tax 3 : 595
         *  tax 2 = 5 // amount without tax 2 : 590
         *  tax 1 = 590 - 590 / (1 + 0.08 + 0.10) = 90 // amount without tax 1 : 500
         * total tax amount = 100.95
         */
        $this->assertEquals(100.95, $taxAmount);
        $this->assertEquals(500, $untaxedPrice);
    }
}
