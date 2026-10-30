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

namespace Thelia\Tests\Unit\Domain\Catalog\Bundle;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Model\ProductBundleItem;

final class BundleDefaultCompositionTest extends TestCase
{
    public function testTheDefaultHoldsTheRequiredItemsAndTheOptionalOnesAtTheirMinimum(): void
    {
        $items = [
            self::item(1, quantity: '1', required: true),
            self::item(2, quantity: '2', required: true),
            self::item(3, quantity: '1', required: false, min: '0', max: '3'),
            self::item(4, quantity: '1', required: false),
            self::item(5, quantity: '2', required: false, min: '1', max: '4'),
        ];

        $composition = BundleCompositionResolver::defaultComposition($items);

        self::assertSame('[{"bundleItemId":1,"quantity":"1"},{"bundleItemId":2,"quantity":"2"},{"bundleItemId":5,"quantity":"1"}]', $composition->toCanonicalJson());
    }

    private static function item(int $id, string $quantity, bool $required, ?string $min = null, ?string $max = null): ProductBundleItem
    {
        return (new ProductBundleItem())
            ->setId($id)
            ->setQuantity($quantity)
            ->setMinQuantity($min)
            ->setMaxQuantity($max)
            ->setRequired($required ? 1 : 0);
    }
}
