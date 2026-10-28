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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\API\ResourceCache;
use Thelia\Api\Service\API\ResourceMemoizer;
use Thelia\Api\Service\API\ResourceService;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The cross-request cache of the data access layer is switched on, as a shop may switch it on:
 * what a customer was served is never served to a customer of another group, nor to a
 * visitor, and what a visitor was served is never served to a customer a price of their own
 * is owed to - in whichever order they come.
 *
 * Catalog price 100.00 untaxed.
 */
final class ResourceCacheCustomerPriceTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use ResetsPriceMemos;

    private FixtureFactory $factory;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        // Read when the cache service is built, which is after the kernel booted.
        putenv('THELIA_DATA_ACCESS_CACHE=1');
        $_ENV['THELIA_DATA_ACCESS_CACHE'] = $_SERVER['THELIA_DATA_ACCESS_CACHE'] = '1';

        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->category = $this->factory->category();
        $this->product = $this->factory->product($this->category, $this->factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => 100.0, 'baseQuantity' => 10, 'title' => 'Cached product']);
        $this->getService(ResourceCache::class)->clear();
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->getService(ResourceCache::class)->clear();
        $this->resetPriceMemos();
        putenv('THELIA_DATA_ACCESS_CACHE');
        unset($_ENV['THELIA_DATA_ACCESS_CACHE'], $_SERVER['THELIA_DATA_ACCESS_CACHE']);

        parent::tearDown();
    }

    public function testTheCacheIsOnAndKeepsAVisitorsRead(): void
    {
        self::assertSame(100.0, $this->itemPrice());

        // Proof the cache is the one answering: the catalog moves under it, the visitor does not see it.
        $this->product->getDefaultSaleElements()->getProductPrices()->getFirst()->setPrice('55.000000')->save($this->getPropelConnection());
        self::assertSame(100.0, $this->itemPrice(), 'The shop caches while nothing prices per visitor.');
    }

    public function testTwoGroupsThenAVisitorThenTheFirstGroupAgainAreEachServedTheirOwnPrice(): void
    {
        $first = $this->member($this->factory->customerGroup(discount: 20.0));
        $second = $this->member($this->factory->customerGroup(discount: 50.0), 10.0);

        foreach ([[$first, 80.0], [$second, 45.0], [null, 100.0], [$first, 80.0], [null, 100.0], [$second, 45.0]] as [$customer, $price]) {
            $this->signInAs($customer);

            self::assertSame($price, $this->itemPrice(), 'item');
            self::assertSame($price, $this->listedPrice(), 'list');
        }
    }

    /**
     * The groups are on the cache, each on its own entry: the catalog moves under it, and
     * every group, then the visitor, is still served what it was served first.
     */
    public function testEachGroupIsServedFromTheCacheItsOwnEntryWhileTheCatalogMovesUnderIt(): void
    {
        $first = $this->member($this->factory->customerGroup(discount: 20.0));
        $second = $this->member($this->factory->customerGroup(discount: 50.0));
        $sameGroupAsFirst = $this->member($first->getCustomerGroup());

        foreach ([[$first, 80.0], [$second, 50.0], [null, 100.0]] as [$customer, $price]) {
            $this->signInAs($customer);

            self::assertSame($price, $this->itemPrice(), 'first read');
            self::assertSame($price, $this->listedPrice(), 'first listed read');
        }

        $this->product->getDefaultSaleElements()->getProductPrices()->getFirst()->setPrice('60.000000')->save($this->getPropelConnection());

        foreach ([[$first, 80.0], [$second, 50.0], [null, 100.0], [$first, 80.0], [$sameGroupAsFirst, 80.0], [null, 100.0]] as [$customer, $price]) {
            $this->signInAs($customer);

            self::assertSame($price, $this->itemPrice(), 'served from the cache of its group');
            self::assertSame($price, $this->listedPrice(), 'listed from the cache of its group');
        }

        // A group nobody has read yet is computed on the moved catalog, which proves the
        // figures above came from the cache and not from the database.
        $third = $this->member($this->factory->customerGroup(discount: 10.0));
        $this->signInAs($third);
        self::assertSame(54.0, $this->itemPrice());
    }

    public function testACustomerWithADiscountOfTheirOwnIsServedFreshWhileTheirGroupStaysCached(): void
    {
        $group = $this->factory->customerGroup(discount: 20.0);
        $plain = $this->member($group);
        $discounted = $this->member($group, 10.0);

        $this->signInAs($plain);
        self::assertSame(80.0, $this->itemPrice());

        $this->product->getDefaultSaleElements()->getProductPrices()->getFirst()->setPrice('60.000000')->save($this->getPropelConnection());

        $this->signInAs($discounted);
        self::assertSame(43.2, $this->itemPrice(), 'computed on the moved catalog: 60 x 0.8 x 0.9');

        $this->signInAs($plain);
        self::assertSame(80.0, $this->itemPrice(), 'what the discounted customer was charged was not written to the entry of the group');
    }

    public function testTheDiscountOfACustomerAloneIsNeitherServedToTheOthersNorHiddenFromThem(): void
    {
        // No group discounts, no group prices, no rule: nothing but a customer row prices anything.
        $discounted = $this->member($this->factory->customerGroup(), 10.0);
        $plain = $this->member($this->factory->customerGroup());

        foreach ([[null, 100.0], [$discounted, 90.0], [null, 100.0], [$plain, 100.0], [$discounted, 90.0]] as [$customer, $price]) {
            $this->signInAs($customer);

            self::assertSame($price, $this->itemPrice(), 'item');
            self::assertSame($price, $this->listedPrice(), 'list');
        }
    }

    private function signInAs(?Customer $customer): void
    {
        $this->logoutCustomer();

        if (null !== $customer) {
            $this->loginAsCustomerInSession($customer);
        }

        // One request is over, another begins: what a request memoizes dies with it.
        $this->getService(ResourceMemoizer::class)->clear();
        $this->resetPriceMemos();
    }

    private function itemPrice(): float
    {
        $payload = $this->getService(ResourceService::class)->resources('/api/front/products/'.$this->product->getId(), [], 'jsonld');
        self::assertIsArray($payload);

        return round((float) $payload['productSaleElements'][0]['productPrices'][0]['price'], 6);
    }

    private function listedPrice(): float
    {
        $payload = $this->getService(ResourceService::class)->resources('/api/front/products', ['tfilters' => ['category' => [[$this->category->getId()]]], 'itemsPerPage' => 50], 'jsonld');
        self::assertIsArray($payload);
        self::assertCount(1, $payload['hydra:member']);

        return round((float) $payload['hydra:member'][0]['productSaleElements'][0]['productPrices'][0]['price'], 6);
    }

    private function member(CustomerGroup $group, float $customerDiscount = 0.0): Customer
    {
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $member->setDiscount((string) $customerDiscount)->save($this->getPropelConnection());

        return $member;
    }
}
