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

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * One note a shop wrote on an order for its customer to read.
 *
 * Two fields, and deliberately no more: when it was written and what it says. The
 * order history holds who wrote it, from which account, and everything the shop
 * records about the order for itself — none of which is the customer's business,
 * and none of which travels here.
 *
 * The groups are the ones of the order this note is read with, not groups of its
 * own: the note is a detail of a single order read, it is never addressed on its
 * own, and a serialization group nobody can ask for is a group that only makes the
 * payload harder to follow.
 */
final class OrderCustomerNote
{
    #[Groups([Order::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTimeInterface $createdAt = null;

    #[Groups([Order::GROUP_FRONT_READ_SINGLE])]
    public ?string $comment = null;
}
