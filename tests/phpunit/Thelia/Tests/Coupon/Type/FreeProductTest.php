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

namespace Thelia\Coupon\Type;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\CartItem;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * @package Coupon
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class FreeProductTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Product $freeProduct */
    public $freeProduct;
    public $originalPrice;
    public $originalPromo;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $currency = CurrencyQuery::create()->filterByCode('EUR')->findOne();

        // Find a product
        $this->freeProduct = ProductQuery::create()
            ->joinProductSaleElements("pse_join")
            ->addJoinCondition("pse_join", "is_default = ?", 1, null, \PDO::PARAM_INT)
            ->findOne()
        ;

        if (null === $this->freeProduct) {
            $this->markTestSkipped("You can't run this test as there's no product with associated product_sale_elements");
        }

        $this->originalPrice = $this->freeProduct->getDefaultSaleElements()->getPricesByCurrency($currency)->getPrice();
        $this->originalPromo = $this->freeProduct->getDefaultSaleElements()->getPromo();

        $this->freeProduct->getDefaultSaleElements()->setPromo(false)->save();
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

        $currencies = CurrencyQuery::create();
        $currencies = $currencies->find();
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue($currencies));

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

        $stubDispatcher = $this->getMockBuilder('\Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $stubDispatcher->expects($this->any())
            ->method('dispatch')
            ->will($this->returnCallback(function ($dummy, $cartEvent) {
                $ci = new CartItem();
                $ci
                    ->setId(3)
                    ->setPrice(123)
                    ->setPromo(0)
                    ->setProductId($this->freeProduct->getId())
                ;

                $cartEvent->setCartItem($ci);
            }));

        $stubFacade->expects($this->any())
            ->method('getDispatcher')
            ->will($this->returnValue($stubDispatcher));

        $stubSession = $this->getMockBuilder('\Thelia\Core\HttpFoundation\Session\Session')
            ->disableOriginalConstructor()
            ->getMock();

        $stubSession->expects($this->any())
            ->method('get')
            ->will($this->onConsecutiveCalls(-1, 3));

        $stubRequest = $this->getMockBuilder('\Thelia\Core\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $stubRequest->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($stubSession));

        $stubFacade->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($stubRequest));

        $country = CountryQuery::create()
            ->findOneByByDefault(1);

        $stubFacade->expects($this->any())
            ->method('getDeliveryCountry')
            ->will($this->returnValue($country));

        return $stubFacade;
    }

    public function generateMatchingCart(\PHPUnit_Framework_MockObject_MockObject $stubFacade, $count)
    {
        $product1 = ProductQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();

        $product2 = ProductQuery::create()->filterById($product1->getId(), Criteria::NOT_IN)->addAscendingOrderByColumn('RAND()')->findOne();

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
        $cartItem1Stub
            ->expects($this->any())
            ->method('getPrice')
            ->will($this->returnValue(100))
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
        $cartItem2Stub
            ->expects($this->any())
            ->method('getPrice')
            ->will($this->returnValue(150))

        ;

        $cartStub = $this->getMockBuilder('\Thelia\Model\Cart')
            ->disableOriginalConstructor()
            ->getMock();

        if ($count == 1) {
            $ret = [$cartItem1Stub];
        } else {
            $ret = [$cartItem1Stub, $cartItem2Stub];
        }

        $cartStub
            ->expects($this->any())
            ->method('getCartItems')
            ->will($this->returnValue($ret));

        $stubFacade->expects($this->any())
            ->method('getCart')
            ->will($this->returnValue($cartStub));

        return [$product1->getId(), $product2->getId()];
    }

    public function generateNoMatchingCart(\PHPUnit_Framework_MockObject_MockObject $stubFacade)
    {
        $product2 = new Product();
        $product2->setId(30);

        $cartItem2Stub = $this->getMockBuilder('\Thelia\Model\CartItem')
            ->disableOriginalConstructor()
            ->getMock();

        $cartItem2Stub->expects($this->any())
            ->method('getProduct')
            ->will($this->returnValue($product2))
        ;
        $cartItem2Stub->expects($this->any())
            ->method('getQuantity')
            ->will($this->returnValue(2))
        ;
        $cartItem2Stub
            ->expects($this->any())
            ->method('getPrice')
            ->will($this->returnValue(11000))
        ;

        $cartStub = $this->getMockBuilder('\Thelia\Model\Cart')
            ->disableOriginalConstructor()
            ->getMock();

        $cartStub
            ->expects($this->any())
            ->method('getCartItems')
            ->will($this->returnValue([$cartItem2Stub]));

        $stubFacade->expects($this->any())
            ->method('getCart')
            ->will($this->returnValue($cartStub));
    }

    public function testSet()
    {
        $stubFacade = $this->generateFacadeStub();

        $coupon = new FreeProduct($stubFacade);

        $date = new \DateTime();

        $coupon->set(
            $stubFacade,
            'TEST',
            'TEST Coupon',
            'This is a test coupon title',
            'This is a test coupon description',
            array('percentage' => 10.00, 'products' => [10, 20], 'offered_product_id' => $this->freeProduct->getId(), 'offered_category_id' => 1),
            true,
            true,
            true,
            true,
            254,
            $date->setTimestamp(strtotime("today + 3 months")),
            new ObjectCollection(),
            new ObjectCollection(),
            false
        );

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 40.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $condition2 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::CART_TOTAL => Operators::INFERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 400.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
        );
        $condition2->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions[] = $condition1;
        $conditions[] = $condition2;
        $coupon->setConditions($conditions);

        $this->assertEquals('TEST', $coupon->getCode());
        $this->assertEquals('TEST Coupon', $coupon->getTitle());
        $this->assertEquals('This is a test coupon title', $coupon->getShortDescription());
        $this->assertEquals('This is a test coupon description', $coupon->getDescription());

        $this->assertEquals(true, $coupon->isCumulative());
        $this->assertEquals(true, $coupon->isRemovingPostage());
        $this->assertEquals(true, $coupon->isAvailableOnSpecialOffers());
        $this->assertEquals(true, $coupon->isEnabled());

        $this->assertEquals(254, $coupon->getMaxUsage());
        $this->assertEquals($date, $coupon->getExpirationDate());
    }

    public function testMatchOne()
    {
        $stubFacade = $this->generateFacadeStub();

        $coupon = new FreeProduct($stubFacade);

        $date = new \DateTime();

        $coupon->set(
            $stubFacade,
            'TEST',
            'TEST Coupon',
            'This is a test coupon title',
            'This is a test coupon description',
            array('percentage' => 10.00, 'products' => [10, 20], 'offered_product_id' => $this->freeProduct->getId(), 'offered_category_id' => 1),
            true,
            true,
            true,
            true,
            254,
            $date->setTimestamp(strtotime("today + 3 months")),
            new ObjectCollection(),
            new ObjectCollection(),
            false
        );

        $products = $this->generateMatchingCart($stubFacade, 1);

        $coupon->product_list = $products;

        $this->assertEquals(123.00, $coupon->exec());
    }

    public function testMatchSeveral()
    {
        $stubFacade = $this->generateFacadeStub();

        $coupon = new FreeProduct($stubFacade);

        $date = new \DateTime();

        $coupon->set(
            $stubFacade,
            'TEST',
            'TEST Coupon',
            'This is a test coupon title',
            'This is a test coupon description',
            array('percentage' => 10.00, 'products' => [10, 20], 'offered_product_id' => $this->freeProduct->getId(), 'offered_category_id' => 1),
            true,
            true,
            true,
            true,
            254,
            $date->setTimestamp(strtotime("today + 3 months")),
            new ObjectCollection(),
            new ObjectCollection(),
            false
        );

        $products = $this->generateMatchingCart($stubFacade, 2);

        $coupon->product_list = $products;

        $this->assertEquals(123.00, $coupon->exec());
    }

    public function testNoMatch()
    {
        $stubFacade = $this->generateFacadeStub();

        $coupon = new FreeProduct($stubFacade);

        $date = new \DateTime();

        $coupon->set(
            $stubFacade,
            'TEST',
            'TEST Coupon',
            'This is a test coupon title',
            'This is a test coupon description',
            array('percentage' => 10.00, 'products' => [10, 20], 'offered_product_id' => $this->freeProduct->getId(), 'offered_category_id' => 1),
            true,
            true,
            true,
            true,
            254,
            $date->setTimestamp(strtotime("today + 3 months")),
            new ObjectCollection(),
            new ObjectCollection(),
            false
        );

        $this->generateNoMatchingCart($stubFacade);

        $this->assertEquals(0.00, $coupon->exec());
    }

    public function testGetName()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Coupon test name');

        /** @var FacadeInterface $stubFacade */
        $coupon = new FreeProduct($stubFacade);

        $actual = $coupon->getName();
        $expected = 'Coupon test name';
        $this->assertEquals($expected, $actual);
    }

    public function testGetToolTip()
    {
        $tooltip = 'Coupon test tooltip';
        $stubFacade = $this->generateFacadeStub(399, 'EUR', $tooltip);

        /** @var FacadeInterface $stubFacade */
        $coupon = new FreeProduct($stubFacade);

        $actual = $coupon->getToolTip();
        $expected = $tooltip;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        if (null !== $this->freeProduct) {
            $this->freeProduct->getDefaultSaleElements()->setPromo($this->originalPromo)->save();
        }
    }
}
