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
 * Turns a rule on or off.
 *
 * The event carries the state to write when the caller names one, so a link opened
 * twice asks for the state it already named instead of flipping the rule back. A
 * caller that names none gets the flip.
 */
class CatalogPriceRuleToggleActivityEvent extends CatalogPriceRuleEvent
{
    public function __construct(protected int $catalogPriceRuleId, protected ?bool $active = null)
    {
        parent::__construct();
    }

    public function getCatalogPriceRuleId(): int
    {
        return $this->catalogPriceRuleId;
    }

    /**
     * The state the rule is to be left in, or null to flip whatever is stored.
     */
    public function getWantedActiveState(): ?bool
    {
        return $this->active;
    }
}
