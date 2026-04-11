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

namespace Thelia\Tests\Unit\Taxation;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Model\OrderProductTax;

final class OrderProductTaxCollectionTest extends TestCase
{
    public function testEmptyOnConstruction(): void
    {
        $collection = new OrderProductTaxCollection();

        self::assertTrue($collection->isEmpty());
        self::assertSame(0, $collection->getCount());
    }

    public function testConstructorAcceptsVariadicTaxes(): void
    {
        $a = new OrderProductTax();
        $b = new OrderProductTax();

        $collection = new OrderProductTaxCollection($a, $b);

        self::assertFalse($collection->isEmpty());
        self::assertSame(2, $collection->getCount());
    }

    public function testAddTaxIsFluent(): void
    {
        $collection = new OrderProductTaxCollection();
        $same = $collection->addTax(new OrderProductTax());

        self::assertSame($collection, $same);
    }

    public function testIterationWalksInInsertionOrder(): void
    {
        $a = (new OrderProductTax())->setAmount('1.00');
        $b = (new OrderProductTax())->setAmount('2.00');
        $c = (new OrderProductTax())->setAmount('3.00');

        $collection = new OrderProductTaxCollection($a, $b, $c);

        $walked = [];
        foreach ($collection as $tax) {
            $walked[] = (float) $tax->getAmount();
        }

        self::assertSame([1.0, 2.0, 3.0], $walked);
    }

    public function testGetKeyReturnsTaxAtIndexOrNull(): void
    {
        $a = new OrderProductTax();
        $collection = new OrderProductTaxCollection($a);

        self::assertSame($a, $collection->getKey(0));
        self::assertNull($collection->getKey(99));
    }
}
