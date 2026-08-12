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

namespace Thelia\Domain\Taxation\TaxEngine;

use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\State;
use Thelia\Model\TaxRule;

/**
 * Contract of the tax calculator used by the whole catalog, cart and order chain.
 *
 * An implementation is stateful: a load*() method selects the tax rules to apply,
 * then the get*Price() methods use that selection. Never share an instance between
 * two unrelated computations, always ask a TaxCalculatorFactoryInterface for a new one.
 */
interface TaxCalculatorInterface
{
    public function load(Product $product, Country $country, ?State $state = null): static;

    public function loadTaxRule(TaxRule $taxRule, Country $country, Product $product, ?State $state = null): static;

    public function loadTaxRuleWithoutCountry(TaxRule $taxRule, Product $product): static;

    public function loadTaxRuleWithoutProduct(TaxRule $taxRule, Country $country, ?State $state = null): static;

    public function getTaxAmountFromUntaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null): int|float;

    public function getTaxAmountFromTaxedPrice($taxedPrice): int|float;

    public function getTaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null, ?string $askedLocale = null): int|float;

    public function getUntaxedPrice($taxedPrice): int|float;
}
