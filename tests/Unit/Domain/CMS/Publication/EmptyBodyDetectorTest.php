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

namespace Thelia\Tests\Unit\Domain\CMS\Publication;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\CMS\Publication\EmptyBodyDetector;

final class EmptyBodyDetectorTest extends TestCase
{
    #[DataProvider('bodiesWithNothingToShow')]
    public function testABodyWithNothingToShowIsEmpty(?string $html): void
    {
        self::assertTrue((new EmptyBodyDetector())->isEmpty($html));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function bodiesWithNothingToShow(): iterable
    {
        yield 'null' => [null];
        yield 'nothing' => [''];
        yield 'spaces and line breaks' => ["  \n\t "];
        yield 'an empty paragraph' => ['<p></p>'];
        yield 'the non-breaking space an editor leaves' => ['<p>&nbsp;</p>'];
        yield 'the non-breaking space as a character' => ["<p>\u{00A0}</p>"];
        yield 'a zero-width space' => ["<p>\u{200B}</p>"];
        yield 'nested empty tags and a line break' => ['<div><p><br></p><span></span></div>'];
        yield 'a style block alone' => ['<style>p { color: red; }</style>'];
        yield 'a script block alone' => ['<script type="text/javascript">document.title = "x";</script><p></p>'];
        yield 'an html comment' => ['<!-- todo -->'];
    }

    #[DataProvider('bodiesWithSomethingToShow')]
    public function testABodyWithSomethingToShowIsNotEmpty(string $html): void
    {
        self::assertFalse((new EmptyBodyDetector())->isEmpty($html));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodiesWithSomethingToShow(): iterable
    {
        yield 'plain text' => ['Hello'];
        yield 'text in a paragraph' => ['<p>Hello</p>'];
        yield 'a single letter after a style block' => ['<style>p {}</style><p>a</p>'];
        yield 'an entity that is a character' => ['<p>&eacute;</p>'];
        yield 'an image alone' => ['<p><img src="/a.png" alt=""></p>'];
        yield 'a video alone' => ['<video src="/a.mp4"></video>'];
        yield 'an embedded frame alone' => ['<iframe src="https://example.com/embed"></iframe>'];
        yield 'a picture element' => ['<picture><source srcset="/a.webp"><img src="/a.png"></picture>'];
    }
}
