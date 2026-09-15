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

namespace Thelia\Domain\Order\Enum;

/**
 * The kinds of entry the core writes in an order history.
 *
 * The stored column is a plain string, not this enum: a module that follows an order
 * through its own steps writes its own type and its rows sit in the same timeline.
 * What is enumerated here is what the core itself knows how to write and to label.
 */
enum OrderHistoryEventType: string
{
    case ORDER_CREATED = 'order_created';
    case STATUS_CHANGED = 'status_changed';
    case ADDRESS_UPDATED = 'address_updated';
    case DELIVERY_REF_UPDATED = 'delivery_ref_updated';
    case TRANSACTION_REF_UPDATED = 'transaction_ref_updated';
    case INVOICE_REF_ALLOCATED = 'invoice_ref_allocated';
    case EMAIL_SENT = 'email_sent';
    case NOTE = 'note';
    case RETURN_OPENED = 'return_opened';
    case RETURN_STATUS_CHANGED = 'return_status_changed';
    case RETURN_RECEIVED = 'return_received';
}
