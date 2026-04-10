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

namespace Thelia\Domain\Cart\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\CartQuery;
use Thelia\Model\OrderQuery;

class CartPurger
{
    /**
     * @throws PropelException
     */
    public function purgeCartsWithoutOrder(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        $cartIdsWithOrder = OrderQuery::create()
            ->select('CartId')
            ->find()
            ->toArray();

        $query = CartQuery::create()->filterByCreatedAt($threshold, Criteria::LESS_THAN);

        if (!empty($cartIdsWithOrder)) {
            $query->filterById($cartIdsWithOrder, Criteria::NOT_IN);
        }

        $query->filterByCustomerId(null, Criteria::ISNOTNULL);

        return $query->delete();
    }

    /**
     * @throws PropelException
     */
    public function purgeAnonymousCarts(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        return CartQuery::create()
            ->filterByCustomerId(null, Criteria::ISNULL)
            ->filterByCreatedAt($threshold, Criteria::LESS_THAN)
            ->delete();
    }

    private function getThresholdDate(int $days): \DateTime
    {
        return (new \DateTime())->modify(\sprintf('-%d days', $days));
    }
}
