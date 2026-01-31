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

namespace Thelia\Domain\Catalog\Product\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class CombinationGenerationDTO implements DTOEventActionInterface
{
    /**
     * @param array<array<int>> $combinations Array of attribute combination arrays (each is list of AttributeAv IDs)
     */
    public function __construct(
        public int $currencyId,
        public array $combinations,
        public ?string $reference = null,
        public ?float $price = null,
        public ?float $weight = null,
        public ?float $quantity = null,
        public ?float $salePrice = null,
        public bool $onSale = false,
        public bool $isNew = false,
        public ?string $eanCode = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'currency_id' => $this->currencyId,
            'combinations' => $this->combinations,
            'reference' => $this->reference,
            'price' => $this->price,
            'weight' => $this->weight,
            'quantity' => $this->quantity,
            'sale_price' => $this->salePrice,
            'on_sale' => $this->onSale,
            'is_new' => $this->isNew,
            'ean_code' => $this->eanCode,
        ];
    }
}
