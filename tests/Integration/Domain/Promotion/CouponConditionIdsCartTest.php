<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Domain\Promotion;

use BackOfficeDefaultTwigBundle\Service\Coupon\CouponConditionsRenderer;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\CartContainsCategories;
use Thelia\Condition\Implementation\CartContainsProducts;
use Thelia\Condition\Implementation\ForSomeCustomers;
use Thelia\Condition\Implementation\MatchBillingCountries;
use Thelia\Condition\Operators;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * The coupon conditions that hold a list of ids, saved the way the back-office
 * saves them: CouponConditionsRenderer::buildConditionFromRequest() reads the
 * posted ids as strings, the condition is stored, then rebuilt from the
 * database on every cart evaluation, where the ids of the cart, the customer
 * and its addresses are integers. The API stores the ids as integers.
 */
final class CouponConditionIdsCartTest extends ActionIntegrationPromotionTestCase
{
    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given.
        if (!class_exists(CouponConditionsRenderer::class)) {
            self::markTestSkipped('The default-twig back-office theme is not installed.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // The next test must not start with this one's customer signed in.
        if (class_exists(CouponConditionsRenderer::class)) {
            $this->getService(SecurityContext::class)->clearCustomerUser();
        }

        parent::tearDown();
    }

    public function testACartContainsProductsConditionSavedByTheBackOfficeMatches(): void
    {
        $product = $this->product();
        $this->promotionWhen('thelia.condition.cart_contains_products', CartContainsProducts::PRODUCTS_LIST, Operators::IN, [$product->getId()]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $product);

        self::assertSame(5.0, $this->cartDiscount($cart), 'The cart holds the product of the condition.');
    }

    public function testACartContainsProductsConditionWithTheNotInOperatorExcludesItsProducts(): void
    {
        $product = $this->product();
        $this->promotionWhen('thelia.condition.cart_contains_products', CartContainsProducts::PRODUCTS_LIST, Operators::OUT, [$product->getId()]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $product);

        self::assertSame(0.0, $this->cartDiscount($cart), 'The cart holds nothing but the excluded product.');
    }

    public function testACartContainsCategoriesConditionSavedByTheBackOfficeMatches(): void
    {
        $category = $this->factory->category();
        $this->promotionWhen('thelia.condition.cart_contains_categories', CartContainsCategories::CATEGORIES_LIST, Operators::IN, [$category->getId()]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->productIn($category));

        self::assertSame(5.0, $this->cartDiscount($cart), 'The cart holds a product of the category of the condition.');
    }

    public function testACustomersConditionSavedByTheBackOfficeMatches(): void
    {
        $customer = $this->signedInCustomer();
        $this->promotionWhen('thelia.condition.for_some_customers', ForSomeCustomers::CUSTOMERS_LIST, Operators::IN, [$customer->getId()]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart), 'The customer of the condition is signed in.');
    }

    public function testABillingCountryConditionSavedByTheBackOfficeMatches(): void
    {
        $customer = $this->signedInCustomer();
        $country = $this->factory->country();
        $this->factory->address($customer, $country)->setIsDefault(1)->save();
        $this->promotionWhen('thelia.condition.match_billing_countries', MatchBillingCountries::COUNTRIES_LIST, Operators::IN, [$country->getId()]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart), 'The billing address of the customer is in the country of the condition.');
    }

    public function testAConditionStoredWithIntegerIdsStillMatches(): void
    {
        $product = $this->product();

        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.cart_contains_products',
            [CartContainsProducts::PRODUCTS_LIST => Operators::IN],
            [CartContainsProducts::PRODUCTS_LIST => [$product->getId()]],
        );
        $this->automaticPromotion(conditions: $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions));

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $product);

        self::assertSame(5.0, $this->cartDiscount($cart));
    }

    /**
     * Saves an automatic promotion of 5.00 under one condition, built from the
     * request the condition editor of the back-office posts.
     *
     * @param list<int> $ids
     */
    private function promotionWhen(string $conditionServiceId, string $field, string $operator, array $ids): void
    {
        $request = Request::create('/admin/coupon/1/condition/save', 'POST', [
            'categoryCondition' => $conditionServiceId,
            $field => [
                'operator' => $operator,
                'value' => array_map('\strval', $ids),
            ],
        ]);

        $renderer = $this->getService(CouponConditionsRenderer::class);
        self::assertInstanceOf(CouponConditionsRenderer::class, $renderer);

        $condition = $renderer->buildConditionFromRequest($request);
        self::assertNotNull($condition);

        $conditions = new ConditionCollection();
        $conditions[] = $condition;

        $this->automaticPromotion(conditions: $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions));
    }

    private function signedInCustomer(): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        return $customer;
    }

    private function cartDiscount(Cart $cart): float
    {
        $cart->reload();

        return (float) $cart->getDiscount();
    }
}
