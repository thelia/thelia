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
 * The kind of money movement a payment journal line describes.
 *
 * An authorization reserves an amount without taking it; a capture takes all or part
 * of a reserved amount, or the whole price at once when the module does not separate
 * the two; a void releases what an authorization still holds; a refund gives back
 * what was captured.
 */
enum PaymentTransactionType: string
{
    case AUTHORIZATION = 'authorization';
    case CAPTURE = 'capture';
    case REFUND = 'refund';
    case VOID = 'void';
}
