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

namespace Thelia\Domain\Company\Exception;

/**
 * The buyer is not one the caller may see: there is no such link, or it belongs to another company.
 */
final class CompanyBuyerNotFoundException extends CompanyException
{
    public static function withId(int $buyerId): self
    {
        return new self(\sprintf('The buyer %d was not found.', $buyerId));
    }
}
