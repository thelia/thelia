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
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\CartItem;

final class CartItemBundleCompositionTest extends TestCase
{
    public function testALineOutsideABundleHasNoComposition(): void
    {
        self::assertNull((new CartItem())->getBundleCompositionValue());
        self::assertNull((new CartItem())->setBundleComposition('')->getBundleCompositionValue());
    }

    public function testTheStoredJsonIsReadBackAsAComposition(): void
    {
        $line = (new CartItem())->setBundleComposition('[{"bundleItemId":12,"quantity":"1"},{"bundleItemId":13,"quantity":"2"}]');

        self::assertSame('2', $line->getBundleCompositionValue()?->quantityOf(13));
    }

    public function testAStoredJsonThatIsNotACompositionIsRefused(): void
    {
        $this->expectException(InvalidBundleCompositionException::class);

        (new CartItem())->setBundleComposition('{"oops":1}')->getBundleCompositionValue();
    }
}
