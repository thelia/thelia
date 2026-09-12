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

use Thelia\Model\Base\OrderReturnLine as BaseOrderReturnLine;

class OrderReturnLine extends BaseOrderReturnLine
{
    /**
     * The condition the returned goods were received in.
     */
    public const CONDITION_GOOD = 'good';
    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITIONS = [
        self::CONDITION_GOOD,
        self::CONDITION_DAMAGED,
    ];
}
