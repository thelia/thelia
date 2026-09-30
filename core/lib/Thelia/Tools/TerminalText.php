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
 * Text written to an operator's terminal from something a module ships (its descriptor, its
 * directory name): a control character could rewrite or hide what the console prints.
 */
final class TerminalText
{
    /**
     * The text with every C0 and C1 control character but the tab and the line feed replaced,
     * and the formatting characters that reorder or hide text on screen: the bidirectional
     * embeddings, overrides and isolates (U+202A to U+202E, U+2066 to U+2069), the zero-width
     * and directional marks (U+200B to U+200F, U+061C), the invisible operators and word
     * joiner (U+2060 to U+2064), the deprecated format controls (U+206A to U+206F), the
     * interlinear annotation marks (U+FFF9 to U+FFFB), the tag characters (U+E0000 to
     * U+E007F), the Mongolian vowel separator (U+180E), the combining grapheme joiner
     * (U+034F), the variation selectors (U+FE00 to U+FE0F), the blank fillers (U+115F,
     * U+1160, U+2800, U+3164, U+FFA0), the soft hyphen (U+00AD), the line and paragraph separators
     * (U+2028, U+2029) and the byte order mark (U+FEFF). The line feed is kept: messages span
     * lines, so a module directory named with one can still start a line of its own. Each range is matched as its UTF-8 encoding,
     * byte by byte, so a text that is not valid UTF-8 is still cleaned instead of dropped.
     */
    public static function withoutControlCharacters(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F\xAD]|\xD8\x9C|\xE2\x80[\x8B-\x8F\xA8-\xAE]|\xE2\x81[\xA0-\xA4\xA6-\xA9\xAA-\xAF]|\xEF\xBB\xBF|\xEF\xBF[\xB9-\xBB]|\xE1\xA0\x8E|\xCD\x8F|\xEF\xB8[\x80-\x8F]|\xE1\x85[\x9F\xA0]|\xE2\xA0\x80|\xE3\x85\xA4|\xEF\xBE\xA0|\xF3\xA0[\x80\x81][\x80-\xBF]/', '?', $text) ?? '';
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
}
