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

namespace Thelia\Tests\Unit\Domain\Order\Twig;

use PHPUnit\Framework\TestCase;
use Picqer\Barcode\Types\TypeCode128;
use Thelia\Domain\Order\Twig\BarcodeExtension;
use Twig\TwigFunction;

final class BarcodeExtensionTest extends TestCase
{
    public function testTheFunctionIsDeclaredToTwig(): void
    {
        $names = array_map(static fn (TwigFunction $function): string => $function->getName(), (new BarcodeExtension())->getFunctions());

        self::assertContains('barcode_code128', $names);
    }

    public function testTheValueIsReadableBackFromTheSvg(): void
    {
        $uri = (new BarcodeExtension())->code128DataUri('ORD-2026-0042', 2.0, 40);

        self::assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = base64_decode(substr($uri, \strlen('data:image/svg+xml;base64,')), true);
        self::assertIsString($svg);
        self::assertStringContainsString('<svg', $svg);

        // The bars drawn are the Code 128 bars of the value, same widths at the same positions.
        $expected = (new TypeCode128())->getBarcode('ORD-2026-0042');
        $drawn = [];
        preg_match_all('#<rect x="([\d.]+)"[^>]*width="([\d.]+)"#', $svg, $drawn, \PREG_SET_ORDER);

        $x = 0.0;
        $index = 0;
        foreach ($expected->getBars() as $bar) {
            if ($bar->isBar() && $bar->getWidth() > 0) {
                self::assertEqualsWithDelta($x, (float) $drawn[$index][1], 0.001);
                self::assertEqualsWithDelta($bar->getWidth() * 2.0, (float) $drawn[$index][2], 0.001);
                ++$index;
            }
            $x += $bar->getWidth() * 2.0;
        }

        self::assertCount($index, $drawn);
    }

    public function testASingleCharacterIsFortySixModulesWide(): void
    {
        // Start B (11) + character (11) + check character (11) + stop (13), at 2 units a module.
        $uri = (new BarcodeExtension())->code128DataUri('A', 2.0, 40);
        $svg = (string) base64_decode(substr($uri, \strlen('data:image/svg+xml;base64,')), true);

        self::assertMatchesRegularExpression('#<svg width="92"#', $svg);
    }

    public function testDifferentValuesGiveDifferentBars(): void
    {
        $extension = new BarcodeExtension();

        self::assertNotSame($extension->code128DataUri('A1'), $extension->code128DataUri('A2'));
    }

    /**
     * An order reference with an accent cannot be a Code 128: no barcode, rather than an error that would stop
     * the whole batch, or bars a scanner would read as another reference.
     */
    public function testAValueOutsideAsciiHasNoBarcode(): void
    {
        self::assertNull((new BarcodeExtension())->code128DataUri('CMD-ÉTÉ-2026'));
        self::assertNull((new BarcodeExtension())->code128DataUri('订单-42'));
    }

    public function testAnEmptyValueIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BarcodeExtension())->code128DataUri('');
    }
}
