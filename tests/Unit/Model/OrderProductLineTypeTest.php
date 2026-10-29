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

namespace Thelia\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Model\OrderProduct;

/**
 * What each kind of order line says of itself: the readings everything that walks the lines
 * of an order (stock, shipments, returns) branches on.
 */
final class OrderProductLineTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool, bool, bool, bool, bool}>
     */
    public static function lineTypes(): iterable
    {
        //                                    goods  service bundle component shippable movesStock
        yield 'a product' => [OrderProduct::LINE_TYPE_PRODUCT, true, false, false, false, true, true];
        yield 'a service' => [OrderProduct::LINE_TYPE_SERVICE, false, true, false, false, false, true];
        yield 'the line of a bundle' => [OrderProduct::LINE_TYPE_BUNDLE, true, false, true, false, false, false];
        yield 'a component of a bundle' => [OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, true, false, false, true, true, true];
        yield 'a type of a later version' => ['gift_card', true, false, false, false, true, true];
    }

    #[DataProvider('lineTypes')]
    public function testWhatALineSaysOfItself(string $lineType, bool $goods, bool $service, bool $bundle, bool $component, bool $shippable, bool $movesStock): void
    {
        $line = (new OrderProduct())->setLineType($lineType);

        self::assertSame($goods, $line->isProductLine());
        self::assertSame($service, $line->isServiceLine());
        self::assertSame($bundle, $line->isBundleLine());
        self::assertSame($component, $line->isBundleComponentLine());
        self::assertSame($shippable, $line->isShippableLine());
        self::assertSame($movesStock, $line->movesStockOfItsOwn());
    }

    public function testALineWithNoTypeIsAProduct(): void
    {
        $line = new OrderProduct();

        self::assertTrue($line->isProductLine());
        self::assertTrue($line->isShippableLine());
        self::assertFalse($line->isBundleLine());
    }
}
