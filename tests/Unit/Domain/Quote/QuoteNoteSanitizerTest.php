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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\Service\QuoteNoteSanitizer;

/**
 * What the shop writes to a buyer is kept as plain text: every page escapes it when it prints it, so
 * what is stored must not carry markup or entities that would be printed as they are.
 */
final class QuoteNoteSanitizerTest extends TestCase
{
    public function testTheTextOfTheNoteIsKeptAsItWasWritten(): void
    {
        self::assertSame("Prix valable jusqu'à l'inventaire & R&D <3 et 5<6", (new QuoteNoteSanitizer())->merchantNote("Prix valable jusqu'à l'inventaire & R&D <3 et 5<6"));
    }

    public function testTheTagsGoAndTheTextInsideThemStays(): void
    {
        self::assertSame('x', (new QuoteNoteSanitizer())->merchantNote('<b>x</b>'));
        self::assertSame('Valid until the 16th, see the terms.', (new QuoteNoteSanitizer())->merchantNote('<p onclick="steal()">Valid <strong>until</strong> the 16th, see <a href="https://example.test/terms">the terms</a>.</p>'));
    }

    public function testTheEntitiesAreReadBackAsTheCharactersTheyStandFor(): void
    {
        self::assertSame("jusqu'à l'inventaire", (new QuoteNoteSanitizer())->merchantNote('jusqu&#039;à l&apos;inventaire'));
        self::assertSame('Fish & chips "2 for 1"', (new QuoteNoteSanitizer())->merchantNote('Fish &amp; chips &quot;2 for 1&quot;'));
    }

    public function testWhatRunsInAScriptOrAStyleDoesNotSurviveAsText(): void
    {
        $clean = (new QuoteNoteSanitizer())->merchantNote(
            '<p>Hello</p><script>alert(1)</script><style>p{display:none}</style><img src="https://tracker.test/p.png"><iframe src="https://evil.test"></iframe><form action="https://evil.test"><input name="card"></form>',
        );

        self::assertSame('Hello', $clean);
    }

    public function testTheParagraphsAndTheBreaksOfTheMarkupBecomeLines(): void
    {
        self::assertSame("First\nSecond\nThird", (new QuoteNoteSanitizer())->merchantNote('<p>First</p><p>Second<br/>Third</p>'));
    }

    public function testABlankNoteIsNoNote(): void
    {
        $sanitizer = new QuoteNoteSanitizer();

        self::assertNull($sanitizer->merchantNote(null));
        self::assertNull($sanitizer->merchantNote("  \n "));
        self::assertNull($sanitizer->merchantNote('<script>alert(1)</script>'));
        self::assertNull($sanitizer->merchantNote('<p></p>'));
    }

    public function testWhatTheBuyerWritesIsPlainText(): void
    {
        $sanitizer = new QuoteNoteSanitizer();

        self::assertSame('Need it by Friday alert(1)', $sanitizer->buyerComment(' Need it <b>by</b> Friday <script>alert(1)</script> '));
        self::assertNull($sanitizer->buyerComment('<br>'));
        self::assertSame(5000, mb_strlen((string) $sanitizer->buyerComment(str_repeat('a', 6000))));
    }
}
