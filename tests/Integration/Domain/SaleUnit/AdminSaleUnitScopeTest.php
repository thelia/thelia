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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Core\Event\Administrator\AdministratorSaleUnitsEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Domain\SaleUnit\Exception\SaleUnitStillAssignedException;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Model\Admin;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\SaleUnit;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * An administrator attached to no sale unit reaches them all, as before; one
 * attached to some reaches those only, and no national order; a
 * superadministrator is never restricted. The back office replaces the
 * attachment through one event, an empty list lifting the restriction.
 */
final class AdminSaleUnitScopeTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private AdminSaleUnitScope $scope;

    private SaleUnit $first;

    private SaleUnit $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scope = $this->getService(AdminSaleUnitScope::class);
        $this->first = $this->factory->saleUnit();
        $this->second = $this->factory->saleUnit();
    }

    protected function tearDown(): void
    {
        $this->scope->reset();

        parent::tearDown();
    }

    public function testAnAdministratorAttachedToNoUnitIsNotRestricted(): void
    {
        $admin = $this->profiledAdmin();

        self::assertNull($this->scope->allowedSaleUnitIds($admin));
        self::assertFalse($this->scope->isRestricted($admin));
        self::assertTrue($this->scope->allows($this->first->getId(), $admin));
        self::assertTrue($this->scope->allows(null, $admin), 'National orders included.');
    }

    public function testAnAttachedAdministratorReachesItsUnitsOnly(): void
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->first);

        self::assertSame([$this->first->getId()], $this->scope->allowedSaleUnitIds($admin));
        self::assertTrue($this->scope->allows($this->first->getId(), $admin));
        self::assertFalse($this->scope->allows($this->second->getId(), $admin));
        self::assertFalse($this->scope->allows(null, $admin), 'A national order belongs to none of its units.');
    }

    public function testASuperadministratorIsNeverRestricted(): void
    {
        $superadmin = $this->factory->admin();
        $this->factory->adminSaleUnit($superadmin, $this->first);

        self::assertNull($this->scope->allowedSaleUnitIds($superadmin));
        self::assertTrue($this->scope->allows($this->second->getId(), $superadmin));
        self::assertSame([$this->first->getId()], $this->scope->assignedSaleUnitIds($superadmin->getId()), 'The rows are still there for the form.');
    }

    public function testNoAdministratorAtAllIsNotRestricted(): void
    {
        self::assertNull($this->scope->allowedSaleUnitIds());
    }

    public function testAQueryIsNarrowedToTheUnitsOfARestrictedAdministrator(): void
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->first);
        $mine = $this->factory->order();
        $mine->setSaleUnitId($this->first->getId())->save($this->getPropelConnection());
        $theirs = $this->factory->order();
        $theirs->setSaleUnitId($this->second->getId())->save($this->getPropelConnection());
        $national = $this->factory->order();

        $ids = $this->orderIds($admin, [$mine->getId(), $theirs->getId(), $national->getId()]);

        self::assertSame([$mine->getId()], $ids);
        self::assertSame(
            [$mine->getId(), $theirs->getId(), $national->getId()],
            $this->orderIds($this->profiledAdmin(), [$mine->getId(), $theirs->getId(), $national->getId()]),
            'An administrator attached to no unit sees every order.',
        );
    }

    public function testTheEventReplacesTheAttachmentAndAnEmptyListLiftsIt(): void
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->first);

        $event = $this->dispatch(
            new AdministratorSaleUnitsEvent($admin->getId(), [$this->second->getId(), 999999999]),
            TheliaEvents::ADMINISTRATOR_UPDATE_SALE_UNITS,
        );

        self::assertSame([$this->second->getId()], $event->getSaleUnitIds(), 'An id naming no unit is ignored when another one is known.');
        self::assertSame([$this->second->getId()], $this->scope->allowedSaleUnitIds($admin), 'The scope reads the new attachment at once.');

        $this->dispatch(new AdministratorSaleUnitsEvent($admin->getId(), []), TheliaEvents::ADMINISTRATOR_UPDATE_SALE_UNITS);

        self::assertNull($this->scope->allowedSaleUnitIds($admin));
        self::assertSame([], $this->scope->assignedSaleUnitIds($admin->getId()));
    }

    /**
     * A list naming no unit at all is a mistake, not the empty list that lifts the
     * restriction: taking it as such would open every unit to the administrator.
     */
    public function testAListNamingNoKnownUnitIsRefused(): void
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->first);

        try {
            $this->dispatch(new AdministratorSaleUnitsEvent($admin->getId(), [999999999]), TheliaEvents::ADMINISTRATOR_UPDATE_SALE_UNITS);
            self::fail('A list naming no known unit must be refused.');
        } catch (\InvalidArgumentException) {
        }

        $this->scope->forget();
        self::assertSame([$this->first->getId()], $this->scope->allowedSaleUnitIds($admin));
    }

    public function testAUnitStillAttachedToAnAdministratorCannotBeDeleted(): void
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->first);

        try {
            $this->first->delete($this->getPropelConnection());
            self::fail('Deleting the unit an administrator is restricted to must be refused.');
        } catch (SaleUnitStillAssignedException $exception) {
            self::assertStringContainsString('administrator', $exception->getMessage());
        }

        $this->scope->forget();
        self::assertSame([$this->first->getId()], $this->scope->allowedSaleUnitIds($admin), 'The administrator stays restricted.');
    }

    public function testAShopWithNoActiveUnitAsksNothingAboutAdministrators(): void
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->first);
        $this->first->setActive(false)->save($this->getPropelConnection());
        $this->second->setActive(false)->save($this->getPropelConnection());
        $checker = $this->getService(SaleUnitActivityChecker::class);
        $checker->forgetAfterTheRequest();
        $checker->isMultiSaleUnitMode();
        $checker->reset();

        $statements = $this->recordSqlQueries(fn () => self::assertNull($this->scope->allowedSaleUnitIds($admin)));

        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => str_contains($statement, 'admin_sale_unit'),
        )));
    }

    private function profiledAdmin(): Admin
    {
        return $this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]);
    }

    /**
     * @param list<int> $among
     *
     * @return list<int>
     */
    private function orderIds(Admin $admin, array $among): array
    {
        $query = OrderQuery::create()->filterById($among)->orderById();
        $this->scope->restrictQuery($query, OrderTableMap::COL_SALE_UNIT_ID, $admin);

        return array_map('intval', $query->select(['Id'])->find($this->getPropelConnection())->getData());
    }
}
