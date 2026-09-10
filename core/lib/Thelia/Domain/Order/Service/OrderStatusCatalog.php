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

namespace Thelia\Domain\Order\Service;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * The order statuses of the shop, read once per request and kept in memory.
 *
 * The transition guard and the action runner both need to know, for any status,
 * which canonical status it stands for: a custom status declared equivalent to
 * "paid" must follow the transitions and fire the actions configured on "paid".
 * Both consult this catalog rather than the database, so that a bulk change on
 * hundreds of orders reads the statuses once.
 */
final class OrderStatusCatalog
{
    /** @var array<int, OrderStatus>|null indexed by status id, ordered by position */
    private ?array $statuses = null;

    /**
     * @return array<int, OrderStatus> indexed by status id, ordered by position
     */
    public function all(): array
    {
        if (null === $this->statuses) {
            $this->statuses = [];

            foreach (OrderStatusQuery::create()->orderByPosition()->find() as $status) {
                $this->statuses[$status->getId()] = $status;
            }
        }

        return $this->statuses;
    }

    public function get(int $statusId): ?OrderStatus
    {
        return $this->all()[$statusId] ?? null;
    }

    /**
     * The status ids that stand for the given status: the status itself and, when
     * it declares an equivalence to a canonical status held by another row, that
     * canonical status.
     *
     * @return list<int>
     */
    public function equivalentIds(int $statusId): array
    {
        $status = $this->get($statusId);

        if (null === $status) {
            return [$statusId];
        }

        $ids = [$statusId];

        foreach ($this->all() as $candidate) {
            if ($candidate->getId() !== $statusId
                && $candidate->getProtectedStatus()
                && $candidate->getCode() === $status->getEffectiveCode()
            ) {
                $ids[] = $candidate->getId();
            }
        }

        return $ids;
    }

    #[AsEventListener(event: TheliaEvents::ORDER_STATUS_CREATE, priority: -128)]
    #[AsEventListener(event: TheliaEvents::ORDER_STATUS_UPDATE, priority: -128)]
    #[AsEventListener(event: TheliaEvents::ORDER_STATUS_DELETE, priority: -128)]
    #[AsEventListener(event: TheliaEvents::ORDER_STATUS_UPDATE_POSITION, priority: -128)]
    public function reset(): void
    {
        $this->statuses = null;
    }
}
