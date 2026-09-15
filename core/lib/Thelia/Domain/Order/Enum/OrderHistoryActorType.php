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
 * Which kind of author is behind an order history entry.
 *
 * SYSTEM is not a fallback for "we did not look": it is the answer for a gesture that
 * genuinely has no author, which is the nominal case on the command line and in a worker.
 */
enum OrderHistoryActorType: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case MODULE = 'module';
    case SYSTEM = 'system';
}
