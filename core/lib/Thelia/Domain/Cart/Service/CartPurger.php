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
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Model\CartQuery;
use Thelia\Model\OrderQuery;

class CartPurger
{
    private const LOT = 500;

    /**
     * The lines of the purged carts that held stock are told as released (STOCK_RELEASED), by cart.
     */
    public function __construct(private readonly ?StockReservationService $stockReservation = null)
    {
    }

    /**
     * @throws PropelException
     */
    public function purgeCartsWithoutOrder(int $days): int
    {
        return $this->deleteAnnouncingTheReleases($this->cartsWithoutOrder($days));
    }

    /**
     * @throws PropelException
     */
    public function countCartsWithoutOrder(int $days): int
    {
        return $this->cartsWithoutOrder($days)->count();
    }

    /**
     * @throws PropelException
     */
    public function purgeAnonymousCarts(int $days): int
    {
        return $this->deleteAnnouncingTheReleases($this->anonymousCarts($days));
    }

    /**
     * @throws PropelException
     */
    public function countAnonymousCarts(int $days): int
    {
        return $this->anonymousCarts($days)->count();
    }

    /**
     * The carts that have a line told as held are deleted first, by lots, each lot read before the
     * delete takes its lines away and told once it is done (a line that reads back held was kept:
     * the cart was not deleted after all); the carts that held nothing follow in one delete.
     *
     * @throws PropelException
     */
    private function deleteAnnouncingTheReleases(CartQuery $query): int
    {
        if (null === $this->stockReservation || !$this->stockReservation->mayHaveHeldLines()) {
            return $query->delete();
        }

        $deleted = 0;

        while (true) {
            // The carts of the purge with at least one line told as held: few, since only a hold
            // that nothing released (a tunnel left open) leaves a date behind.
            $cartIds = array_map('intval', (clone $query)
                ->useCartItemQuery()
                    ->filterByReservedUntil(null, Criteria::ISNOTNULL)
                ->endUse()
                ->select('Id')
                ->distinct()
                ->limit(self::LOT)
                ->find()
                ->toArray());

            if ([] === $cartIds) {
                break;
            }

            $held = $this->stockReservation->heldLinesOfCarts($cartIds);
            $lot = CartQuery::create()->filterById($cartIds, Criteria::IN)->delete();

            foreach ($held as $cartId => $lines) {
                $this->stockReservation->announceReleased($cartId, $lines);
            }

            $deleted += $lot;

            if (0 === $lot) {
                // Nothing went: the same carts would come back for ever.
                break;
            }
        }

        return $deleted + $query->delete();
    }

    private function cartsWithoutOrder(int $days): CartQuery
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

        return $query;
    }

    private function anonymousCarts(int $days): CartQuery
    {
        return CartQuery::create()
            ->filterByCustomerId(null, Criteria::ISNULL)
            ->filterByCreatedAt($this->getThresholdDate($days), Criteria::LESS_THAN);
    }

    private function getThresholdDate(int $days): \DateTime
    {
        return (new \DateTime())->modify(\sprintf('-%d days', $days));
    }
}
