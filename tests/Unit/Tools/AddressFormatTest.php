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

namespace Thelia\Tests\Unit\Tools;

use CommerceGuys\Addressing\Address;
use PHPUnit\Framework\TestCase;
use Thelia\Tools\AddressFormat;

/**
 * Exercises the thin wrapper around CommerceGuys/Addressing.
 *
 * Thelia-specific methods (`formatTheliaAddress`, `postalLabelFormat`, …)
 * depend on Propel models and live database fixtures, so they are covered
 * by integration tests instead of this file.
 */
final class AddressFormatTest extends TestCase
{
    public function testGetInstanceReturnsASingleton(): void
    {
        self::assertSame(AddressFormat::getInstance(), AddressFormat::getInstance());
    }

    public function testFormatRendersHtmlParagraphForFrenchAddress(): void
    {
        $address = (new Address())
            ->withCountryCode('FR')
            ->withAddressLine1('10 rue de la Paix')
            ->withPostalCode('75002')
            ->withLocality('Paris')
            ->withGivenName('John')
            ->withFamilyName('Doe');

        $formatted = AddressFormat::getInstance()->format($address, 'fr_FR');
        $plain = strip_tags($formatted);

        self::assertStringContainsString('<p', $formatted);
        self::assertStringContainsString('John', $plain);
        self::assertStringContainsString('Doe', $plain);
        self::assertStringContainsString('10 rue de la Paix', $plain);
        self::assertStringContainsString('75002', $plain);
        self::assertStringContainsString('Paris', $plain);
    }

    public function testFormatCanRenderPlainTextWhenHtmlFlagIsFalse(): void
    {
        $address = (new Address())
            ->withCountryCode('FR')
            ->withAddressLine1('10 rue de la Paix')
            ->withPostalCode('75002')
            ->withLocality('Paris')
            ->withGivenName('John')
            ->withFamilyName('Doe');

        $formatted = AddressFormat::getInstance()->format($address, 'fr_FR', html: false);

        self::assertStringNotContainsString('<p', $formatted);
        self::assertStringContainsString('John Doe', $formatted);
        self::assertStringContainsString('75002 Paris', $formatted);
    }

    public function testFormatWrapsInsideCustomHtmlTag(): void
    {
        $address = (new Address())
            ->withCountryCode('US')
            ->withAddressLine1('1600 Amphitheatre Pkwy')
            ->withPostalCode('94043')
            ->withLocality('Mountain View')
            ->withAdministrativeArea('US-CA')
            ->withGivenName('John')
            ->withFamilyName('Doe');

        $formatted = AddressFormat::getInstance()->format($address, 'en', htmlTag: 'address');

        self::assertStringStartsWith('<address', $formatted);
        self::assertStringContainsString('Mountain View', $formatted);
    }
}
