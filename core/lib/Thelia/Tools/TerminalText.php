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

namespace Thelia\Tools;

/**
 * Text written to an operator's terminal, to a log or to a page of the back office from
 * something a module or a theme ships (its descriptor, its directory name): a control
 * character could rewrite or hide what is printed.
 */
final class TerminalText
{
    /**
     * The text with every C0 and C1 control character but the tab and the line feed replaced,
     * and the formatting characters that reorder or hide text on screen: the bidirectional
     * embeddings, overrides and isolates (U+202A to U+202E, U+2066 to U+2069), the zero-width
     * and directional marks (U+200B to U+200F, U+061C), the invisible operators and word
     * joiner (U+2060 to U+2064), the deprecated format controls (U+206A to U+206F), the
     * interlinear annotation marks (U+FFF9 to U+FFFB), the tag block (U+E0000 to U+E00FF), the
     * Khmer inherent vowels (U+17B4, U+17B5), the shorthand format controls (U+1BCA0 to
     * U+1BCA3), the Mongolian free variation selectors and vowel separator (U+180B to U+180F),
     * the combining grapheme joiner (U+034F), the variation selectors (U+FE00 to U+FE0F,
     * U+E0100 to U+E01FF), the musical format controls (U+1D173 to U+1D17A), the blank fillers
     * (U+115F, U+1160, U+2800, U+3164, U+FFA0), the soft hyphen (U+00AD), the line and
     * paragraph separators (U+2028, U+2029) and the byte order mark (U+FEFF). The line feed is
     * kept: messages span lines, so a module directory named with one can still start a line
     * of its own. A text that is not valid UTF-8 has each byte outside a well-formed sequence
     * replaced first: a raw C1 byte (0x80 to 0x9F) is a control character to a terminal that
     * is not in UTF-8. Each range is then matched as its UTF-8 encoding, byte by byte. Last,
     * on what is then valid UTF-8, every other format character (Unicode category Cf, the
     * Arabic number signs among them), private use character (Co), the object replacement
     * character (U+FFFC), the noncharacters (U+FDD0 to U+FDEF, U+FFFE, U+FFFF) and the
     * unassigned ignorable code points of the specials block (U+2065, U+FFF0 to U+FFF8),
     * which print as nothing or as a glyph that is not the text. A step that fails leaves
     * nothing rather than the text it was to clean.
     */
    public static function withoutControlCharacters(string $text): string
    {
        if (1 !== preg_match('//u', $text)) {
            // Every byte that does not belong to a well-formed UTF-8 sequence becomes "?",
            // whatever extension is loaded and whatever substitute character it is set to.
            $text = preg_replace('/(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})(*SKIP)(*FAIL)|[\x80-\xFF]/', '?', $text) ?? '';
        }

        $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F\xAD]|\xD8\x9C|\xE2\x80[\x8B-\x8F\xA8-\xAE]|\xE2\x81[\xA0-\xA4\xA6-\xA9\xAA-\xAF]|\xEF\xBB\xBF|\xEF\xBF[\xB9-\xBB]|\xE1\xA0[\x8B-\x8F]|\xCD\x8F|\xEF\xB8[\x80-\x8F]|\xF3\xA0[\x84-\x87][\x80-\xBF]|\xF0\x9D\x85[\xB3-\xBA]|\xE1\x85[\x9F\xA0]|\xE2\xA0\x80|\xE3\x85\xA4|\xEF\xBE\xA0|\xF3\xA0[\x80-\x83][\x80-\xBF]|\xE1\x9E[\xB4\xB5]|\xF0\x9B\xB2[\xA0-\xA3]/', '?', $text) ?? '';

        return preg_replace('/[\p{Cf}\p{Co}\x{FFFC}\x{2065}\x{FFF0}-\x{FFF8}\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}]/u', '?', $text) ?? '';
    }

    /**
     * An identifier (a module code, a directory name) on one line: the line feed and the tab
     * withoutControlCharacters() keeps for messages are replaced too, so the identifier cannot
     * start a line of its own that reads like the output of the command.
     */
    public static function singleLine(string $text): string
    {
        return str_replace(["\n", "\t"], '?', self::withoutControlCharacters($text));
    }

    /**
     * A message on one line: each line break, and the blanks around it, becomes a space, so
     * that a text spread over lines (the errors of a schema, one per line) cannot start a
     * line of its own that reads like the output of the command.
     */
    public static function onOneLine(string $text): string
    {
        // Cleaned first: what follows runs on valid UTF-8, in Unicode mode, so that the
        // tables of the locale never take the second byte of "à" (0xA0) for a blank. Every
        // run of blanks is then read once, as a whole (possessive, from its first blank on:
        // no run is read again from each of its positions, which took seconds on a run of
        // thousands without the PCRE JIT): one that holds a line break becomes a space, the
        // others stay. A carriage return is a line break too.
        $cleaned = self::withoutControlCharacters(str_replace("\r", "\n", $text));
        $onOneLine = preg_replace_callback(
            '/\s++/u',
            static fn (array $run): string => str_contains($run[0], "\n") ? ' ' : $run[0],
            $cleaned,
        ) ?? '';

        return trim(str_replace("\t", '?', $onOneLine));
    }
}
