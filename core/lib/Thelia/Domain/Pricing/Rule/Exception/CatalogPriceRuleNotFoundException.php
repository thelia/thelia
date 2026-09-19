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

namespace Thelia\Domain\Pricing\Rule\Exception;

class CatalogPriceRuleNotFoundException extends \RuntimeException
{
    public static function withId(int $ruleId): self
    {
        return new self(\sprintf('Catalog price rule with ID %d not found', $ruleId));
    }
}
