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

namespace Thelia\Domain\Order\Edition;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One line of the order as the edit wants it: a line the order has (kept, with its
 * quantity and possibly a new unit price), or a sale element to add. A line of the order
 * the edit does not name is removed.
 */
#[Exclude]
final readonly class OrderEditLine
{
    private function __construct(
        public ?int $orderProductId,
        public ?int $productSaleElementsId,
        public float $quantity,
        public ?string $unitPrice,
    ) {
    }

    /**
     * @param string|null $unitPrice the new unit price excluding tax, null to keep the frozen one
     */
    public static function keep(int $orderProductId, float $quantity, ?string $unitPrice = null): self
    {
        return new self($orderProductId, null, $quantity, $unitPrice);
    }

    /**
     * @param string|null $unitPrice the unit price excluding tax, null for the catalogue price
     */
    public static function add(int $productSaleElementsId, float $quantity, ?string $unitPrice = null): self
    {
        return new self(null, $productSaleElementsId, $quantity, $unitPrice);
    }

    public function isAdded(): bool
    {
        return null === $this->orderProductId;
    }
}
