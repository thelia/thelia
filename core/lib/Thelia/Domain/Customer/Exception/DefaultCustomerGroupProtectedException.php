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
 * The default customer group is what a new account and a visitor belong to: the
 * shop must always hold exactly one. Make another group the default first, and
 * the previous one is released by that very change.
 */
final class DefaultCustomerGroupProtectedException extends \RuntimeException
{
    public static function onDeletion(int $customerGroupId): self
    {
        return new self(\sprintf(
            'The customer group %d is the default one and cannot be deleted. Make another group the default first.',
            $customerGroupId,
        ));
    }

    public static function onUnflagging(int $customerGroupId): self
    {
        return new self(\sprintf(
            'The customer group %d is the only default one and cannot stop being it. Make another group the default instead.',
            $customerGroupId,
        ));
    }
}
