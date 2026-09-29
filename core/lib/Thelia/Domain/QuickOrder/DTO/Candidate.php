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

namespace Thelia\Domain\QuickOrder\DTO;

/**
 * One of the sale elements an ambiguous reference may mean, with what tells it
 * apart from the others.
 */
final readonly class Candidate
{
    /**
     * @param list<array{attribute: string, value: string}> $attributes
     */
    public function __construct(
        public int $productSaleElementsId,
        public int $productId,
        public string $reference,
        public bool $isDefault,
        public bool $preselected,
        public array $attributes,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productSaleElementsId' => $this->productSaleElementsId,
            'productId' => $this->productId,
            'ref' => $this->reference,
            'isDefault' => $this->isDefault,
            'preselected' => $this->preselected,
            'attributes' => $this->attributes,
        ];
    }
}
