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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Condition\SerializableCondition;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\Address;
use Thelia\Model\Lang;

/**
 * @package Coupon
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class StartDateTest extends \PHPUnit_Framework_TestCase
{
    public $startDate;
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->startDate = time() - 2000;
    }

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

        $address = new Address();
        $address->setCountryId(10);

        $stubFacade->expects($this->any())
            ->method('getDeliveryAddress')
            ->will($this->returnValue($address));

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

        $lang = new Lang();
        $lang->setDateFormat("d/m/Y");

        $stubSession = $this->getMockBuilder('\Thelia\Core\HttpFoundation\Session\Session')
            ->disableOriginalConstructor()
            ->getMock();

        $stubSession->expects($this->any())
            ->method('getLang')
            ->will($this->returnValue($lang));

        $stubRequest = $this->getMockBuilder('\Thelia\Core\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $stubRequest->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($stubSession));

        $stubFacade->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($stubRequest));

        return $stubFacade;
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\StartDate::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     */
    public function testInValidBackOfficeInputOperator()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new StartDate($stubFacade);

        $operators = array(
            StartDate::START_DATE => 'petite licorne'
        );
        $values = array(
            StartDate::START_DATE => $this->startDate
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\StartDate::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     */
    public function testInValidBackOfficeInputValue()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new StartDate($stubFacade);
        $operators = array(
            StartDate::START_DATE => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            StartDate::START_DATE => 'petit poney'
        );

        $condition1->setValidatorsFromForm($operators, $values);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\StartDate::isMatching
     *
     */
    public function testMatchingRule()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new StartDate($stubFacade);
        $operators = array(
            StartDate::START_DATE => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            StartDate::START_DATE => $this->startDate
        );

        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\StartDate::isMatching
     *
     */
    public function testNotMatching()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new StartDate($stubFacade);

        $operators = array(
            StartDate::START_DATE => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            StartDate::START_DATE => time() + 2000
        );

        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testGetSerializableRule()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new StartDate($stubFacade);

        $operators = array(
            StartDate::START_DATE => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            StartDate::START_DATE => $this->startDate
        );

        $condition1->setValidatorsFromForm($operators, $values);

        $serializableRule = $condition1->getSerializableCondition();

        $expected = new SerializableCondition();
        $expected->conditionServiceId = $condition1->getServiceId();
        $expected->operators = $operators;
        $expected->values = $values;

        $actual = $serializableRule;

        $this->assertEquals($expected, $actual);
    }

    /**
     * Check getName i18n
     *
     * @covers Thelia\Condition\Implementation\StartDate::getName
     *
     */
    public function testGetName()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Number of articles in cart');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new StartDate($stubFacade);

        $actual = $condition1->getName();
        $expected = 'Number of articles in cart';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check tooltip i18n
     *
     * @covers Thelia\Condition\Implementation\StartDate::getToolTip
     *
     */
    public function testGetToolTip()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Sample coupon condition');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new StartDate($stubFacade);

        $actual = $condition1->getToolTip();
        $expected = 'Sample coupon condition';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check validator
     *
     * @covers Thelia\Condition\Implementation\StartDate::generateInputs
     *
     */
    public function testGetValidator()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Price');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new StartDate($stubFacade);

        $operators = array(
            StartDate::START_DATE => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            StartDate::START_DATE => $this->startDate
        );

        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getValidators();

        $validators = array(
            'inputs' => array(
                StartDate::START_DATE => array(
                    'availableOperators' => array(
                        '>=' => 'Price',
                    ),
                    'value' => '',
                    'selectedOperator' => '>='
                )
            ),
            'setOperators' => array(
                'start_date' => '>='
            ),
            'setValues' => array(
                'start_date' => $this->startDate
            )
        );
        $expected = $validators;

        $this->assertEquals($expected, $actual);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
