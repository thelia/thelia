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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupNotFoundException;
use Thelia\Model\Base\CustomerGroupQuery as BaseCustomerGroupQuery;

class CustomerGroupQuery extends BaseCustomerGroupQuery
{
    /**
     * The group a new account and a visitor belong to.
     *
     * Every customer holds a group, so a shop with no default one cannot create
     * an account: the failure is raised here, with its cause, instead of
     * surfacing later as a foreign key error on `customer`.
     *
     * @throws DefaultCustomerGroupNotFoundException when no group is the default
     */
    public static function findDefault(): CustomerGroup
    {
        $group = self::create()
            ->filterByIsDefault(1)
            ->orderById()
            ->findOne();

        if (!$group instanceof CustomerGroup) {
            throw new DefaultCustomerGroupNotFoundException();
        }

        return $group;
    }

    /**
     * The ids among those given that no group answers to: a definition naming one is refused
     * before anything is written, instead of failing later on the foreign key.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    public static function findUnknownIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $known = self::create()->filterById($ids, Criteria::IN)->select('Id')->find()->toArray();

        return array_values(array_diff($ids, array_map('intval', $known)));
    }
}
