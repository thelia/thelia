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

namespace Thelia\Domain\Payment\Enum;

/**
 * Why money is given back: a short list the product fixes, with a label to translate, and
 * a free comment beside it when the merchant needs one.
 */
enum RefundReason: string
{
    case Returned = 'returned';
    case MissingOrDamaged = 'missing_or_damaged';
    case Goodwill = 'goodwill';
    case Cancellation = 'cancellation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Returned => 'Goods returned',
            self::MissingOrDamaged => 'Goods missing or damaged',
            self::Goodwill => 'Commercial gesture',
            self::Cancellation => 'Order cancelled',
            self::Other => 'Other reason',
        };
    }
}
