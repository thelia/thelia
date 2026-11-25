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

namespace Thelia\Domain\Quote\Service;

/**
 * Cleans what a person writes to another on a quote.
 *
 * Both notes are plain text, which every page, e-mail and document escapes when it prints them.
 * What a buyer writes loses its tags. What the shop writes loses them too, and what runs inside a
 * script or a style element with them; the entities it carries are read back as the characters they
 * stand for, and a paragraph or a line break of the markup becomes a line break of the text.
 */
final readonly class QuoteNoteSanitizer
{
    /** Longest note, in characters, before it is cleaned. */
    public const int MAXIMUM_LENGTH = 20000;

    private const int MAXIMUM_BUYER_COMMENT_LENGTH = 5000;

    public function merchantNote(?string $note): ?string
    {
        return $this->text($note);
    }

    public function buyerComment(?string $comment): ?string
    {
        $comment = null === $comment ? '' : trim(strip_tags($comment));

        return '' === $comment ? null : mb_substr($comment, 0, self::MAXIMUM_BUYER_COMMENT_LENGTH);
    }

    private function text(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        if ('' === $value) {
            return null;
        }

        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $value) ?? $value;
        $value = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|tr)>#i', "\n", $value) ?? $value;
        // Only what opens or closes an element goes: a "<" that starts no tag is a character of the text.
        $value = preg_replace('#<!--.*?-->|</?[a-z][^>]*>#is', '', $value) ?? $value;
        $text = trim(html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $text = preg_replace("#\n{3,}#", "\n\n", $text) ?? $text;

        return '' === $text ? null : $text;
    }
}
