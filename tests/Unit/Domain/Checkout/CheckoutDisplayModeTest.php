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

namespace Thelia\Tests\Unit\Domain\Checkout;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;

/**
 * How the theme is told to lay the checkout out.
 *
 * The setting is read from the `config` table, which answers with a string or with
 * nothing at all: an unknown value, a half-finished edit or a missing row all have to
 * land on the layout a shop already has, never on a blank one.
 */
final class CheckoutDisplayModeTest extends TestCase
{
    public function testAMissingSettingFallsBackToSteps(): void
    {
        self::assertSame(CheckoutDisplayMode::Steps, CheckoutDisplayMode::fromStoredValue(null));
    }

    public function testAnUnknownValueFallsBackToSteps(): void
    {
        self::assertSame(CheckoutDisplayMode::Steps, CheckoutDisplayMode::fromStoredValue('accordion'));
        self::assertSame(CheckoutDisplayMode::Steps, CheckoutDisplayMode::fromStoredValue(''));
    }

    /**
     * "0" is what the settings, the forms and the templates of a shop all read as
     * "off". A mode named that way would answer false to half the code that reads it,
     * so the name stays taken by nothing.
     */
    public function testNoModeIsNamedZero(): void
    {
        foreach (CheckoutDisplayMode::cases() as $mode) {
            self::assertNotSame('0', $mode->value);
        }

        self::assertSame(CheckoutDisplayMode::Steps, CheckoutDisplayMode::fromStoredValue('0'));
    }

    public function testEveryKnownValueIsRecognised(): void
    {
        self::assertSame(CheckoutDisplayMode::Steps, CheckoutDisplayMode::fromStoredValue('steps'));
        self::assertSame(CheckoutDisplayMode::OnePage, CheckoutDisplayMode::fromStoredValue('one_page'));
    }
}
