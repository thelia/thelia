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
 * A customer was to be put in a group the shop does not have: refused before anything is
 * written, instead of failing later on the foreign key of `customer.customer_group_id`.
 */
final class UnknownCustomerGroupException extends \InvalidArgumentException
{
    public static function withId(int $customerGroupId): self
    {
        return new self(\sprintf('The customer group %d does not exist.', $customerGroupId));
    }
}
