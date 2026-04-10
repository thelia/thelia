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

namespace Thelia\Test;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Admin;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Creates test entities with sensible defaults.
 *
 * Reference entities (Lang, Currency, etc.) use findOrCreate: they return
 * existing seed data when available, avoiding duplicates.
 *
 * All writes go through the injected connection so that
 * IntegrationTestCase's transaction rollback catches them.
 */
final class FixtureFactory
{
    private static int $counter = 0;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    private function next(): int
    {
        return ++self::$counter;
    }

    // ------------------------------------------------------------------
    // Reference entities (findOrCreate)
    // ------------------------------------------------------------------

    public function lang(array $overrides = []): Lang
    {
        $existing = LangQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $lang = new Lang();
        $lang->setTitle($overrides['title'] ?? 'English');
        $lang->setCode($overrides['code'] ?? 'en');
        $lang->setLocale($overrides['locale'] ?? 'en_US');
        $lang->setActive($overrides['active'] ?? true);
        $lang->setVisible($overrides['visible'] ?? 1);
        $lang->setByDefault($overrides['byDefault'] ?? 0);
        $lang->setDateFormat($overrides['dateFormat'] ?? 'Y-m-d');
        $lang->setTimeFormat($overrides['timeFormat'] ?? 'H:i:s');
        $lang->setDatetimeFormat($overrides['datetimeFormat'] ?? 'Y-m-d H:i:s');
        $lang->setDecimalSeparator($overrides['decimalSeparator'] ?? '.');
        $lang->setThousandsSeparator($overrides['thousandsSeparator'] ?? ',');
        $lang->setDecimals($overrides['decimals'] ?? '2');
        $lang->save($this->connection);

        return $lang;
    }

    public function currency(array $overrides = []): Currency
    {
        $existing = CurrencyQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $currency = new Currency();
        $currency->setCode($overrides['code'] ?? 'EUR');
        $currency->setSymbol($overrides['symbol'] ?? '€');
        $currency->setRate($overrides['rate'] ?? 1.0);
        $currency->setVisible($overrides['visible'] ?? 1);
        $currency->setByDefault($overrides['byDefault'] ?? 0);
        $currency->save($this->connection);

        return $currency;
    }

    public function customerTitle(array $overrides = []): CustomerTitle
    {
        $existing = CustomerTitleQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $n = $this->next();
        $title = new CustomerTitle();
        $title->setPosition($overrides['position'] ?? (string) $n);
        $title->setByDefault($overrides['byDefault'] ?? 0);
        $title->save($this->connection);

        return $title;
    }

    public function country(array $overrides = []): Country
    {
        $existing = CountryQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $country = new Country();
        $country->setIsocode($overrides['isocode'] ?? 'FR');
        $country->setIsoalpha2($overrides['isoalpha2'] ?? 'FR');
        $country->setIsoalpha3($overrides['isoalpha3'] ?? 'FRA');
        $country->setVisible($overrides['visible'] ?? 1);
        $country->setShopCountry($overrides['shopCountry'] ?? true);
        $country->save($this->connection);

        return $country;
    }

    public function taxRule(array $overrides = []): TaxRule
    {
        $existing = TaxRuleQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $taxRule = new TaxRule();
        $taxRule->setIsDefault($overrides['isDefault'] ?? false);
        $taxRule->save($this->connection);

        return $taxRule;
    }

    // ------------------------------------------------------------------
    // Structural entities
    // ------------------------------------------------------------------

    public function category(array $overrides = []): Category
    {
        $category = new Category();
        $category->setParent($overrides['parent'] ?? 0);
        $category->setVisible($overrides['visible'] ?? 1);
        $category->setPosition($overrides['position'] ?? $this->next());
        $category->save($this->connection);

        return $category;
    }

    // ------------------------------------------------------------------
    // Business entities (dependencies are explicit)
    // ------------------------------------------------------------------

    public function product(
        Category $category,
        TaxRule $taxRule,
        Currency $currency,
        array $overrides = [],
    ): Product {
        $n = $this->next();

        $product = new Product();
        $product
            ->setRef($overrides['ref'] ?? 'PROD-'.$n)
            ->setVisible($overrides['visible'] ?? 1)
            ->setPosition($overrides['position'] ?? $n);

        // Product::create() handles the full creation in a transaction:
        // persist the product, assign default category, create default PSE + price.
        $product->create(
            $category->getId(),
            $overrides['basePrice'] ?? 10.0,
            $currency->getId(),
            $taxRule->getId(),
            $overrides['baseWeight'] ?? 0.0,
            $overrides['baseQuantity'] ?? 0,
        );

        return $product;
    }

    public function customer(
        CustomerTitle $title,
        array $overrides = [],
    ): Customer {
        $n = $this->next();

        $customer = new Customer();
        $customer->setTitleId($title->getId());
        $customer->setFirstname($overrides['firstname'] ?? 'John');
        $customer->setLastname($overrides['lastname'] ?? 'Doe');
        $customer->setEmail($overrides['email'] ?? 'customer-'.$n.'@test.com');
        $customer->setPassword($overrides['password'] ?? 'password');
        $customer->save($this->connection);

        return $customer;
    }

    public function admin(array $overrides = []): Admin
    {
        $n = $this->next();

        $admin = new Admin();
        $admin->setFirstname($overrides['firstname'] ?? 'Admin');
        $admin->setLastname($overrides['lastname'] ?? 'Test');
        $admin->setLogin($overrides['login'] ?? 'admin-'.$n);
        $admin->setPassword($overrides['password'] ?? 'password');
        $admin->setLocale($overrides['locale'] ?? 'en_US');
        $admin->setEmail($overrides['email'] ?? 'admin-'.$n.'@test.com');
        $admin->save($this->connection);

        return $admin;
    }
}
