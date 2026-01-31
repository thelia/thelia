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

namespace Thelia\Domain\Catalog\Product\Exception;

class PSENotFoundException extends \RuntimeException
{
    public static function withId(int $pseId): self
    {
        return new self(\sprintf('ProductSaleElement with ID %d not found', $pseId));
    }
}
