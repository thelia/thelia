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

namespace Thelia\Domain\Catalog\DTO;

/**
 * A reference and the quantity asked for it, as a buyer types, pastes or saves
 * it. The sale element is only set once a reference shared by several of them
 * has been settled; whoever reads it checks that it still carries that reference.
 */
final readonly class ReferenceQuantity
{
    public function __construct(
        public string $reference,
        public int $quantity,
        public ?int $productSaleElementsId = null,
    ) {
    }
}
