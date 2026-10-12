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
use Thelia\Domain\Catalog\Product\DTO\PSEUpdateDTO;

/**
 * The array a combination update hands to the event layer carries every field
 * of the combination, the manufacturer part number and brand included.
 */
final class PSEUpdateDTOTest extends TestCase
{
    public function testTheManufacturerPartNumberAndBrandAreHandedOverWithTheOtherFields(): void
    {
        $dto = new PSEUpdateDTO(
            reference: 'REF-001',
            price: 99.99,
            currencyId: 1,
            weight: 1.5,
            quantity: 100.0,
            salePrice: 79.99,
            onSale: true,
            isNew: false,
            isDefault: true,
            eanCode: '4006381333931',
            taxRuleId: 5,
            fromDefaultCurrency: true,
            mpn: 'MPN-42',
            manufacturerBrandId: 8,
        );

        self::assertSame(
            [
                'reference' => 'REF-001',
                'price' => 99.99,
                'currency_id' => 1,
                'weight' => 1.5,
                'quantity' => 100.0,
                'sale_price' => 79.99,
                'on_sale' => true,
                'is_new' => false,
                'is_default' => true,
                'ean_code' => '4006381333931',
                'tax_rule_id' => 5,
                'from_default_currency' => true,
                'mpn' => 'MPN-42',
                'manufacturer_brand_id' => 8,
            ],
            $dto->toArray(),
        );
    }
}
