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

namespace Thelia\Domain\Promotion\Coupon\Selection;

enum PromotionDiscardReason: string
{
    /** The promotion never applies with one already kept, or applies alone. */
    case Excluded = 'excluded';

    /** A promotion kept before it asked to stop the ones that follow. */
    case Stopped = 'stopped';
}
