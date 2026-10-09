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
 * character could rewrite or hide what is printed. The escaping for the output format
 * (HTML, the console formatter) stays with the caller.
 */
final class TerminalText
{
    /**
     * The text with every character a terminal, a log or a page would obey or hide replaced
     * by "?", in three steps, each of which leaves nothing rather than the text it was to
     * clean when it fails. A backtrack limit proves it for the second step. It cannot for
     * the first: the lowest limit stops it, but had it given the text back, the same limit
     * would stop the second step on that text; and a text left invalid would fail the
     * third step, in Unicode mode, anyway. It cannot for the third step nor for the run of
     * blanks of onOneLine() either: no limit stops them without stopping an earlier step
     * first. These three stand by construction.
     *
     * 1. A text that is not valid UTF-8 has each byte outside a well-formed sequence
     *    replaced: a raw C1 byte (0x80 to 0x9F) is a control character to a terminal that is
     *    not in UTF-8.
     * 2. Matched as their UTF-8 encoding, byte by byte: the C0 and C1 control characters but
     *    the tab and the line feed (kept: messages span lines and are indented; an
     *    identifier goes through singleLine(), which replaces them too), the soft hyphen
     *    (U+00AD), the Arabic letter mark (U+061C), the zero-width and directional marks
     *    (U+200B to U+200F), the line and paragraph separators (U+2028, U+2029), the
     *    bidirectional embeddings, overrides and isolates (U+202A to U+202E, U+2066 to
     *    U+2069), the invisible operators and word joiner (U+2060 to U+2064), the deprecated
     *    format controls (U+206A to U+206F), the byte order mark (U+FEFF), the interlinear
     *    annotation marks (U+FFF9 to U+FFFB), the Mongolian free variation selectors and
     *    vowel separator (U+180B to U+180F), the combining grapheme joiner (U+034F), the
     *    variation selectors (U+FE00 to U+FE0F, U+E0100 to U+E01FF), the musical format
     *    controls (U+1D173 to U+1D17A), the blank fillers (U+115F, U+1160, U+2800, U+3164,
     *    U+FFA0), the tags block and the
     *    unassigned code points that follow it (U+E0000 to U+E00FF), the Khmer inherent
     *    vowels (U+17B4, U+17B5), the shorthand format controls (U+1BCA0 to U+1BCA3) and
     *    the Egyptian hieroglyph format controls (U+13430 to U+1343F, format characters
     *    added in Unicode 12 and 15, that an older PCRE may not know). Many of them are
     *    format characters (Cf) that the third step would replace too: this step stands on
     *    its own, without the Unicode tables of PCRE.
     * 3. On what is then valid UTF-8, in Unicode mode: every other format character
     *    (category Cf, the Arabic number signs among them), every private use character
     *    (Co), the object replacement character (U+FFFC), the noncharacters of every plane
     *    (U+FDD0 to U+FDEF, and U+FFFE and U+FFFF of each of the 17 planes), the unassigned
     *    ignorable code points (U+2065 of the general punctuation block, U+FFF0 to U+FFF8 of
     *    the specials block, and the rest of the tags and variation selectors blocks of
     *    plane 14, U+E0000 to U+E0FFF), which print as nothing or as a glyph that is not
     *    the text.
     */
    public static function withoutControlCharacters(string $text): string
    {
        if (1 !== preg_match('//u', $text)) {
            // Every byte that does not belong to a well-formed UTF-8 sequence becomes "?",
            // whatever extension is loaded and whatever substitute character it is set to.
            $text = preg_replace('/(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})(*SKIP)(*FAIL)|[\x80-\xFF]/', '?', $text) ?? '';
        }

        $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F\xAD]|\xD8\x9C|\xE2\x80[\x8B-\x8F\xA8-\xAE]|\xE2\x81[\xA0-\xA4\xA6-\xAF]|\xEF\xBB\xBF|\xEF\xBF[\xB9-\xBB]|\xE1\xA0[\x8B-\x8F]|\xCD\x8F|\xEF\xB8[\x80-\x8F]|\xF3\xA0[\x84-\x87][\x80-\xBF]|\xF0\x9D\x85[\xB3-\xBA]|\xE1\x85[\x9F\xA0]|\xE2\xA0\x80|\xE3\x85\xA4|\xEF\xBE\xA0|\xF3\xA0[\x80-\x83][\x80-\xBF]|\xE1\x9E[\xB4\xB5]|\xF0\x9B\xB2[\xA0-\xA3]|\xF0\x93\x90[\xB0-\xBF]/', '?', $text) ?? '';

        return preg_replace('/[\p{Cf}\p{Co}\x{FFFC}\x{2065}\x{FFF0}-\x{FFF8}\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}\x{BFFFE}\x{BFFFF}\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{EFFFE}\x{EFFFF}\x{FFFFE}\x{FFFFF}\x{10FFFE}\x{10FFFF}\x{E0000}-\x{E0FFF}]/u', '?', $text) ?? '';
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
     * A message on one line: each line break (a carriage return is one), and the blanks
     * around it (any blank of Unicode), becomes a space, so that a text spread over lines
     * (the errors of a schema, one per line) cannot start a line of its own that reads like
     * the output of the command. The blanks without a line break are kept as they are (a
     * no-break space at either end among them), a tab in a run of blanks that holds no
     * line break becomes "?" (at either end too), what withoutControlCharacters() replaces
     * is replaced the same way, and the spaces and line breaks at either end are dropped.
     * "a  \n b\tc" gives "a b?c". When PCRE fails, nothing is given, as in
     * withoutControlCharacters().
     */
    public static function onOneLine(string $text): string
    {
        // A carriage return is a line break too. Cleaned first: what follows runs on valid
        // UTF-8, in Unicode mode, so that the tables of the locale never take the second
        // byte of "à" (0xA0) for a blank.
        $cleaned = self::withoutControlCharacters(str_replace("\r", "\n", $text));

        // Every run of blanks is matched as a whole, from its first blank, and told apart in
        // the callback: one that holds a line break becomes a space, the others stay. That
        // is what keeps the time linear: a pattern that had to find a line break after the
        // blanks (such as "\s*[\r\n]+") was tried again from each blank of a run, and a
        // run of thirty thousand blanks took five seconds without the PCRE JIT.
        $onOneLine = preg_replace_callback(
            '/\s++/u',
            static fn (array $run): string => str_contains($run[0], "\n") ? ' ' : $run[0],
            $cleaned,
        ) ?? '';

        return trim(str_replace("\t", '?', $onOneLine));
    }
}
