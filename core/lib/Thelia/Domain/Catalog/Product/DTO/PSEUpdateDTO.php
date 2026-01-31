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

readonly class PSEUpdateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $reference,
        public float $price,
        public int $currencyId,
        public float $weight = 0.0,
        public float $quantity = 0.0,
        public float $salePrice = 0.0,
        public bool $onSale = false,
        public bool $isNew = false,
        public bool $isDefault = false,
        public ?string $eanCode = null,
        public int $taxRuleId = 0,
        public bool $fromDefaultCurrency = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'price' => $this->price,
            'currency_id' => $this->currencyId,
            'weight' => $this->weight,
            'quantity' => $this->quantity,
            'sale_price' => $this->salePrice,
            'on_sale' => $this->onSale,
            'is_new' => $this->isNew,
            'is_default' => $this->isDefault,
            'ean_code' => $this->eanCode,
            'tax_rule_id' => $this->taxRuleId,
            'from_default_currency' => $this->fromDefaultCurrency,
        ];
    }
}
