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

namespace Thelia\Domain\CMS\Publication;

/**
 * Tells an HTML body with nothing to show from one with something in it.
 *
 * An editor leaves `<p>&nbsp;</p>`, an empty `<div>` or a `<style>` block behind when the
 * writer typed nothing: none of that is a body. An image, a video, an embedded frame on
 * its own is one, the page shows it.
 */
final readonly class EmptyBodyDetector
{
    private const string MEDIA_TAGS = 'img|picture|video|audio|iframe|object|embed|svg|canvas';

    public function isEmpty(?string $html): bool
    {
        if (null === $html) {
            return true;
        }

        if (1 === preg_match('#<(?:'.self::MEDIA_TAGS.')\b#i', $html)) {
            return false;
        }

        $text = preg_replace('#<(style|script)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        // Whitespace, the non-breaking space an editor writes for an empty paragraph, the
        // zero-width space and the byte order mark are all nothing to read.
        $text = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text) ?? $text;

        return '' === $text;
    }
}
