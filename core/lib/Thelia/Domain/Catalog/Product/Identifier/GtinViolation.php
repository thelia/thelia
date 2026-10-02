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

namespace Thelia\Domain\Catalog\Product\Identifier;

/**
 * Why a code is not a GTIN, so that the refusal can say what to fix.
 */
enum GtinViolation: string
{
    case NotDigits = 'not_digits';
    case Length = 'length';
    case CheckDigit = 'check_digit';
}
