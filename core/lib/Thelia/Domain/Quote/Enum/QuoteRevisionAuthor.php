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

namespace Thelia\Domain\Quote\Enum;

/**
 * Who wrote a revision of a quote.
 */
enum QuoteRevisionAuthor: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
