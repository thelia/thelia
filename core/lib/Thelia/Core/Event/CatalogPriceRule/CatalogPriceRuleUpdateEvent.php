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

namespace Thelia\Core\Event\CatalogPriceRule;

/**
 * The same payload as a creation, addressed to an existing rule.
 */
class CatalogPriceRuleUpdateEvent extends CatalogPriceRuleCreateEvent
{
    public function __construct(protected int $catalogPriceRuleId)
    {
        parent::__construct();
    }

    public function getCatalogPriceRuleId(): int
    {
        return $this->catalogPriceRuleId;
    }
}
