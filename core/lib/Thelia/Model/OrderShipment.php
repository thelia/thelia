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

use Thelia\Model\Base\OrderShipment as BaseOrderShipment;

/**
 * A parcel, or a set of parcels leaving together, carrying part or all of an order.
 *
 * An order shipped at once has exactly one shipment; one shipped in several goes
 * has several, each with its own tracking number and delivery note. The lines a
 * shipment carries are its items, each pointing to an order line with a quantity.
 */
class OrderShipment extends BaseOrderShipment
{
    /** Being composed: its lines still count against what is left to ship, and it can be given up. */
    public const string STATUS_DRAFT = 'draft';

    /** Handed to the carrier: final. */
    public const string STATUS_SHIPPED = 'shipped';

    /** Given up before leaving: its lines are back in what is left to ship. */
    public const string STATUS_CANCELLED = 'cancelled';

    public const array STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SHIPPED,
        self::STATUS_CANCELLED,
    ];

    public function isDraft(): bool
    {
        return self::STATUS_DRAFT === $this->getStatus();
    }

    public function isShipped(): bool
    {
        return self::STATUS_SHIPPED === $this->getStatus();
    }

    public function isCancelled(): bool
    {
        return self::STATUS_CANCELLED === $this->getStatus();
    }
}
