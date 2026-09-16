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

namespace Thelia\Domain\Localization\Service;

/**
 * The writing direction of a language, which drives the HTML "dir" attribute.
 *
 * Kept as a list rather than a column on `lang`: which way a language is written is a
 * linguistic fact a shop has no business editing, and a language written right to left
 * ships with a Thelia update the same way a new currency does. A theme that wants to
 * force a direction of its own writes it in its own templates.
 */
final class LocaleDirection
{
    public const LEFT_TO_RIGHT = 'ltr';

    public const RIGHT_TO_LEFT = 'rtl';

    /**
     * Language codes written right to left, as ISO 639 codes: Arabic, Aramaic, Central
     * Kurdish, Divehi, Persian, Hebrew, Pashto, Sindhi, Uyghur, Urdu, Yiddish.
     *
     * "ku" is deliberately absent, and it is the one entry worth a note because most
     * hand-written lists get it wrong. Bare "ku" is Kurmanji, written in the Latin
     * alphabet: CLDR resolves it to ku_Latn_TR and classes it left to right. The
     * right-to-left Kurdish is Sorani, which has its own code, "ckb", listed above.
     * A shop that really serves Kurdish in Arabic script declares it as ckb.
     *
     * @var list<string>
     */
    public const RIGHT_TO_LEFT_LANGUAGES = [
        'ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'yi',
    ];

    /**
     * The direction of a language code ("ar") or of a full locale ("ar_SA", "ar-MA"):
     * a region never changes the way a language is written, so only the language part
     * is read. Anything unknown, empty or malformed reads left to right, because a
     * template asking for a direction has to be given one.
     *
     * @return self::LEFT_TO_RIGHT|self::RIGHT_TO_LEFT
     */
    public function forLocale(?string $locale): string
    {
        return $this->isRightToLeft($locale) ? self::RIGHT_TO_LEFT : self::LEFT_TO_RIGHT;
    }

    public function isRightToLeft(?string $locale): bool
    {
        return \in_array($this->languageOf((string) $locale), self::RIGHT_TO_LEFT_LANGUAGES, true);
    }

    /**
     * The language part of a locale, whichever separator it was written with: Thelia
     * stores "ar_SA" while a browser and an HTML lang attribute both spell it "ar-SA".
     */
    private function languageOf(string $locale): string
    {
        $language = strtolower(trim($locale));
        $separatorPosition = strcspn($language, '_-');

        return substr($language, 0, $separatorPosition);
    }
}
