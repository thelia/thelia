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

namespace Thelia\Domain\Shipping\ProductRule;

/**
 * The shipping rules the products of one cart carry, read once and shared by every
 * carrier the cart is offered: the carriers they exclude, the lines left out of the
 * postage and the surcharge added to it.
 */
final readonly class CartShippingRuleSet
{
    /**
     * @param array<int, list<int>> $productsExcludingModule module id => ids of the products of the cart that exclude it
     * @param list<int>             $freeCartItemIds         cart lines left out of the postage
     * @param float                 $untaxedSurcharge        sum of the surcharges, untaxed, in the default currency
     */
    public function __construct(
        private array $productsExcludingModule,
        private array $freeCartItemIds,
        private int $shippedLineCount,
        private float $untaxedSurcharge,
    ) {
    }

    public static function none(): self
    {
        return new self([], [], 0, 0.0);
    }

    public function excludes(int $moduleId): bool
    {
        return isset($this->productsExcludingModule[$moduleId]);
    }

    /**
     * @return list<int>
     */
    public function productsExcluding(int $moduleId): array
    {
        return $this->productsExcludingModule[$moduleId] ?? [];
    }

    /**
     * @return list<int>
     */
    public function freeCartItemIds(): array
    {
        return $this->freeCartItemIds;
    }

    public function hasFreeLines(): bool
    {
        return [] !== $this->freeCartItemIds;
    }

    /**
     * Every line that travels is offered: the carrier is not paid for anything.
     */
    public function isEntirelyFree(): bool
    {
        return $this->shippedLineCount > 0 && \count($this->freeCartItemIds) === $this->shippedLineCount;
    }

    public function untaxedSurcharge(): float
    {
        return $this->untaxedSurcharge;
    }

    /**
     * True when the postage a carrier quotes has to be adjusted at all.
     */
    public function changesPostage(): bool
    {
        return $this->hasFreeLines() || $this->untaxedSurcharge > 0;
    }
}
