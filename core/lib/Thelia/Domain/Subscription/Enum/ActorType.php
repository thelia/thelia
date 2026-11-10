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

namespace Thelia\Domain\Subscription\Enum;

/**
 * Who changed a subscription, stored in subscription_history.actor_type. The actor id is
 * the customer or the administrator, none for the shop itself.
 */
enum ActorType: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case System = 'system';
}
