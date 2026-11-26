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

namespace Thelia\Domain\Order\Twig;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The notes of an order that the shop addressed to its customer, for templates that have no loop to read
 * them with, the PDF documents first.
 *
 * Same rule as the front API (`OrderCustomerNotesAddon`): the entry is a note and it is marked visible to
 * the customer. An internal note, or an entry of another kind that a module marked visible, never comes out.
 */
final class OrderCustomerNoteExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('order_customer_notes', $this->orderCustomerNotes(...)),
        ];
    }

    /**
     * The notes visible to the customer of an order, oldest first, as `['comment' => …, 'created_at' => …]`.
     * The text is raw: the template escapes it (`{{ note.comment|nl2br }}`).
     *
     * An empty list for a missing order id, so a template can call it before the order is known.
     *
     * @return list<array{comment: string, created_at: ?\DateTimeInterface}>
     */
    public function orderCustomerNotes(int|string|null $orderId): array
    {
        if (null === $orderId || '' === $orderId || 0 === (int) $orderId) {
            return [];
        }

        $notes = OrderHistoryQuery::create()
            ->filterByOrderId((int) $orderId)
            ->filterByEventType(OrderHistoryEventType::NOTE->value)
            ->filterByVisibleToCustomer(1)
            ->orderById(Criteria::ASC)
            ->find();

        return array_values(array_map(
            static function (OrderHistory $note): array {
                $createdAt = $note->getCreatedAt();

                return [
                    'comment' => (string) $note->getComment(),
                    'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt : null,
                ];
            },
            iterator_to_array($notes),
        ));
    }
}
