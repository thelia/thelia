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
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Model\Category;
use Thelia\Model\Product;

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class CartContainsCategoriesTest extends TestCase
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

        $category1 = new Category();
        $category1->setId(10);

        $category2 = new Category();
        $category2->setId(20);

        $category3 = new Category();
        $category3->setId(30);

        $product1 = new Product();
        $product1->addCategory($category1)->addCategory($category2);

        $product2 = new Product();
        $product2->addCategory($category3);

        $cartItem1Stub = $this->getMockBuilder('\Thelia\Model\CartItem')
            ->disableOriginalConstructor()
            ->getMock();

        $cartItem1Stub
            ->expects($this->any())
            ->method('getProduct')
            ->will($this->returnValue($product1))
        ;

        $cartItem1Stub
            ->expects($this->any())
            ->method('getQuantity')
            ->will($this->returnValue(1))
        ;

        $cartItem2Stub = $this->getMockBuilder('\Thelia\Model\CartItem')
            ->disableOriginalConstructor()
            ->getMock();

        $cartItem2Stub
            ->expects($this->any())
            ->method('getProduct')
            ->will($this->returnValue($product2));

        $cartItem2Stub
            ->expects($this->any())
            ->method('getQuantity')
            ->will($this->returnValue(2))
        ;

        $cartStub = $this->getMockBuilder('\Thelia\Model\Cart')
            ->disableOriginalConstructor()
            ->getMock();

        $cartStub
            ->expects($this->any())
            ->method('getCartItems')
            ->will($this->returnValue([$cartItem1Stub, $cartItem2Stub]));

        $stubFacade->expects($this->any())
            ->method('getCart')
            ->will($this->returnValue($cartStub));

        return $stubFacade;
    }

    /**
     * Check if validity test on BackOffice inputs are working.
     *
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::setValidators
     */
    public function testInValidBackOfficeInputOperator()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new CartContainsCategories($stubFacade);
        $operators = [
            CartContainsCategories::CATEGORIES_LIST => Operators::INFERIOR_OR_EQUAL,
        ];
        $values = [
            CartContainsCategories::CATEGORIES_LIST => [],
        ];
        $this->expectException(InvalidConditionOperatorException::class);
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working.
     *
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::setValidators
     */
    public function testInValidBackOfficeInputValue()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new CartContainsCategories($stubFacade);
        $operators = [
            CartContainsCategories::CATEGORIES_LIST => Operators::IN,
        ];
        $values = [
            CartContainsCategories::CATEGORIES_LIST => [],
        ];

        $this->expectException(InvalidConditionValueException::class);
        $condition1->setValidatorsFromForm($operators, $values);
    }

    /**
     * Check if test inferior operator is working.
     *
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::isMatching
     */
    public function testMatchingRule()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new CartContainsCategories($stubFacade);
        $operators = [
            CartContainsCategories::CATEGORIES_LIST => Operators::IN,
        ];
        $values = [
            CartContainsCategories::CATEGORIES_LIST => [10, 20],
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
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::isMatching
     */
    public function testNotMatching()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new CartContainsCategories($stubFacade);

        $operators = [
            CartContainsCategories::CATEGORIES_LIST => Operators::IN,
        ];
        $values = [
            CartContainsCategories::CATEGORIES_LIST => [50, 60],
        ];

        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual = $isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testGetSerializableRule()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateFacadeStub();

        $condition1 = new CartContainsCategories($stubFacade);

        $operators = [
            CartContainsCategories::CATEGORIES_LIST => Operators::IN,
        ];
        $values = [
            CartContainsCategories::CATEGORIES_LIST => [50, 60],
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
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::getName
     */
    public function testGetName()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Number of articles in cart');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new CartContainsCategories($stubFacade);

        $actual = $condition1->getName();
        $expected = 'Number of articles in cart';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check tooltip i18n.
     *
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::getToolTip
     */
    public function testGetToolTip()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Sample coupon condition');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new CartContainsCategories($stubFacade);

        $actual = $condition1->getToolTip();
        $expected = 'Sample coupon condition';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check validator.
     *
     * @covers \Thelia\Condition\Implementation\CartContainsCategories::generateInputs
     */
    public function testGetValidator()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Price');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new CartContainsCategories($stubFacade);

        $operators = [
            CartContainsCategories::CATEGORIES_LIST => Operators::IN,
        ];
        $values = [
            CartContainsCategories::CATEGORIES_LIST => [50, 60],
        ];

        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getValidators();

        $validators = [
            'inputs' => [
                CartContainsCategories::CATEGORIES_LIST => [
                    'availableOperators' => [
                        'in' => 'Price',
                        'out' => 'Price',
                    ],
                    'value' => '',
                    'selectedOperator' => 'in',
                ],
            ],
            'setOperators' => [
                'categories' => 'in',
            ],
            'setValues' => [
                'categories' => [50, 60],
            ],
        ];
        $expected = $validators;

        $this->assertEquals($expected, $actual);
    }
}
