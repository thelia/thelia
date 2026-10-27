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
 * Every account that registers without a link joins the default customer group, so a link
 * to it would say nothing: the shop refuses to open one rather than hand out a second way
 * in that is the first one.
 */
final class DefaultCustomerGroupHasNoRegistrationLinkException extends \RuntimeException
{
    public static function forGroup(int $customerGroupId): self
    {
        return new self(\sprintf(
            'The customer group %d is the default one: it has no registration link, every account that registers without one joins it.',
            $customerGroupId,
        ));
    }
}
