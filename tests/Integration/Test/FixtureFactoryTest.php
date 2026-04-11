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

namespace Thelia\Tests\Integration\Test;

use Thelia\Model\AdminQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class FixtureFactoryTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testReferenceEntitiesReturnExistingSeedData(): void
    {
        $lang1 = $this->factory->lang();
        $lang2 = $this->factory->lang();

        self::assertSame($lang1->getId(), $lang2->getId(), 'lang() must return the same seeded entity');

        $currency1 = $this->factory->currency();
        $currency2 = $this->factory->currency();

        self::assertSame($currency1->getId(), $currency2->getId());
    }

    public function testCategoryCreation(): void
    {
        $category = $this->factory->category();

        self::assertNotNull($category->getId());
        self::assertSame(0, $category->getParent());
        self::assertSame(1, $category->getVisible());
    }

    public function testProductCreationWithPSE(): void
    {
        $category = $this->factory->category();
        $taxRule = $this->factory->taxRule();
        $currency = $this->factory->currency();

        $product = $this->factory->product($category, $taxRule, $currency);

        self::assertNotNull($product->getId());
        self::assertStringStartsWith('PROD-', $product->getRef());

        $pse = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        self::assertNotNull($pse, 'Product must have a default PSE');
    }

    public function testCustomerCreationWithHashedPassword(): void
    {
        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);

        self::assertNotNull($customer->getId());
        self::assertSame('PASSWORD_BCRYPT', $customer->getAlgo());
        self::assertTrue(password_verify('password', $customer->getPassword()));
    }

    public function testAdminCreation(): void
    {
        $admin = $this->factory->admin();

        self::assertNotNull($admin->getId());
        self::assertStringStartsWith('admin-', $admin->getLogin());
        self::assertSame('PASSWORD_BCRYPT', $admin->getAlgo());
        self::assertTrue(password_verify('password', $admin->getPassword()));
    }

    public function testCounterEnsuresUniqueness(): void
    {
        $admin1 = $this->factory->admin();
        $admin2 = $this->factory->admin();

        self::assertNotSame($admin1->getLogin(), $admin2->getLogin());
        self::assertNotSame($admin1->getEmail(), $admin2->getEmail());
    }

    public function testOverridesAreApplied(): void
    {
        $category = $this->factory->category(['visible' => 0, 'parent' => 0]);

        self::assertSame(0, $category->getVisible());
    }

    public function testAllFixtureDataIsRolledBack(): void
    {
        $countBefore = AdminQuery::create()->count();

        $this->factory->admin();
        $this->factory->admin();
        $this->factory->admin();

        // These 3 admins exist within the transaction...
        self::assertSame($countBefore + 3, AdminQuery::create()->count());

        // ...but will be rolled back in tearDown().
        // The next test can verify this.
    }
}
