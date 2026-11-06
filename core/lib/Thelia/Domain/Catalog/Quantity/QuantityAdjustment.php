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

namespace Thelia\Domain\Catalog\Quantity;

/**
 * Why a quantity is not one the rules of its combination accept.
 */
enum QuantityAdjustment: string
{
    /** Below the smallest quantity the combination is sold in. */
    case BelowMinimum = 'below_minimum';

    /** Not a multiple of the step. */
    case OffStep = 'off_step';

    /** More decimals than the combination sells in. */
    case TooManyDecimals = 'too_many_decimals';
}
