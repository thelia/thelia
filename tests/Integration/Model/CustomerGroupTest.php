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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupNotFoundException;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupProtectedException;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupPaymentModuleQuery;
use Thelia\Model\CustomerGroupProductPriceQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Every customer belongs to exactly one group, and the shop always holds a
 * default one: a creation that names no group joins it, and a shop that lost it
 * is told so instead of failing later on a foreign key.
 */
final class CustomerGroupTest extends IntegrationTestCase
{
    public function testTheInstallSeedsOneDefaultGroupWithNoDiscountAndTaxedPrices(): void
    {
        $default = CustomerGroupQuery::findDefault();

        self::assertSame('default', $default->getCode());
        self::assertSame(1, $default->getIsDefault());
        self::assertSame(1, $default->getDisplayTaxedPrice());
        self::assertSame(0.0, (float) $default->getDiscount());
        self::assertSame(1, CustomerGroupQuery::create()->filterByIsDefault(1)->count(), 'exactly one default group');
        self::assertSame(
            'Default customer group',
            $default->setLocale('en_US')->getTitle(),
        );
    }

    public function testACustomerCreatedWithoutAGroupJoinsTheDefaultGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        self::assertSame((int) CustomerGroupQuery::findDefault()->getId(), $customer->getCustomerGroupId());
        self::assertSame(
            $customer->getCustomerGroupId(),
            CustomerQuery::create()->findPk($customer->getId())?->getCustomerGroupId(),
            'the group is stored, not only set on the object',
        );
    }

    public function testACustomerCreatedInAnotherGroupKeepsIt(): void
    {
        $factory = $this->createFixtureFactory();
        $group = (new CustomerGroup())
            ->setCode('pro-'.uniqid())
            ->setPosition(2);
        $group->save();

        $customer = new Customer();
        $customer
            ->setTitleId((int) $factory->customerTitle()->getId())
            ->setFirstname('Pro')
            ->setLastname('Buyer')
            ->setEmail('pro-'.uniqid().'@test.com')
            ->setPassword('password')
            ->setCustomerGroupId((int) $group->getId())
            ->save();

        self::assertSame((int) $group->getId(), $customer->getCustomerGroupId());
    }

    public function testFindingTheDefaultGroupFailsExplicitlyWhenNoneIsTheDefault(): void
    {
        CustomerGroupQuery::create()->update(['IsDefault' => 0]);

        $this->expectException(DefaultCustomerGroupNotFoundException::class);

        CustomerGroupQuery::findDefault();
    }

    public function testCreatingACustomerFailsExplicitlyWhenNoGroupIsTheDefault(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        CustomerGroupQuery::create()->update(['IsDefault' => 0]);

        $this->expectException(DefaultCustomerGroupNotFoundException::class);

        $factory->customer($title);
    }

    public function testTheDefaultGroupCannotBeDeleted(): void
    {
        $default = CustomerGroupQuery::findDefault();

        try {
            $default->delete();
            self::fail('the default group was deleted');
        } catch (DefaultCustomerGroupProtectedException) {
            self::assertNotNull(CustomerGroupQuery::create()->findPk($default->getId()), 'the group is still there');
        }
    }

    public function testTheOnlyDefaultGroupCannotStopBeingTheDefault(): void
    {
        $default = CustomerGroupQuery::findDefault();

        $this->expectException(DefaultCustomerGroupProtectedException::class);

        $default->setIsDefault(0)->save();
    }

    public function testFlaggingAnotherGroupReleasesThePreviousDefault(): void
    {
        $factory = $this->createFixtureFactory();
        $previous = CustomerGroupQuery::findDefault();
        $next = $factory->customerGroup();

        $next->setIsDefault(1)->save();

        self::assertSame(1, CustomerGroupQuery::create()->filterByIsDefault(1)->count());
        self::assertSame($next->getId(), CustomerGroupQuery::findDefault()->getId());
        self::assertSame(0, CustomerGroupQuery::create()->findPk($previous->getId())?->getIsDefault());

        // The released group is an ordinary one again.
        CustomerGroupQuery::create()->findPk($previous->getId())?->delete();
        self::assertNull(CustomerGroupQuery::create()->findPk($previous->getId()));
    }

    public function testDeletingAGroupHandsItsCustomersToTheDefaultGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup(code: 'to-delete');
        $title = $factory->customerTitle();
        $first = $factory->customer($title, ['customerGroup' => $group]);
        $second = $factory->customer($title, ['customerGroup' => $group]);
        $elsewhere = $factory->customer($title, ['customerGroup' => $factory->customerGroup()]);
        $defaultId = (int) CustomerGroupQuery::findDefault()->getId();

        self::assertSame($group->getId(), $first->getCustomerGroupId());

        $group->delete();

        self::assertNull(CustomerGroupQuery::create()->findPk($group->getId()));
        self::assertSame($defaultId, CustomerQuery::create()->findPk($first->getId())?->getCustomerGroupId());
        self::assertSame($defaultId, CustomerQuery::create()->findPk($second->getId())?->getCustomerGroupId());
        self::assertNotSame($defaultId, CustomerQuery::create()->findPk($elsewhere->getId())?->getCustomerGroupId(), 'a customer of another group stays there');
    }

    /**
     * The lists of a group are its own: deleting it hands its customers the default group's
     * catalog and payment modules, and changes nothing for anybody else.
     */
    public function testAGroupThatListsCategoriesAndPaymentModulesCanBeDeleted(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $listed = $factory->category();
        $other = $factory->category();
        $group = $factory->customerGroup(code: 'only-door', categoryIds: [(int) $listed->getId()], paymentModuleIds: [(int) $module->getId()]);
        $member = $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);
        $consumer = $factory->customer($factory->customerTitle());
        $visibility = $this->getService(CategoryVisibility::class);
        $this->getService('services_resetter')->reset();

        self::assertSame([(int) $other->getId()], $visibility->closedCategoryIdsFor($member));
        self::assertSame([], $visibility->closedCategoryIdsFor($consumer));

        $group->delete();
        $this->getService('services_resetter')->reset();

        self::assertNull(CustomerGroupQuery::create()->findPk($group->getId()));
        self::assertSame(0, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($group->getId())->count());
        self::assertSame(0, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($group->getId())->count());
        self::assertSame([], $visibility->closedCategoryIdsFor(CustomerQuery::create()->findPk($member->getId())), 'the member now browses as the default group');
        self::assertSame([], $visibility->closedCategoryIdsFor($consumer));
    }

    public function testTheFixtureBuildsAGroupWithItsRestrictions(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $category = $factory->category();

        $group = $factory->customerGroup(
            code: 'pro',
            discount: 15.5,
            displayTaxedPrice: false,
            title: 'Professionals',
            paymentModuleIds: [(int) $module->getId()],
            categoryIds: [(int) $category->getId()],
        );

        $stored = CustomerGroupQuery::create()->findPk($group->getId());
        self::assertSame('pro', $stored?->getCode());
        self::assertSame(15.5, (float) $stored->getDiscount());
        self::assertSame(0, $stored->getDisplayTaxedPrice());
        self::assertSame(0, $stored->getIsDefault());
        self::assertSame('Professionals', $stored->setLocale('en_US')->getTitle());
        self::assertSame(1, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($group->getId())->filterByModuleId($module->getId())->count());
        self::assertSame(1, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($group->getId())->filterByCategoryId($category->getId())->count());
    }

    public function testTheFixtureCanMakeANewGroupTheDefault(): void
    {
        $group = $this->createFixtureFactory()->customerGroup(isDefault: true);

        self::assertSame($group->getId(), CustomerGroupQuery::findDefault()->getId());
        self::assertSame(1, CustomerGroupQuery::create()->filterByIsDefault(1)->count());
    }

    public function testTheFixtureMovesACustomerAndStoresAGroupPriceWithoutPromo(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup();
        $customer = $factory->assignCustomerGroup($factory->customer($factory->customerTitle()), $group);
        $currency = $factory->currency();
        $pse = $factory->productSaleElement($factory->product($factory->category(), $factory->taxRule(), $currency));

        $price = $factory->customerGroupProductPrice($group, $pse, $currency, 42.0);

        self::assertSame($group->getId(), CustomerQuery::create()->findPk($customer->getId())?->getCustomerGroupId());
        $stored = CustomerGroupProductPriceQuery::create()->findPk($price->getId());
        self::assertSame(42.0, (float) $stored?->getPrice());
        self::assertNull($stored->getPromoPrice(), 'no promo price of its own');
    }

    /**
     * The fresh install reads `setup/thelia.sql`, an updated shop the 3.3.0
     * script: both have to land on the same columns.
     */
    #[DataProvider('columns')]
    public function testTheSchemaHoldsTheColumn(string $table, string $column, string $nullable): void
    {
        $statement = Propel::getConnection('TheliaMain')->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        );
        $statement->execute([$table, $column]);

        self::assertSame($nullable, $statement->fetchColumn(), \sprintf('%s.%s', $table, $column));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function columns(): iterable
    {
        yield 'customer group of a customer' => ['customer', 'customer_group_id', 'NO'];
        yield 'customer group of a customer version' => ['customer_version', 'customer_group_id', 'NO'];
        yield 'customer group of an order' => ['order', 'customer_group_id', 'YES'];
        yield 'customer group of an order version' => ['order_version', 'customer_group_id', 'YES'];
        yield 'group discount of an order' => ['order', 'customer_group_discount_rate', 'YES'];
        yield 'group discount of an order version' => ['order_version', 'customer_group_discount_rate', 'YES'];
        yield 'price of a group' => ['customer_group_product_price', 'price', 'YES'];
        yield 'promo price of a group' => ['customer_group_product_price', 'promo_price', 'YES'];
        yield 'payment module restriction' => ['customer_group_payment_module', 'module_id', 'NO'];
        yield 'category restriction' => ['customer_group_category', 'category_id', 'NO'];
        yield 'price rule audience' => ['catalog_price_rule_customer_group', 'customer_group_id', 'NO'];
        yield 'sale audience' => ['sale_customer_group', 'customer_group_id', 'NO'];
    }
}
