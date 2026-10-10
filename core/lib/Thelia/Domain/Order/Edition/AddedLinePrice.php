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
 * What the order line factory reads from a cart item, for a line added outside a cart.
 */
#[Exclude]
final readonly class AddedLinePrice
{
    public function __construct(
        private float $quantity,
        private float $price,
        private float $promoPrice,
        private bool $promo,
    ) {
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * As a cart item gives it: the DECIMAL column is a string.
     */
    public function getPrice(): string
    {
        return number_format($this->price, 6, '.', '');
    }

    public function getPromoPrice(): string
    {
        return number_format($this->promoPrice, 6, '.', '');
    }

    public function getPromo(): int
    {
        return $this->promo ? 1 : 0;
    }

    public function getIsOffered(): int
    {
        return 0;
    }

    public function getId(): ?int
    {
        return null;
    }
}
