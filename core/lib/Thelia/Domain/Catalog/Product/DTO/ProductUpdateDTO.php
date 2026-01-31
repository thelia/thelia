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

readonly class ProductUpdateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $ref,
        public string $title,
        public string $locale,
        public int $defaultCategoryId,
        public bool $visible = true,
        public bool $virtual = false,
        public ?float $basePrice = null,
        public ?float $baseWeight = null,
        public ?int $taxRuleId = null,
        public ?int $currencyId = null,
        public ?int $baseQuantity = null,
        public ?int $templateId = null,
        public ?string $chapo = null,
        public ?string $description = null,
        public ?string $postscriptum = null,
        public ?int $brandId = null,
        public ?int $virtualDocumentId = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'ref' => $this->ref,
            'title' => $this->title,
            'locale' => $this->locale,
            'default_category' => $this->defaultCategoryId,
            'visible' => $this->visible,
            'virtual' => $this->virtual,
            'price' => $this->basePrice,
            'weight' => $this->baseWeight,
            'tax_rule' => $this->taxRuleId,
            'currency' => $this->currencyId,
            'quantity' => $this->baseQuantity,
            'template_id' => $this->templateId,
            'chapo' => $this->chapo,
            'description' => $this->description,
            'postscriptum' => $this->postscriptum,
            'brand_id' => $this->brandId,
            'virtual_document_id' => $this->virtualDocumentId,
        ];
    }
}
