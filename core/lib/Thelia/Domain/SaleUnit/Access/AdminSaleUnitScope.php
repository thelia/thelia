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

namespace Thelia\Domain\SaleUnit\Access;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Model\Admin;
use Thelia\Model\AdminSaleUnitQuery;

/**
 * The sale units an administrator works on.
 *
 * An administrator attached to no sale unit reaches every one of them, and the
 * national orders: that is every administrator of a shop that never restricts
 * anyone, so nothing changes for it. An administrator attached to some units
 * reaches those only. A superadministrator is never restricted, whatever rows
 * it may carry.
 *
 * A national order (placed on no sale unit) belongs to none of the units an
 * administrator is restricted to: a restricted administrator does not see it.
 *
 * A shop with no active sale unit restricts nobody, and asks nothing.
 */
class AdminSaleUnitScope implements ResetInterface
{
    /** @var array<int, list<int>> assigned sale unit ids, keyed by admin id */
    private array $assigned = [];

    public function __construct(
        private readonly ?Security $security = null,
        private readonly ?SecurityContext $securityContext = null,
        private readonly ?SaleUnitActivityChecker $activityChecker = null,
    ) {
    }

    /**
     * The administrator of the current request: the API token's, else the back office session's.
     */
    public function currentAdmin(): ?Admin
    {
        $user = $this->security?->getUser();

        if ($user instanceof Admin) {
            return $user;
        }

        $admin = $this->securityContext?->getAdminUser();

        return $admin instanceof Admin ? $admin : null;
    }

    /**
     * @return list<int>|null the sale units the administrator is restricted to, or
     *                        null when it is not restricted (every unit, national
     *                        orders included). Defaults to the current administrator;
     *                        no administrator at all (console, front) is not restricted.
     */
    public function allowedSaleUnitIds(?Admin $admin = null): ?array
    {
        $admin ??= $this->currentAdmin();

        if (null === $admin || self::isSuperAdministrator($admin)) {
            return null;
        }

        // A shop with no active sale unit sells from none: nothing to narrow, and
        // nothing to ask the database about its administrators.
        if (null !== $this->activityChecker && !$this->activityChecker->isMultiSaleUnitMode()) {
            return null;
        }

        $saleUnitIds = $this->assignedSaleUnitIds((int) $admin->getId());

        return [] === $saleUnitIds ? null : $saleUnitIds;
    }

    public function isRestricted(?Admin $admin = null): bool
    {
        return null !== $this->allowedSaleUnitIds($admin);
    }

    /**
     * Whether the administrator may reach what belongs to this sale unit; null
     * stands for national (an order placed on no unit).
     */
    public function allows(?int $saleUnitId, ?Admin $admin = null): bool
    {
        $allowed = $this->allowedSaleUnitIds($admin);

        if (null === $allowed) {
            return true;
        }

        return null !== $saleUnitId && \in_array($saleUnitId, $allowed, true);
    }

    /**
     * Narrows a query to the rows of the sale units the administrator reaches,
     * through the column holding the sale unit id. Leaves it alone for an
     * administrator who is not restricted.
     *
     * @param string $saleUnitColumn a fully qualified column, e.g. OrderTableMap::COL_SALE_UNIT_ID
     */
    public function restrictQuery(ModelCriteria $query, string $saleUnitColumn, ?Admin $admin = null): void
    {
        $allowed = $this->allowedSaleUnitIds($admin);

        if (null === $allowed) {
            return;
        }

        $query->addAnd($saleUnitColumn, $allowed, Criteria::IN);
    }

    /**
     * The sale units the administrator is attached to, as stored, whatever its
     * profile: what a form editing the attachment shows. Empty means not restricted.
     *
     * @return list<int>
     */
    public function assignedSaleUnitIds(int $adminId): array
    {
        if (isset($this->assigned[$adminId])) {
            return $this->assigned[$adminId];
        }

        $saleUnitIds = [];

        foreach (AdminSaleUnitQuery::create()
            ->filterByAdminId($adminId)
            ->orderBySaleUnitId()
            ->select(['SaleUnitId'])
            ->find() as $saleUnitId) {
            $saleUnitIds[] = (int) $saleUnitId;
        }

        return $this->assigned[$adminId] = $saleUnitIds;
    }

    /**
     * The attachment of an administrator changed: what was read of it is stale.
     */
    public function forget(?int $adminId = null): void
    {
        if (null === $adminId) {
            $this->assigned = [];

            return;
        }

        unset($this->assigned[$adminId]);
    }

    public function reset(): void
    {
        $this->assigned = [];
    }

    private static function isSuperAdministrator(Admin $admin): bool
    {
        $profileId = $admin->getProfileId();

        return null === $profileId || 0 === (int) $profileId;
    }
}
