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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the rules of a combination make of a requested quantity: the quantity kept,
 * brought down to the valid multiple below it, or null when even that is below the
 * smallest quantity the combination is sold in and the line cannot be kept.
 */
#[Exclude]
final readonly class QuantityDecision
{
    public function __construct(
        public float $requested,
        public ?float $quantity,
        public ?QuantityAdjustment $adjustment,
        public QuantityRule $rule,
    ) {
    }

    public function isAdjusted(): bool
    {
        return null !== $this->adjustment;
    }

    public function removesTheLine(): bool
    {
        return null === $this->quantity;
    }

    /**
     * What happened to a line read back against the rules, for the buyer; empty when
     * the quantity was kept as it was.
     */
    public function message(): string
    {
        if (null === $this->quantity) {
            return QuantityTranslation::trans(
                'This line was removed: the smallest quantity you can order is %quantity.',
                ['%quantity' => $this->formatted($this->rule->smallestQuantity())],
            );
        }

        return match ($this->adjustment) {
            null => '',
            QuantityAdjustment::OffStep => QuantityTranslation::trans(
                'The quantity was brought down to %quantity: sold by %step.',
                ['%quantity' => $this->formatted($this->quantity), '%step' => $this->formatted($this->rule->step)],
            ),
            QuantityAdjustment::TooManyDecimals, QuantityAdjustment::BelowMinimum => QuantityTranslation::trans(
                'The quantity was brought down to %quantity.',
                ['%quantity' => $this->formatted($this->quantity)],
            ),
        };
    }

    /**
     * Why the requested quantity is refused where the buyer can change it: the smallest
     * quantity, the valid ones, or the decimals allowed. Empty when nothing is refused.
     */
    public function refusalMessage(): string
    {
        return match ($this->adjustment) {
            null => '',
            QuantityAdjustment::BelowMinimum => QuantityTranslation::trans(
                'The smallest quantity you can order is %quantity.',
                ['%quantity' => $this->formatted($this->rule->smallestQuantity())],
            ),
            QuantityAdjustment::OffStep => $this->offStepRefusal(),
            QuantityAdjustment::TooManyDecimals => $this->rule->isSoldByMeasure()
                ? QuantityTranslation::trans(
                    'The quantity cannot carry more than %decimals decimal places.',
                    ['%decimals' => (string) $this->rule->decimals],
                )
                : QuantityTranslation::trans('The quantity must be a whole number.'),
        };
    }

    private function offStepRefusal(): string
    {
        $parameters = ['%step' => $this->formatted($this->rule->step)];
        $step = $this->rule->scaledStep();
        $quantity = $this->rule->smallestScaledQuantity();

        foreach (['%first', '%second', '%third'] as $index => $placeholder) {
            $parameters[$placeholder] = $this->formatted(QuantityScale::unscaled($quantity + $index * $step, $this->rule->decimals));
        }

        // A decimal comma would be lost among the commas of the list.
        return $this->rule->isSoldByMeasure()
            ? QuantityTranslation::trans('Sold by %step: %first; %second; %third…', $parameters)
            : QuantityTranslation::trans('Sold by %step: %first, %second, %third…', $parameters);
    }

    private function formatted(float $quantity): string
    {
        return (new QuantityFormatter())->format($quantity, $this->rule->decimals);
    }
}
