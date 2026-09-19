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
 * Asks for the stored prices to be brought back in line with the rules and the
 * catalog: one rule when named, every dirty rule otherwise.
 */
class CatalogPriceRuleRecomputeEvent extends CatalogPriceRuleEvent
{
    public function __construct(protected ?int $catalogPriceRuleId = null)
    {
        parent::__construct();
    }

    public function getCatalogPriceRuleId(): ?int
    {
        return $this->catalogPriceRuleId;
    }
}
