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

use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
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
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Creates test entities with sensible defaults.
 *
 * Reference entities (Lang, Currency, etc.) use findOrCreate: they return
 * existing seed data when available, avoiding duplicates.
 *
 * Structural/business entities use an internal counter for unique fields.
 */
final class FixtureFactory
{
    private int $counter = 0;

    private function next(): int
    {
        return ++$this->counter;
    }

    // ------------------------------------------------------------------
    // Reference entities (findOrCreate)
    // ------------------------------------------------------------------

    public function lang(array $overrides = []): Lang
    {
        $existing = LangQuery::create()->findOne();
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
        $lang->save();

        return $lang;
    }

    public function currency(array $overrides = []): Currency
    {
        $existing = CurrencyQuery::create()->findOne();
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $currency = new Currency();
        $currency->setCode($overrides['code'] ?? 'EUR');
        $currency->setSymbol($overrides['symbol'] ?? '€');
        $currency->setRate($overrides['rate'] ?? 1.0);
        $currency->setVisible($overrides['visible'] ?? 1);
        $currency->setByDefault($overrides['byDefault'] ?? 0);
        $currency->save();

        return $currency;
    }

    public function customerTitle(array $overrides = []): CustomerTitle
    {
        $existing = CustomerTitleQuery::create()->findOne();
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $n = $this->next();
        $title = new CustomerTitle();
        $title->setPosition($overrides['position'] ?? (string) $n);
        $title->setByDefault($overrides['byDefault'] ?? 0);
        $title->save();

        return $title;
    }

    public function country(array $overrides = []): Country
    {
        $existing = CountryQuery::create()->findOne();
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $country = new Country();
        $country->setIsocode($overrides['isocode'] ?? 'FR');
        $country->setIsoalpha2($overrides['isoalpha2'] ?? 'FR');
        $country->setIsoalpha3($overrides['isoalpha3'] ?? 'FRA');
        $country->setVisible($overrides['visible'] ?? 1);
        $country->setShopCountry($overrides['shopCountry'] ?? true);
        $country->save();

        return $country;
    }

    public function taxRule(array $overrides = []): TaxRule
    {
        $existing = TaxRuleQuery::create()->findOne();
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $taxRule = new TaxRule();
        $taxRule->setIsDefault($overrides['isDefault'] ?? false);
        $taxRule->save();

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
        $category->save();

        return $category;
    }

    // ------------------------------------------------------------------
    // Business entities (dependencies are explicit)
    // ------------------------------------------------------------------

    public function product(
        Category $category,
        TaxRule $taxRule,
        array $overrides = [],
    ): Product {
        $n = $this->next();

        $product = new Product();
        $product->setRef($overrides['ref'] ?? 'PROD-'.$n);
        $product->setTaxRuleId($taxRule->getId());
        $product->setVisible($overrides['visible'] ?? 1);
        $product->setPosition($overrides['position'] ?? $n);
        $product->save();

        // Assign to default category
        $product->addCategory($category);
        $product->save();

        return $product;
    }

    public function customer(
        CustomerTitle $title,
        array $overrides = [],
    ): Customer {
        $n = $this->next();

        $customer = new Customer();
        $customer->createOrUpdateWithoutAddress(
            titleId: $title->getId(),
            firstname: $overrides['firstname'] ?? 'John',
            lastname: $overrides['lastname'] ?? 'Doe',
            email: $overrides['email'] ?? 'customer-'.$n.'@test.com',
            plainPassword: $overrides['password'] ?? 'password',
        );

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
        $admin->save();

        return $admin;
    }
}
