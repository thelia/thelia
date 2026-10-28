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

namespace Thelia\Domain\Customer\Service;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;

/**
 * The customer group a visitor belongs to: the one answer the prices, the payment
 * modules, the categories, the coupons and the order snapshot all read.
 *
 * A visitor who is not signed in, a console command and a guest account belong to the
 * default group: a guest row is reusable by e-mail, which is no proof of belonging
 * anywhere. A signed-in customer is read back from the database by id rather than from
 * the object the caller holds: the one in the session is serialized when the customer
 * signs in, so an administrator moving the account to another group, or a guest
 * turning into an account, would otherwise only take effect at the next sign-in. The
 * answer is kept for the request.
 *
 * The audiences of the catalog price rules and of the sale operations reserved for
 * groups do not count a guest as a member of the default group: they decide that on
 * their own, before asking here.
 */
class CustomerGroupResolver implements ResetInterface
{
    /** @var array<int, int> group id by customer id */
    private array $groupIdByCustomer = [];

    /** @var array<int, CustomerGroup> */
    private array $groupsById = [];

    public function __construct(private readonly CatalogActivityProbe $catalogActivityProbe)
    {
    }

    public function groupIdOf(?Customer $customer): int
    {
        // The generated getId() promises an int, though a model not stored yet holds null.
        if (null === $customer || $customer->isNew() || 0 === (int) $customer->getId()) {
            return $this->defaultGroupId();
        }

        $customerId = (int) $customer->getId();

        return $this->groupIdByCustomer[$customerId] ??= $this->readGroupIdOf($customerId);
    }

    public function groupOf(?Customer $customer): CustomerGroup
    {
        $groupId = $this->groupIdOf($customer);

        return $this->groupsById[$groupId] ??= CustomerGroupQuery::create()->findPk($groupId)
            ?? CustomerGroupQuery::findDefault();
    }

    public function defaultGroupId(): int
    {
        return $this->catalogActivityProbe->read()->defaultCustomerGroupId
            ?? (int) CustomerGroupQuery::findDefault()->getId();
    }

    /**
     * Drops what was read for one customer, once its group was changed in this very request.
     */
    public function forget(int $customerId): void
    {
        unset($this->groupIdByCustomer[$customerId]);
    }

    public function reset(): void
    {
        $this->groupIdByCustomer = [];
        $this->groupsById = [];
    }

    private function readGroupIdOf(int $customerId): int
    {
        /** @var array{CustomerGroupId: int|string|null, IsGuest: int|string|null}|null $row */
        $row = CustomerQuery::create()
            ->filterById($customerId)
            ->select(['CustomerGroupId', 'IsGuest'])
            ->findOne();

        if (null === $row || 1 === (int) $row['IsGuest'] || null === $row['CustomerGroupId']) {
            return $this->defaultGroupId();
        }

        return (int) $row['CustomerGroupId'];
    }
}
