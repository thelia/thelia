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

namespace Thelia\Tests\Unit\Domain\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;

/**
 * The tracking address template a merchant types for a carrier, and the link it gives
 * once the tracking number of an order is put in place of %ID%.
 */
final class OrderTrackingUrlTemplateTest extends TestCase
{
    public function testTheTrackingNumberTakesThePlaceOfTheMarker(): void
    {
        self::assertSame(
            'https://carrier.example/track?parcel=6A12345678901',
            OrderTrackingUrlResolver::fill('https://carrier.example/track?parcel=%ID%', '6A12345678901'),
        );
    }

    /**
     * A number typed by hand or set by a module may carry a space, a slash or an
     * ampersand: put raw into the address, it would cut the query short or point to
     * another page of the carrier.
     */
    public function testATrackingNumberIsEncodedSoTheLinkStaysValid(): void
    {
        self::assertSame(
            'https://carrier.example/track/AB%2012%2F3%23%26x%3D1?lang=fr',
            OrderTrackingUrlResolver::fill('https://carrier.example/track/%ID%?lang=fr', 'AB 12/3#&x=1'),
        );
    }

    public function testTheSpacesAroundTheNumberAndTheTemplateAreIgnored(): void
    {
        self::assertSame(
            'https://carrier.example/6A123',
            OrderTrackingUrlResolver::fill('  https://carrier.example/%ID% ', ' 6A123 '),
        );
    }

    public function testNoTrackingNumberGivesNoLink(): void
    {
        self::assertNull(OrderTrackingUrlResolver::fill('https://carrier.example/%ID%', '   '));
    }

    #[DataProvider('refusedTemplates')]
    public function testATemplateThatIsNotAWebAddressWithTheMarkerGivesNoLink(string $template): void
    {
        self::assertFalse(OrderTrackingUrlResolver::isValidTemplate($template));
        self::assertNull(OrderTrackingUrlResolver::fill($template, '6A123'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedTemplates(): iterable
    {
        yield 'no marker' => ['https://carrier.example/track'];
        yield 'the marker alone' => ['%ID%'];
        yield 'script' => ['javascript:alert(document.cookie)//%ID%'];
        yield 'data' => ['data:text/html;base64,PHNjcmlwdD4=%ID%'];
        yield 'ftp' => ['ftp://carrier.example/%ID%'];
        yield 'no host' => ['https:///%ID%'];
        yield 'relative' => ['/track/%ID%'];
        yield 'protocol relative' => ['//carrier.example/%ID%'];
        yield 'credentials hiding the real host' => ['https://carrier.example@attacker.example/%ID%'];
        yield 'space inside' => ['https://carrier.example/track %ID%'];
        yield 'backslash read as a slash by a browser' => ['https://attacker.example\\.carrier.example/%ID%'];
        yield 'backslash before the host' => ['https://\\attacker.example/%ID%'];
        yield 'marker in the host' => ['https://%ID%.carrier.example/track'];
        yield 'marker as the host' => ['https://%ID%/track'];
        yield 'control character' => ["https://carrier.example/track\x01%ID%"];
        yield 'no-break space' => ["https://carrier.example/track\u{00A0}%ID%"];
        yield 'line break inside' => ["https://carrier.example/\n%ID%"];
    }

    public function testTheSchemeIsReadWhateverItsCase(): void
    {
        self::assertTrue(OrderTrackingUrlResolver::isValidTemplate('HTTPS://carrier.example/%ID%'));
        self::assertTrue(OrderTrackingUrlResolver::isValidTemplate('http://carrier.example:8080/t?n=%ID%#top'));
    }
}
