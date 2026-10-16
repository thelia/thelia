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

namespace Thelia\Core\Event\Cart;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\SaleUnit\Cart\CartLineChange;
use Thelia\Model\Cart;

/**
 * A cart was checked against the sale unit the visitor now buys from: the lines
 * the unit cannot sell were removed, the quantities beyond its stock reduced, and
 * every line priced at the unit's price. Dispatched once the cart is saved, with
 * the lines that changed, an empty list when none did.
 */
class CartSaleUnitSwitchedEvent extends ActionEvent
{
    /** The session flash bag key the core writes the summary of a switch under. */
    public const FLASH_KEY = 'thelia.cart.sale_unit_switch';

    /**
     * @param list<CartLineChange> $changes
     */
    public function __construct(
        private readonly Cart $cart,
        private readonly ?int $previousSaleUnitId,
        private readonly ?int $saleUnitId,
        private readonly array $changes = [],
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getPreviousSaleUnitId(): ?int
    {
        return $this->previousSaleUnitId;
    }

    public function getSaleUnitId(): ?int
    {
        return $this->saleUnitId;
    }

    /**
     * @return list<CartLineChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function hasChanges(): bool
    {
        return [] !== $this->changes;
    }

    /**
     * @return list<CartLineChange>
     */
    public function getRemovedLines(): array
    {
        return array_values(array_filter($this->changes, static fn (CartLineChange $change): bool => $change->isRemoved()));
    }

    /**
     * @return list<CartLineChange>
     */
    public function getReducedLines(): array
    {
        return array_values(array_filter($this->changes, static fn (CartLineChange $change): bool => $change->isQuantityReduced()));
    }

    /**
     * @return list<CartLineChange>
     */
    public function getRepricedLines(): array
    {
        return array_values(array_filter($this->changes, static fn (CartLineChange $change): bool => $change->isRepriced()));
    }

    /**
     * The summary a theme reads back on the next request, from the flash bag under FLASH_KEY.
     *
     * @return array{previous_sale_unit_id: ?int, sale_unit_id: ?int, removed: list<array<string, int|float|string>>, reduced: list<array<string, int|float|string>>, repriced: list<array<string, int|float|string>>}
     */
    public function toSummary(): array
    {
        $toArray = static fn (CartLineChange $change): array => $change->toArray();

        return [
            'previous_sale_unit_id' => $this->previousSaleUnitId,
            'sale_unit_id' => $this->saleUnitId,
            'removed' => array_map($toArray, $this->getRemovedLines()),
            'reduced' => array_map($toArray, $this->getReducedLines()),
            'repriced' => array_map($toArray, $this->getRepricedLines()),
        ];
    }
}
