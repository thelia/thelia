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
 * Why a guest order tracking token was, or was not, allowed through.
 *
 * The two refusals are not the same answer to give. One says "you are asking too fast",
 * which is about the caller and nothing else; the other is bound to an order number, so
 * saying it out loud would tell the caller that this number is being tracked — which is
 * the enumeration the limiter exists to stop.
 */
enum GuestOrderAccessVerdict
{
    case Allowed;

    /**
     * The caller has spent its own budget, whatever it was asking about. Safe to say so:
     * it depends on the caller's own traffic, not on which order it named.
     */
    case ClientBudgetExhausted;

    /**
     * The budget attached to the order the token names is spent. Must be answered exactly
     * like an unknown token — anything else answers "this order exists".
     */
    case OrderBudgetExhausted;
}
