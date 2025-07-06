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

namespace Thelia\TaxEngine;

use Thelia\Model\Product;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
interface TaxTypeInterface
{
    /**
     * For a price percent tax type, return the percentage (e.g. 20 for 20%) of the product price
     * to use in tax calculation.
     *
     * For other tax types, this method shoud return 0.
     */
    public function pricePercentRetriever(): float;

    /**
     * For constant amount tax type, return the absolute amount to use in tax calculation.
     *
     * For other tax types, this method shoud return 0.
     */
    public function fixAmountRetriever(Product $product): float;

    /**
     * Returns the requirements definition of this tax type. This is an array of
     * TaxTypeRequirementDefinition, which defines the name and the type of
     * the requirements. Example :.
     *
     * array(
     *    'percent' => new FloatType()
     * );
     */
    public function getRequirementsDefinition(): array;

    public function getTitle();

    public function calculate(Product $product, $untaxedPrice): float;

    public function getRequirements(): array;

    public function loadRequirements($requirementsValues): void;

    public function setRequirement($key, $value): self;

    public function getRequirement($key);
}
