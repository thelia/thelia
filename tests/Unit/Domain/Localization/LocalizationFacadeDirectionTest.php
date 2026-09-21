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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Thelia\Domain\Localization\LocalizationFacade;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Localization\Service\LocaleDirection;

/**
 * A template asks the facade which way to write, and has to be answered whatever the
 * context it renders in: a console command, a worker or a message consumer has no
 * session, and getLang() answers null there while getLocale() falls back to the default
 * language of the shop.
 */
final class LocalizationFacadeDirectionTest extends TestCase
{
    public function testTheDirectionIsReadFromTheLocaleSoItSurvivesTheAbsenceOfASession(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLang')->willReturn(null);
        $langService->method('getLocale')->willReturn('ar_SA');

        self::assertSame(
            LocaleDirection::RIGHT_TO_LEFT,
            $this->facade($langService)->getCurrentLangDirection(),
        );
    }

    public function testALeftToRightLocaleIsAnsweredWithoutASessionToo(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLang')->willReturn(null);
        $langService->method('getLocale')->willReturn('fr_FR');

        self::assertSame(
            LocaleDirection::LEFT_TO_RIGHT,
            $this->facade($langService)->getCurrentLangDirection(),
        );
    }

    /**
     * A shop with no default language at all makes getLocale() raise. Writing an
     * attribute is not worth taking a page down for, so the direction still answers.
     */
    public function testAFailingLocaleLookupStillAnswersLeftToRight(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willThrowException(new \RuntimeException('No default language is defined.'));

        self::assertSame(
            LocaleDirection::LEFT_TO_RIGHT,
            $this->facade($langService)->getCurrentLangDirection(),
        );
    }

    /**
     * The fallback is deliberate, its cause is not: whatever made the locale
     * unresolvable - no default language, an unreachable database, a broken session -
     * is written down rather than replaced by a left-to-right page nobody can explain.
     */
    public function testAFailingLocaleLookupIsLogged(): void
    {
        $failure = new \RuntimeException('No default language is defined.');

        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willThrowException($failure);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::isString(), ['exception' => $failure]);

        $facade = new LocalizationFacade($langService, new LocaleDirection(), $logger);

        self::assertSame(LocaleDirection::LEFT_TO_RIGHT, $facade->getCurrentLangDirection());
    }

    /**
     * The direction is the only argument this facade gained, and it gained it with a
     * default: code that built the facade with a language service alone still does.
     */
    public function testTheFacadeStillBuildsFromALanguageServiceAlone(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willReturn('he_IL');

        self::assertSame(
            LocaleDirection::RIGHT_TO_LEFT,
            (new LocalizationFacade($langService))->getCurrentLangDirection(),
        );
    }

    public function testANullLocaleAnswersLeftToRight(): void
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willReturn(null);

        self::assertSame(
            LocaleDirection::LEFT_TO_RIGHT,
            $this->facade($langService)->getCurrentLangDirection(),
        );
    }

    private function facade(LangService $langService): LocalizationFacade
    {
        return new LocalizationFacade($langService, new LocaleDirection());
    }
}
