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

readonly class ProductWithPSECreateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $ref,
        public string $title,
        public string $locale,
        public int $defaultCategoryId,
        public float $price,
        public int $currencyId,
        public bool $visible = true,
        public bool $virtual = false,
        public ?float $weight = null,
        public ?int $quantity = null,
        public ?int $taxRuleId = null,
        public ?int $templateId = null,
        public ?float $salePrice = null,
        public bool $onSale = false,
        public bool $isNew = false,
        public ?string $eanCode = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'ref' => $this->ref,
            'title' => $this->title,
            'locale' => $this->locale,
            'default_category' => $this->defaultCategoryId,
            'price' => $this->price,
            'currency_id' => $this->currencyId,
            'visible' => $this->visible,
            'virtual' => $this->virtual,
            'weight' => $this->weight,
            'quantity' => $this->quantity,
            'tax_rule' => $this->taxRuleId,
            'template_id' => $this->templateId,
            'sale_price' => $this->salePrice,
            'on_sale' => $this->onSale,
            'is_new' => $this->isNew,
            'ean_code' => $this->eanCode,
        ];
    }

    public function toProductCreateDTO(): ProductCreateDTO
    {
        return new ProductCreateDTO(
            ref: $this->ref,
            title: $this->title,
            locale: $this->locale,
            defaultCategoryId: $this->defaultCategoryId,
            visible: $this->visible,
            virtual: $this->virtual,
            basePrice: $this->price,
            baseWeight: $this->weight,
            taxRuleId: $this->taxRuleId,
            currencyId: $this->currencyId,
            baseQuantity: $this->quantity,
            templateId: $this->templateId,
        );
    }

    public function toPSEUpdateDTO(): PSEUpdateDTO
    {
        return new PSEUpdateDTO(
            reference: $this->ref,
            price: $this->price,
            currencyId: $this->currencyId,
            weight: $this->weight ?? 0.0,
            quantity: (float) ($this->quantity ?? 0),
            salePrice: $this->salePrice ?? 0.0,
            onSale: $this->onSale,
            isNew: $this->isNew,
            isDefault: true,
            eanCode: $this->eanCode,
            taxRuleId: $this->taxRuleId ?? 0,
            fromDefaultCurrency: false,
        );
    }
}
