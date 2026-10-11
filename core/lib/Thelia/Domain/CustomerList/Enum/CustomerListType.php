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

namespace Thelia\Domain\CustomerList\Enum;

/**
 * The sort of a customer list, stored in `customer_list.type`.
 *
 * Only the purchase list ships today. The column exists from the first release
 * so that favorites can join the core later with rules of their own (anonymous
 * visitors, a default list) without migrating the lists already saved. No rule
 * reads this value outside the facade of its sort.
 */
enum CustomerListType: string
{
    /** A named list of references and quantities a signed-in buyer recalls into a cart. */
    case Purchase = 'purchase';
}
