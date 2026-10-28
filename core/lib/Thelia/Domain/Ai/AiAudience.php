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

namespace Thelia\Domain\Ai;

/**
 * Who a usage answers to. A public usage is inactive until the merchant gives it a budget above zero.
 */
enum AiAudience: string
{
    /** Anonymous visitors of the shop: the only audience a stranger reaches. */
    case Public = 'public';
    /** Logged-in customers. */
    case Customer = 'customer';
    /** Administrators, in the back office or the admin API. */
    case Admin = 'admin';
    /** Jobs and commands, with no person behind the call. */
    case System = 'system';
}
