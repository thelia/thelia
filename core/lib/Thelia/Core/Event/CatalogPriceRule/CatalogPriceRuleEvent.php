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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CatalogPriceRule;

/**
 * The event every change to a catalog price rule travels on. The action that
 * handles it sets the rule it created or touched, so the caller can read its id.
 */
class CatalogPriceRuleEvent extends ActionEvent
{
    public function __construct(protected ?CatalogPriceRule $catalogPriceRule = null)
    {
    }

    public function setCatalogPriceRule(CatalogPriceRule $catalogPriceRule): static
    {
        $this->catalogPriceRule = $catalogPriceRule;

        return $this;
    }

    public function getCatalogPriceRule(): ?CatalogPriceRule
    {
        return $this->catalogPriceRule;
    }

    public function hasCatalogPriceRule(): bool
    {
        return null !== $this->catalogPriceRule;
    }
}
