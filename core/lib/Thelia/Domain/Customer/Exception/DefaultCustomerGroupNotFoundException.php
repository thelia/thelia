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

namespace Thelia\Domain\Customer\Exception;

/**
 * No customer group is flagged as the default one.
 *
 * A new account joins the default group and a visitor is priced as one of its
 * members, so a shop without it can neither register a customer nor price a
 * cart. The install and the update seed it: its absence means it was deleted
 * or its flag cleared by hand.
 */
final class DefaultCustomerGroupNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No customer group is the default one: flag a group as the default before creating a customer.');
    }
}
