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
     * joiner (U+2060 to U+2064), the soft hyphen (U+00AD), the line and paragraph separators
     * (U+2028, U+2029) and the byte order mark (U+FEFF). The line feed is kept: messages span
     * lines, so a module directory named with one can still start a line of its own. Each range is matched as its UTF-8 encoding,
     * byte by byte, so a text that is not valid UTF-8 is still cleaned instead of dropped.
     */
    public static function withoutControlCharacters(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F\xAD]|\xD8\x9C|\xE2\x80[\x8B-\x8F\xA8-\xAE]|\xE2\x81[\xA0-\xA4\xA6-\xA9]|\xEF\xBB\xBF/', '?', $text) ?? '';
    }
}
