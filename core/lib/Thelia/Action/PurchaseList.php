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

namespace Thelia\Action;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\CustomerList\PurchaseListEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\Enum\CustomerListType;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListItem;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\Map\CustomerListTableMap;

/**
 * Writes the purchase lists. Who may write is decided before the dispatch, by
 * PurchaseListFacade: this action only persists what the event carries, a list
 * and its lines in one transaction.
 */
class PurchaseList extends BaseAction implements EventSubscriberInterface
{
    public function create(PurchaseListEvent $event): void
    {
        $this->transactional(function (ConnectionInterface $connection) use ($event): void {
            $list = (new CustomerList())
                ->setCustomerId((int) $event->getCustomer()->getId())
                ->setType(CustomerListType::Purchase->value)
                ->setTitle((string) $event->getTitle())
                ->setShared(false);
            $list->save($connection);

            $this->insertLines($list, $event->getLines() ?? new ReferenceQuantityLines(), $connection);

            $event->setCustomerList($list);
        });
    }

    public function update(PurchaseListEvent $event): void
    {
        $list = $this->listOf($event);

        $this->transactional(function (ConnectionInterface $connection) use ($event, $list): void {
            if (null !== $event->getTitle()) {
                $list->setTitle($event->getTitle());
            }

            $lines = $event->getLines();

            if (null !== $lines) {
                CustomerListItemQuery::create()
                    ->filterByCustomerListId($list->getId())
                    ->delete($connection);
                $this->insertLines($list, $lines, $connection);
                // The list row may not change when only its lines do: its
                // update date is what orders the customer's lists.
                $list->setUpdatedAt(new \DateTime());
            }

            $list->save($connection);
        });
    }

    public function delete(PurchaseListEvent $event): void
    {
        $this->listOf($event)->delete();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PURCHASE_LIST_CREATE => ['create', 128],
            TheliaEvents::PURCHASE_LIST_UPDATE => ['update', 128],
            TheliaEvents::PURCHASE_LIST_DELETE => ['delete', 128],
        ];
    }

    private function insertLines(CustomerList $list, ReferenceQuantityLines $lines, ConnectionInterface $connection): void
    {
        foreach ($lines as $position => $line) {
            (new CustomerListItem())
                ->setCustomerListId((int) $list->getId())
                ->setRef($line->reference)
                ->setProductSaleElementsId($line->productSaleElementsId)
                ->setQuantity($line->quantity)
                ->setPosition($position + 1)
                ->save($connection);
        }
    }

    private function listOf(PurchaseListEvent $event): CustomerList
    {
        return $event->getCustomerList()
            ?? throw new \LogicException('A purchase list update or deletion needs the list it applies to.');
    }

    /**
     * @param callable(ConnectionInterface): void $write
     */
    private function transactional(callable $write): void
    {
        $connection = Propel::getWriteConnection(CustomerListTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $write($connection);
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }
}
