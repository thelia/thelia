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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\CartItem;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The discount of a customer is read from the database by their id, never from the copy of
 * the customer the session holds since sign-in: an administrator withdrawing it, or
 * mistyping it, is obeyed by the product page and by the cart at the very next request,
 * the same way. A discount outside 0..100 (0 excluded) counts for nothing, as the one of a
 * group does.
 *
 * Catalog price 100.00 untaxed.
 */
final class CustomerDiscountReadFromTheDatabaseTest extends ActionIntegrationTestCase
{
    use LogsInAsCustomer;
    use ResetsPriceMemos;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::getDefaultCurrency();
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testAnAdministratorWithdrawingTheDiscountIsObeyedByThePageAndByTheCart(): void
    {
        $product = $this->product();
        $customer = $this->customerWithADiscount('10.000000');
        $this->signInWithTheCopyOfTheSession($customer);

        self::assertEqualsWithDelta(90.0, $this->pageBestPriceOf($product), 0.000001, 'the discount is there at sign-in');

        $this->theAdministratorSets($customer, '0.000000');

        self::assertEqualsWithDelta(100.0, $this->pageBestPriceOf($product), 0.000001, 'the page');
        self::assertEqualsWithDelta(100.0, (float) $this->addToCart($customer, $product)->getRealPrice(), 0.000001, 'the cart');
    }

    public function testAnAdministratorGrantingADiscountIsObeyedByThePageAndByTheCart(): void
    {
        $product = $this->product();
        $customer = $this->customerWithADiscount('0.000000');
        $this->signInWithTheCopyOfTheSession($customer);

        $this->theAdministratorSets($customer, '20.000000');

        self::assertEqualsWithDelta(80.0, $this->pageBestPriceOf($product), 0.000001, 'the page');
        self::assertEqualsWithDelta(80.0, (float) $this->addToCart($customer, $product)->getRealPrice(), 0.000001, 'the cart');
    }

    public function testADiscountAboveAHundredCountsForNothingEverywhere(): void
    {
        $product = $this->product();
        $customer = $this->customerWithADiscount('0.000000');
        $this->signInWithTheCopyOfTheSession($customer);

        $this->theAdministratorSets($customer, '150.000000');

        self::assertEqualsWithDelta(100.0, $this->pageBestPriceOf($product), 0.000001, 'the page');
        self::assertEqualsWithDelta(100.0, (float) $this->addToCart($customer, $product)->getRealPrice(), 0.000001, 'the cart');
        self::assertFalse($this->getService(PricingActivityChecker::class)->hasDiscountedCustomer($customer));
    }

    public function testTheDiscountIsReadOncePerRequestAndForgottenWithIt(): void
    {
        $customer = $this->customerWithADiscount('10.000000');
        $rate = $this->getService(CustomerDiscountRate::class);

        self::assertSame(10.0, $rate->rateOf($customer));

        CustomerQuery::create()->filterById($customer->getId())->update(['Discount' => '0.000000']);
        self::assertSame(10.0, $rate->rateOf($customer), 'kept for the request');

        $this->getService('services_resetter')->reset();
        self::assertSame(0.0, $rate->rateOf($customer), 'read again by the next one');
    }

    public function testAVisitorAndACustomerNotStoredYetAreAskedNothingOfTheDatabase(): void
    {
        $rate = $this->getService(CustomerDiscountRate::class);

        self::assertSame(0.0, $rate->rateOf(null));
        self::assertSame(15.0, $rate->rateOf((new Customer())->setDiscount('15.000000')));
        self::assertSame(0.0, $rate->rateOf((new Customer())->setDiscount('150.000000')));
    }

    private function customerWithADiscount(string $discount): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer->setDiscount($discount)->save($this->getPropelConnection());

        return $customer;
    }

    /**
     * What the session holds is the customer as serialized at sign-in: later writes to the
     * row never reach it.
     */
    private function signInWithTheCopyOfTheSession(Customer $customer): void
    {
        $this->loginAsCustomerInSession(unserialize(serialize($customer)));
    }

    private function theAdministratorSets(Customer $customer, string $discount): void
    {
        CustomerQuery::create()->filterById($customer->getId())->update(['Discount' => $discount]);

        // Another request: what the previous one remembered is gone.
        $this->resetPriceMemos();
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Discount product']);
    }

    private function pageBestPriceOf(Product $product): float
    {
        foreach ($this->getService(LoopExecutor::class)->execute('product', ['id' => $product->getId()]) as $row) {
            return (float) $row->getVarVal()['BEST_PRICE'];
        }

        self::fail('The loop returned no product.');
    }

    private function addToCart(Customer $customer, Product $product): CartItem
    {
        $event = new CartEvent($this->factory->cart($customer));
        $event
            ->setProductId($product->getId())
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return $event->getCartItem() ?? self::fail('The line was not added.');
    }
}
