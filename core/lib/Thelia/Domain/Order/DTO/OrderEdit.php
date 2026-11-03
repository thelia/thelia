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

namespace Thelia\Domain\Order\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OrderEdit
{
    /**
     * @param list<AddedOrderLine> $addedLines
     * @param list<int>            $removedLines   order product ids
     * @param array<int, float>    $quantities     order product id => new quantity
     * @param array<int, string>   $unitPrices     order product id => new unit price, untaxed
     * @param string|null          $discount       new order discount, untouched when null
     * @param string|null          $untaxedPostage new untaxed postage, taxed again on the delivery country by the delivery module of the order; untouched when null
     * @param string|null          $expectedState  OrderEditor::stateOf() as the editor displayed it
     */
    public function __construct(
        public array $addedLines = [],
        public array $removedLines = [],
        public array $quantities = [],
        public array $unitPrices = [],
        public ?string $discount = null,
        public ?string $untaxedPostage = null,
        public ?string $expectedState = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->addedLines
            && [] === $this->removedLines
            && [] === $this->quantities
            && [] === $this->unitPrices
            && null === $this->discount
            && null === $this->untaxedPostage;
    }
}
