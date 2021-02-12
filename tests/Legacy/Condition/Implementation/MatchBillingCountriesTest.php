<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Condition\Implementation;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Condition\SerializableCondition;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\Address;

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class MatchBillingCountriesTest extends TestCase
{
    /**
     * Generate adapter stub.
     *
     * @param int    $cartTotalPrice   Cart total price
     * @param string $checkoutCurrency Checkout currency
     * @param string $i18nOutput       Output from each translation
     *
     * @return MockObject
     */
    public function generateFacadeStub($cartTotalPrice = 400, $checkoutCurrency = 'EUR', $i18nOutput = '')
    {
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $address = new Address();
        $address->setCountryId(10);

        $stubCustomer = $this->getMockBuilder('\Thelia\Model\Customer')
            ->disableOriginalConstructor()
            ->getMock();

        $stubCustomer->expects($this->any())
            ->method('getDefaultAddress')
            ->will($this->returnValue($address));

        $stubFacade->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($stubCustomer));

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

        return $stubFacade;
    }

    /**
     * Check if validity test on BackOffice inputs are working.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::setValidators
     */
    public function testInValidBackOfficeInputOperator(): void
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new MatchBillingCountries($stubFacade);
        $operators = [
            MatchBillingCountries::COUNTRIES_LIST => Operators::INFERIOR_OR_EQUAL,
        ];
        $values = [
            MatchBillingCountries::COUNTRIES_LIST => [],
        ];

        $this->expectException(\Thelia\Exception\InvalidConditionOperatorException::class);
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::setValidators
     */
    public function testInValidBackOfficeInputValue(): void
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new MatchBillingCountries($stubFacade);
        $operators = [
            MatchBillingCountries::COUNTRIES_LIST => Operators::IN,
        ];
        $values = [
            MatchBillingCountries::COUNTRIES_LIST => [],
        ];

        $this->expectException(\Thelia\Exception\InvalidConditionValueException::class);
        $condition1->setValidatorsFromForm($operators, $values);
    }

    /**
     * Check if test inferior operator is working.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::isMatching
     */
    public function testMatchingRule(): void
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new MatchBillingCountries($stubFacade);
        $operators = [
            MatchBillingCountries::COUNTRIES_LIST => Operators::IN,
        ];
        $values = [
            MatchBillingCountries::COUNTRIES_LIST => [10, 20],
        ];

        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::isMatching
     */
    public function testNotMatching(): void
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new MatchBillingCountries($stubFacade);

        $operators = [
            MatchBillingCountries::COUNTRIES_LIST => Operators::IN,
        ];
        $values = [
            MatchBillingCountries::COUNTRIES_LIST => [50, 60],
        ];

        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testGetSerializableRule(): void
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new MatchBillingCountries($stubFacade);

        $operators = [
            MatchBillingCountries::COUNTRIES_LIST => Operators::IN,
        ];
        $values = [
            MatchBillingCountries::COUNTRIES_LIST => [50, 60],
        ];

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
     * Check getName i18n.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::getName
     */
    public function testGetName(): void
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Number of articles in cart');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchBillingCountries($stubFacade);

        $actual = $condition1->getName();
        $expected = 'Number of articles in cart';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check tooltip i18n.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::getToolTip
     */
    public function testGetToolTip(): void
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Sample coupon condition');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchBillingCountries($stubFacade);

        $actual = $condition1->getToolTip();
        $expected = 'Sample coupon condition';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check validator.
     *
     * @covers \Thelia\Condition\Implementation\MatchBillingCountries::generateInputs
     */
    public function testGetValidator(): void
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Price');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchBillingCountries($stubFacade);

        $operators = [
            MatchBillingCountries::COUNTRIES_LIST => Operators::IN,
        ];
        $values = [
            MatchBillingCountries::COUNTRIES_LIST => [50, 60],
        ];

        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getValidators();

        $validators = [
            'inputs' => [
                MatchBillingCountries::COUNTRIES_LIST => [
                    'availableOperators' => [
                        'in' => 'Price',
                        'out' => 'Price',
                    ],
                    'value' => '',
                    'selectedOperator' => 'in',
                ],
            ],
            'setOperators' => [
                'countries' => 'in',
            ],
            'setValues' => [
                'countries' => [50, 60],
            ],
        ];
        $expected = $validators;

        $this->assertEquals($expected, $actual);
    }
}
