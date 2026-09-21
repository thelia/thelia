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

namespace Thelia\Tests\Unit\Domain\Localization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Localization\Service\LocaleDirection;

final class LocaleDirectionTest extends TestCase
{
    public function testElevenLanguagesAreWrittenRightToLeft(): void
    {
        self::assertCount(11, LocaleDirection::RIGHT_TO_LEFT_LANGUAGES);
        self::assertSame(
            LocaleDirection::RIGHT_TO_LEFT_LANGUAGES,
            array_unique(LocaleDirection::RIGHT_TO_LEFT_LANGUAGES),
        );
    }

    #[DataProvider('directions')]
    public function testDirectionOfALocale(?string $locale, string $direction): void
    {
        self::assertSame($direction, (new LocaleDirection())->forLocale($locale));
    }

    #[DataProvider('directions')]
    public function testARightToLeftLocaleIsReportedAsSuch(?string $locale, string $direction): void
    {
        self::assertSame(
            LocaleDirection::RIGHT_TO_LEFT === $direction,
            (new LocaleDirection())->isRightToLeft($locale),
        );
    }

    /**
     * Every listed language answers rtl under its bare code, so that adding one to the
     * list is enough to serve it.
     */
    public function testEveryListedLanguageIsWrittenRightToLeft(): void
    {
        foreach (LocaleDirection::RIGHT_TO_LEFT_LANGUAGES as $language) {
            self::assertSame(
                LocaleDirection::RIGHT_TO_LEFT,
                (new LocaleDirection())->forLocale($language),
                \sprintf('"%s" is listed as written right to left', $language),
            );
        }
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function directions(): iterable
    {
        yield 'Arabic' => ['ar', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Aramaic' => ['arc', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Central Kurdish' => ['ckb', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Divehi' => ['dv', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Persian' => ['fa', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Hebrew' => ['he', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Pashto' => ['ps', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Sindhi' => ['sd', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Uyghur' => ['ug', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Urdu' => ['ur', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Yiddish' => ['yi', LocaleDirection::RIGHT_TO_LEFT];

        yield 'Arabic as spoken in Saudi Arabia' => ['ar_SA', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Arabic as spoken in Morocco' => ['ar_MA', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Hebrew as spoken in Israel' => ['he_IL', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Persian as spoken in Iran' => ['fa_IR', LocaleDirection::RIGHT_TO_LEFT];
        yield 'Urdu as spoken in Pakistan' => ['ur_PK', LocaleDirection::RIGHT_TO_LEFT];
        yield 'a locale written with a dash, as a browser sends it' => ['ar-SA', LocaleDirection::RIGHT_TO_LEFT];
        yield 'mixed case' => ['AR_sa', LocaleDirection::RIGHT_TO_LEFT];
        yield 'upper case code' => ['HE', LocaleDirection::RIGHT_TO_LEFT];
        yield 'surrounding spaces' => [' fa ', LocaleDirection::RIGHT_TO_LEFT];

        // Bare "ku" is Kurmanji, written in the Latin alphabet, and CLDR classes it
        // left to right; the right-to-left Kurdish is Sorani, which is "ckb" above.
        yield 'Kurdish' => ['ku', LocaleDirection::LEFT_TO_RIGHT];
        yield 'Kurdish as spoken in Turkey' => ['ku_TR', LocaleDirection::LEFT_TO_RIGHT];
        yield 'Central Kurdish as spoken in Iraq' => ['ckb_IQ', LocaleDirection::RIGHT_TO_LEFT];

        yield 'French' => ['fr', LocaleDirection::LEFT_TO_RIGHT];
        yield 'French as spoken in France' => ['fr_FR', LocaleDirection::LEFT_TO_RIGHT];
        yield 'English' => ['en_US', LocaleDirection::LEFT_TO_RIGHT];
        yield 'a language whose code starts like a right to left one' => ['arn', LocaleDirection::LEFT_TO_RIGHT];
        yield 'a region that looks like a right to left code' => ['en_AR', LocaleDirection::LEFT_TO_RIGHT];
        yield 'unknown code' => ['zz', LocaleDirection::LEFT_TO_RIGHT];
        yield 'nonsense' => ['not a locale', LocaleDirection::LEFT_TO_RIGHT];
        yield 'empty string' => ['', LocaleDirection::LEFT_TO_RIGHT];
        yield 'a lone separator' => ['_', LocaleDirection::LEFT_TO_RIGHT];
        yield 'null' => [null, LocaleDirection::LEFT_TO_RIGHT];
    }
}
