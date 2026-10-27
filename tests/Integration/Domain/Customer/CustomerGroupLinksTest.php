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

namespace Thelia\Tests\Integration\Domain\Customer;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Customer\Service\CustomerGroupLinks;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupPaymentModuleQuery;
use Thelia\Model\Map\CustomerGroupTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The one writer of the lists of a group: a list given is the list the group ends up with, a list
 * left out (null) stays as it is, and the lists of the other groups are never touched. Each list
 * belongs to its group alone, so emptying it or deleting the group is never refused.
 */
final class CustomerGroupLinksTest extends IntegrationTestCase
{
    public function testTheListsGivenAreTheListsTheGroupEndsUpWith(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $kept = $factory->category();
        $dropped = $factory->category();
        $added = $factory->category();
        $factory->customerGroup(code: 'links-other', categoryIds: [(int) $dropped->getId()]);
        $group = $factory->customerGroup(code: 'links-group', categoryIds: [(int) $kept->getId(), (int) $dropped->getId()]);

        $this->links()->replace($group, [(int) $kept->getId(), (int) $added->getId(), (int) $added->getId()], [(int) $module->getId()], $this->connection());

        self::assertSame([(int) $kept->getId(), (int) $added->getId()], $this->categoryIdsOf((int) $group->getId()));
        self::assertSame([(int) $module->getId()], $this->paymentModuleIdsOf((int) $group->getId()));
    }

    public function testAListLeftOutIsLeftAlone(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $category = $factory->category();
        $factory->customerGroup(code: 'links-null-sibling', paymentModuleIds: [(int) $module->getId()]);
        $group = $factory->customerGroup(code: 'links-null', categoryIds: [(int) $category->getId()], paymentModuleIds: [(int) $module->getId()]);

        $this->links()->replace($group, null, null, $this->connection());
        self::assertSame([(int) $category->getId()], $this->categoryIdsOf((int) $group->getId()));
        self::assertSame([(int) $module->getId()], $this->paymentModuleIdsOf((int) $group->getId()));

        // Left out is not empty: only the other list is touched.
        $this->links()->replace($group, null, [], $this->connection());
        self::assertSame([(int) $category->getId()], $this->categoryIdsOf((int) $group->getId()));
        self::assertSame([], $this->paymentModuleIdsOf((int) $group->getId()));
    }

    public function testAnEmptyListClearsTheLinksAndLeavesTheOtherGroupsAlone(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $category = $factory->category();
        $sibling = $factory->customerGroup(code: 'links-sibling', categoryIds: [(int) $category->getId()], paymentModuleIds: [(int) $module->getId()]);
        $alone = $factory->customerGroup(code: 'links-alone', categoryIds: [(int) $factory->category()->getId()], paymentModuleIds: [(int) $module->getId()]);

        $this->links()->replace($alone, [], [], $this->connection());

        self::assertSame([], $this->categoryIdsOf((int) $alone->getId()), 'The only group listing a category may empty its list.');
        self::assertSame([], $this->paymentModuleIdsOf((int) $alone->getId()));
        self::assertSame([(int) $category->getId()], $this->categoryIdsOf((int) $sibling->getId()));
        self::assertSame([(int) $module->getId()], $this->paymentModuleIdsOf((int) $sibling->getId()));
    }

    public function testAListGivenNonEmptyRestrictsTheGroupAndAnEmptyOneLiftsTheRestriction(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $group = $factory->customerGroup(code: 'links-flags');
        self::assertSame([0, 0], $this->flagsOf((int) $group->getId()));

        $this->links()->replace($group, [(int) $factory->category()->getId()], null, $this->connection());
        self::assertSame([1, 0], $this->flagsOf((int) $group->getId()), 'Categories only: the payment modules stay open.');

        $this->links()->replace($group, null, [(int) $module->getId()], $this->connection());
        self::assertSame([1, 1], $this->flagsOf((int) $group->getId()));

        $this->links()->replace($group, [], null, $this->connection());
        self::assertSame([0, 1], $this->flagsOf((int) $group->getId()), 'An empty list of categories opens the whole catalog and leaves the payment flag alone.');

        $this->links()->replace($group, null, [], $this->connection());
        self::assertSame([0, 0], $this->flagsOf((int) $group->getId()));
    }

    public function testAListLeftOutLeavesTheFlagsAsTheyAre(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $group = $factory->customerGroup(code: 'links-flags-null', categoryIds: [(int) $factory->category()->getId()], paymentModuleIds: [(int) $module->getId()]);

        // The category it listed goes: the list is empty and the flag is the one thing left of the restriction.
        CustomerGroupCategoryQuery::create()->filterByCustomerGroupId((int) $group->getId())->delete();
        $this->links()->replace($group, null, null, $this->connection());
        $this->links()->replace($group, null, [(int) $module->getId()], $this->connection());

        self::assertSame([1, 1], $this->flagsOf((int) $group->getId()));
    }

    public function testDeletingAGroupDropsItsListsOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $module = ModuleQuery::create()->findOne();
        self::assertNotNull($module);
        $category = $factory->category();
        $group = $factory->customerGroup(code: 'links-delete', categoryIds: [(int) $category->getId()], paymentModuleIds: [(int) $module->getId()]);
        $sibling = $factory->customerGroup(code: 'links-delete-sibling', categoryIds: [(int) $category->getId()]);
        $groupId = (int) $group->getId();

        $group->delete();

        self::assertSame([], $this->categoryIdsOf($groupId));
        self::assertSame([], $this->paymentModuleIdsOf($groupId));
        self::assertSame([(int) $category->getId()], $this->categoryIdsOf((int) $sibling->getId()));
    }

    /**
     * @return array{int, int} restricts_categories, restricts_payment_modules, read from the database
     */
    private function flagsOf(int $groupId): array
    {
        $row = $this->connection()->query(\sprintf('SELECT restricts_categories, restricts_payment_modules FROM customer_group WHERE id = %d', $groupId))->fetch(\PDO::FETCH_NUM);

        return [(int) $row[0], (int) $row[1]];
    }

    private function links(): CustomerGroupLinks
    {
        return $this->getService(CustomerGroupLinks::class);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(CustomerGroupTableMap::DATABASE_NAME);
    }

    /**
     * @return list<int>
     */
    private function categoryIdsOf(int $groupId): array
    {
        $ids = array_map('intval', CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($groupId)->select('CategoryId')->find()->toArray());
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function paymentModuleIdsOf(int $groupId): array
    {
        $ids = array_map('intval', CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($groupId)->select('ModuleId')->find()->toArray());
        sort($ids);

        return $ids;
    }
}
