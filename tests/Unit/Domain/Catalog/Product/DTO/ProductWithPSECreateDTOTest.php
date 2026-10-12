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

namespace Thelia\Tests\Unit\Domain\Catalog\Product\DTO;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Product\DTO\ProductWithPSECreateDTO;

/**
 * A product created together with its default combination: what the combination
 * receives when the caller leaves weight, stock, sale price or tax rule out.
 */
final class ProductWithPSECreateDTOTest extends TestCase
{
    public function testTheDefaultCombinationOfAProductGivenOnlyItsRequiredFieldsHasNoWeightStockNorSalePrice(): void
    {
        $combination = (new ProductWithPSECreateDTO(
            ref: 'TSHIRT-RED',
            title: 'Red T-shirt',
            locale: 'en_US',
            defaultCategoryId: 3,
            price: 19.9,
            currencyId: 1,
        ))->toPSEUpdateDTO();

        self::assertSame(
            [
                'reference' => 'TSHIRT-RED',
                'price' => 19.9,
                'currency_id' => 1,
                'weight' => 0.0,
                'quantity' => 0.0,
                'sale_price' => 0.0,
                'on_sale' => false,
                'is_new' => false,
                'is_default' => true,
                'ean_code' => null,
                'tax_rule_id' => 0,
                'from_default_currency' => false,
                'mpn' => null,
                'manufacturer_brand_id' => null,
            ],
            $combination->toArray(),
        );
    }

    public function testTheDefaultCombinationTakesTheValuesTheCallerGave(): void
    {
        $combination = (new ProductWithPSECreateDTO(
            ref: 'TSHIRT-BLUE',
            title: 'Blue T-shirt',
            locale: 'fr_FR',
            defaultCategoryId: 3,
            price: 24.5,
            currencyId: 2,
            weight: 0.35,
            quantity: 40,
            taxRuleId: 4,
            salePrice: 19.0,
            onSale: true,
            isNew: true,
            eanCode: '4006381333931',
        ))->toPSEUpdateDTO();

        self::assertSame(
            [
                'reference' => 'TSHIRT-BLUE',
                'price' => 24.5,
                'currency_id' => 2,
                'weight' => 0.35,
                'quantity' => 40.0,
                'sale_price' => 19.0,
                'on_sale' => true,
                'is_new' => true,
                'is_default' => true,
                'ean_code' => '4006381333931',
                'tax_rule_id' => 4,
                'from_default_currency' => false,
                'mpn' => null,
                'manufacturer_brand_id' => null,
            ],
            $combination->toArray(),
        );
    }
}
