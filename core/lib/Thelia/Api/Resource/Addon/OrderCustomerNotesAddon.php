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

namespace Thelia\Api\Resource\Addon;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Resource\Order;
use Thelia\Api\Resource\OrderCustomerNote;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;

/**
 * Adds to the single front read of an order the notes the shop wrote for its
 * customer, and nothing else of its history.
 *
 * The order history is an internal journal: it holds what the shop did, which
 * module did it, which administrator signed in to do it, the references it
 * allocated and the mails it sent. A customer reads one thing out of it — the
 * notes a human deliberately addressed to them, by ticking the box that says so.
 *
 * Two conditions, both required, decide that: the entry is a note, and it is
 * marked visible to the customer. An entry that is one without the other never
 * leaves. Nothing widens this: the addon carries the front single-read group only,
 * so an administration read does not build it, and neither does a collection.
 *
 * Who may read the order at all is settled before this runs, by the operation on
 * the Order resource: `/front/account/orders/{id}` is under `/front/account` (so
 * behind ROLE_CUSTOMER) and carries `object.customer.getId() == user.getId()`.
 * The guest tracking operation shares the same groups and is guarded by the
 * signed, expiring token its provider verifies — a guest reading the notes of
 * their own order is the same reader by another door.
 */
class OrderCustomerNotesAddon implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    /**
     * @var array<int, OrderCustomerNote>
     */
    #[Groups([Order::GROUP_FRONT_READ_SINGLE])]
    public array $customerNotes = [];

    public static function getResourceParent(): string
    {
        return Order::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return null;
    }

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
        // An order has as many history entries as it had gestures, so joining them onto
        // the order query would multiply the order row by its own journal. The rows are
        // read on their own in buildFromModel() instead.
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $orderId = $activeRecord->getId();

        if (null === $orderId) {
            return $this;
        }

        $notes = OrderHistoryQuery::create()
            ->filterByOrderId($orderId)
            ->filterByEventType(OrderHistoryEventType::NOTE->value)
            ->filterByVisibleToCustomer(1)
            ->orderById(Criteria::ASC)
            ->find();

        $this->customerNotes = array_map(
            static fn (OrderHistory $note): OrderCustomerNote => self::mapNote($note),
            iterator_to_array($notes),
        );

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        // Read-only addon: a note is written through the order history, never through
        // the order payload a customer sends back.
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        // Read-only addon: the foreign key on order_history.order_id cascades.
    }

    private static function mapNote(OrderHistory $note): OrderCustomerNote
    {
        $customerNote = new OrderCustomerNote();

        $createdAt = $note->getCreatedAt();
        $customerNote->createdAt = $createdAt instanceof \DateTimeInterface ? $createdAt : null;
        $customerNote->comment = $note->getComment();

        return $customerNote;
    }
}
