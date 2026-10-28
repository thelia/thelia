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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Api\Service\API\ResourceMemoizer;
use Thelia\Api\Service\API\ResourceService;
use Thelia\Core\Event\Customer\CustomerEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The cross-request cache is on, and the API publishes the taxed price: it follows the
 * country the visitor is delivered to, and the currency it is browsed in. Two visitors of
 * the same customer group, delivered to two countries or browsing in two currencies, are
 * never served each other's figure.
 *
 * Catalog price 100.00 untaxed: 20 % in the default country, 5.5 % in the other one.
 */
final class ResourceCacheTaxedPriceDestinationTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use ResetsPriceMemos;

    private FixtureFactory $factory;

    private Product $product;

    private Country $otherCountry;

    protected function setUp(): void
    {
        // Read when the cache service is built, which is after the kernel booted.
        putenv('THELIA_DATA_ACCESS_CACHE=1');
        $_ENV['THELIA_DATA_ACCESS_CACHE'] = $_SERVER['THELIA_DATA_ACCESS_CACHE'] = '1';

        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->otherCountry = $this->factory->country(['isocode' => '276', 'isoalpha2' => 'DE', 'isoalpha3' => 'DEU']);
        $taxRule = $this->taxRuleAt([Country::getDefaultCountry()->getId() => '20.000000', $this->otherCountry->getId() => '5.500000']);
        $this->product = $this->factory->product($this->factory->category(), $taxRule, Currency::getDefaultCurrency(), ['basePrice' => 100.0, 'baseQuantity' => 10]);
        $this->getService(ResourceCache::class)->clear();
        $this->newRequest();
    }

    protected function tearDown(): void
    {
        $this->getService(ResourceCache::class)->clear();
        putenv('THELIA_DATA_ACCESS_CACHE');
        unset($_ENV['THELIA_DATA_ACCESS_CACHE'], $_SERVER['THELIA_DATA_ACCESS_CACHE']);

        parent::tearDown();
    }

    public function testTwoCountriesOfOneGroupAreEachServedTheirOwnTaxedPrice(): void
    {
        $home = $this->customerDeliveredTo(Country::getDefaultCountry());
        $abroad = $this->customerDeliveredTo($this->otherCountry);

        foreach ([[$home, 120.0], [$abroad, 105.5], [null, 120.0], [$abroad, 105.5], [$home, 120.0]] as [$customer, $taxed]) {
            $this->signInAs($customer);

            self::assertEqualsWithDelta($taxed, $this->price()['taxedPrice'], 1e-9);
        }
    }

    /**
     * The destination is read once per request: whatever moves it inside the request makes the next
     * price read it again, instead of serving the figure of the country the visitor has just left.
     */
    public function testADestinationThatMovesInsideTheRequestIsReadAgain(): void
    {
        $abroad = $this->customerDeliveredTo($this->otherCountry);
        $homeKey = $this->getService(ProductTaxedPriceResolver::class)->destinationKey();
        $resolver = $this->getService(ProductTaxedPriceResolver::class);
        self::assertEqualsWithDelta(120.0, $resolver->taxedPrice((int) $this->product->getId(), 100.0), 1e-9);

        // Signed in during the request: the login is heard, the delivery country is the one of the customer.
        $this->loginAsCustomerInSession($abroad);
        $this->getService(EventDispatcherInterface::class)->dispatch(new CustomerLoginEvent($abroad), TheliaEvents::CUSTOMER_LOGIN);

        self::assertNotSame($homeKey, $this->getService(ProductTaxedPriceResolver::class)->destinationKey());
        self::assertEqualsWithDelta(105.5, $resolver->taxedPrice((int) $this->product->getId(), 100.0), 1e-9, 'the price follows the destination, not the one read at the start of the request');

        // And back: signed out.
        $this->logoutCustomer();
        $this->getService(EventDispatcherInterface::class)->dispatch(new CustomerEvent($abroad), TheliaEvents::CUSTOMER_LOGOUT);

        self::assertSame($homeKey, $this->getService(ProductTaxedPriceResolver::class)->destinationKey());
    }

    public function testTwoCurrenciesOfOneVisitorAreEachServedTheirOwnPrice(): void
    {
        $other = $this->factory->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 3.0 * (float) Currency::getDefaultCurrency()->getRate(), 'byDefault' => 0]);

        foreach ([[Currency::getDefaultCurrency(), 100.0], [$other, 300.0], [Currency::getDefaultCurrency(), 100.0], [$other, 300.0]] as [$currency, $untaxed]) {
            $this->newRequest();
            $this->session()->setCurrency($currency);

            self::assertEqualsWithDelta($untaxed, $this->price()['price'], 1e-6);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function price(): array
    {
        $payload = $this->getService(ResourceService::class)->resources('/api/front/products/'.$this->product->getId(), [], 'jsonld');
        self::assertIsArray($payload);

        return $payload['productSaleElements'][0]['productPrices'][0];
    }

    private function customerDeliveredTo(Country $country): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country)->setIsDefault(1)->save($this->getPropelConnection());

        return $customer;
    }

    private function signInAs(?Customer $customer): void
    {
        $this->logoutCustomer();

        if (null !== $customer) {
            $this->loginAsCustomerInSession($customer);
        }

        $this->newRequest();
    }

    /**
     * One request is over, another begins: what a request memoizes dies with it.
     */
    private function newRequest(): void
    {
        $this->getService(ResourceMemoizer::class)->clear();
        $this->getService(ProductTaxedPriceResolver::class)->reset();
        $this->getService(ProductPriceCurrencyListener::class)->forgetCurrentCurrency();
        $this->resetPriceMemos();
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    /**
     * @param array<int, string> $percentByCountryId
     */
    private function taxRuleAt(array $percentByCountryId): TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);

        foreach ($percentByCountryId as $countryId => $percent) {
            $tax = $this->factory->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

            (new TaxRuleCountry())
                ->setTaxRuleId($taxRule->getId())
                ->setCountryId($countryId)
                ->setTaxId($tax->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
        }

        return $taxRule;
    }
}
