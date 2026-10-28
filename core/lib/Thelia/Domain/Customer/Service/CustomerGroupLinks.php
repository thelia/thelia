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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\CustomerGroupPaymentModuleQuery;

/**
 * The one writer of the lists of a customer group: the categories it may browse and the
 * payment modules it may pay with (API, back office).
 *
 * Each list belongs to its group alone: rewriting the list of a group, or deleting the group,
 * never opens nor closes anything to another group, so nothing is refused and no other writer
 * is waited for.
 *
 * Whether a group is restricted is a flag of the group (restricts_categories,
 * restricts_payment_modules), set here and nowhere else: a list written non-empty restricts
 * the group to it, a list written empty lifts the restriction. The deletion of a category or
 * of a module cascades on the list and leaves the flag alone, so that a group restricted to a
 * single category that disappears sees nothing, instead of everything.
 */
final readonly class CustomerGroupLinks
{
    /**
     * Brings the lists of the group to the ids given, in one transaction (the one the
     * connection is in, or one of its own): drops the links that are no longer wanted and
     * creates the missing ones. The group must have been saved.
     *
     * @param list<int>|null $categoryIds      null leaves the categories and the flag as they are, an empty list opens the whole catalog
     * @param list<int>|null $paymentModuleIds null leaves the payment modules and the flag as they are, an empty list opens every module
     */
    public function replace(CustomerGroup $group, ?array $categoryIds, ?array $paymentModuleIds, ConnectionInterface $connection): void
    {
        if (null === $categoryIds && null === $paymentModuleIds) {
            return;
        }

        $connection->beginTransaction();

        try {
            if (null !== $categoryIds) {
                $this->syncLinks(
                    CustomerGroupCategoryQuery::create()->filterByCustomerGroupId((int) $group->getId()),
                    'CategoryId',
                    $this->uniqueIds($categoryIds),
                    static fn (int $categoryId): CustomerGroupCategory => (new CustomerGroupCategory())
                        ->setCustomerGroupId((int) $group->getId())
                        ->setCategoryId($categoryId),
                    $connection,
                );

                $group->setRestrictsCategories([] !== $categoryIds ? 1 : 0)->save($connection);
            }

            if (null !== $paymentModuleIds) {
                $this->syncLinks(
                    CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId((int) $group->getId()),
                    'ModuleId',
                    $this->uniqueIds($paymentModuleIds),
                    static fn (int $moduleId): CustomerGroupPaymentModule => (new CustomerGroupPaymentModule())
                        ->setCustomerGroupId((int) $group->getId())
                        ->setModuleId($moduleId),
                    $connection,
                );

                $group->setRestrictsPaymentModules([] !== $paymentModuleIds ? 1 : 0)->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * Brings the links of a group to the ids given: the others are deleted, the missing ones created.
     *
     * @param ModelCriteria                                                     $groupLinks   the link query, already narrowed to the group
     * @param string                                                            $linkedIdName the PHP name of the column holding the linked id
     * @param list<int>                                                         $ids
     * @param \Closure(int): (CustomerGroupCategory|CustomerGroupPaymentModule) $newLink
     */
    private function syncLinks(ModelCriteria $groupLinks, string $linkedIdName, array $ids, \Closure $newLink, ConnectionInterface $connection): void
    {
        $stale = clone $groupLinks;

        if ([] !== $ids) {
            $stale->filterBy($linkedIdName, $ids, Criteria::NOT_IN);
        }

        $stale->delete($connection);

        $kept = array_map('intval', (clone $groupLinks)->select($linkedIdName)->find($connection)->toArray());

        foreach (array_diff($ids, $kept) as $id) {
            $newLink($id)->save($connection);
        }
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function uniqueIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        sort($ids);

        return $ids;
    }
}
