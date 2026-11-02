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

namespace Thelia\Domain\Catalog\Quantity;

use Thelia\Model\ProductSaleElements;

/**
 * The quantities a combination is sold in: a minimum, a step every quantity is a
 * multiple of, the decimals a quantity may carry (0 sells by the piece, more sells by
 * measure), and what one unit sold contains, for the reference price. The defaults are
 * what every combination did before the rules existed: from 1, by 1, whole numbers.
 *
 * Read from a combination already loaded, without a query.
 */
final readonly class QuantityRule
{
    public function __construct(
        public float $minQuantity = 1.0,
        public float $step = 1.0,
        public int $decimals = 0,
        public ?float $unitQuantity = null,
        public ?float $referenceUnitQuantity = null,
        public ?UnitCode $unitCode = null,
    ) {
        if ($decimals < 0 || $decimals > QuantityScale::MAXIMUM_DECIMALS) {
            throw InvalidQuantityRuleException::decimalsOutOfRange($decimals);
        }

        if (QuantityScale::micro($minQuantity) <= 0) {
            throw InvalidQuantityRuleException::notPositive('minimum quantity');
        }

        if (QuantityScale::micro($step) <= 0) {
            throw InvalidQuantityRuleException::notPositive('quantity step');
        }

        if (!QuantityScale::fits($step, $decimals)) {
            throw InvalidQuantityRuleException::moreDecimalsThanAllowed('quantity step', $decimals);
        }

        if (!QuantityScale::fits($minQuantity, $decimals)) {
            throw InvalidQuantityRuleException::moreDecimalsThanAllowed('minimum quantity', $decimals);
        }

        if (null !== $unitQuantity && QuantityScale::micro($unitQuantity) <= 0) {
            throw InvalidQuantityRuleException::notPositive('unit quantity');
        }

        if (null !== $referenceUnitQuantity && QuantityScale::micro($referenceUnitQuantity) <= 0) {
            throw InvalidQuantityRuleException::notPositive('reference unit quantity');
        }
    }

    /**
     * The DECIMAL columns come out of Propel as strings and are turned into numbers here,
     * once. A unit code this version does not know reads as no unit: the unit only
     * serves the reference price and the display, never the quantity.
     */
    public static function fromProductSaleElements(ProductSaleElements $productSaleElements): self
    {
        $unitQuantity = $productSaleElements->getUnitQuantity();
        $referenceUnitQuantity = $productSaleElements->getReferenceUnitQuantity();
        $unitCode = $productSaleElements->getUnitCode();

        return new self(
            minQuantity: (float) ($productSaleElements->getMinQuantity() ?? 1),
            step: (float) ($productSaleElements->getQuantityStep() ?? 1),
            decimals: $productSaleElements->getQuantityDecimals() ?? 0,
            unitQuantity: null === $unitQuantity ? null : (float) $unitQuantity,
            referenceUnitQuantity: null === $referenceUnitQuantity ? null : (float) $referenceUnitQuantity,
            unitCode: null === $unitCode ? null : UnitCode::tryFrom($unitCode),
        );
    }

    /**
     * Writes the rule on the combination, without saving it.
     */
    public function applyTo(ProductSaleElements $productSaleElements): void
    {
        $productSaleElements
            ->setMinQuantity(self::decimalColumn($this->minQuantity))
            ->setQuantityStep(self::decimalColumn($this->step))
            ->setQuantityDecimals($this->decimals)
            ->setUnitQuantity(null === $this->unitQuantity ? null : self::decimalColumn($this->unitQuantity))
            ->setReferenceUnitQuantity(null === $this->referenceUnitQuantity ? null : self::decimalColumn($this->referenceUnitQuantity))
            ->setUnitCode($this->unitCode?->value);
    }

    public function isSoldByMeasure(): bool
    {
        return QuantityScale::isSoldByMeasure($this->decimals);
    }

    public function hasReferencePrice(): bool
    {
        return null !== $this->unitCode
            && null !== $this->unitQuantity
            && null !== $this->referenceUnitQuantity;
    }

    /**
     * The first multiple of the step that is not below the minimum: 6 for a minimum of 2
     * sold by 6.
     */
    public function smallestQuantity(): float
    {
        return QuantityScale::unscaled($this->smallestScaledQuantity(), $this->decimals);
    }

    /**
     * @internal the step in units of 10^-decimals
     */
    public function scaledStep(): int
    {
        return QuantityScale::scaledDown($this->step, $this->decimals);
    }

    /**
     * @internal the smallest quantity in units of 10^-decimals
     */
    public function smallestScaledQuantity(): int
    {
        $step = $this->scaledStep();
        $minimum = QuantityScale::scaledDown($this->minQuantity, $this->decimals);

        return intdiv($minimum + $step - 1, $step) * $step;
    }

    private static function decimalColumn(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
