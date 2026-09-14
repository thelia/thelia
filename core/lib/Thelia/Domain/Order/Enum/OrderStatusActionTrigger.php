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
 * What fires an automatic order status action: entering a status, whatever the
 * order came from, or one precise transition between two statuses.
 */
enum OrderStatusActionTrigger: string
{
    case ENTER = 'enter';
    case TRANSITION = 'transition';
}
