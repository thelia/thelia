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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Lang\LangCreateEvent;
use Thelia\Core\Event\Lang\LangToggleActiveEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CountryI18n;
use Thelia\Model\CountryI18nQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitleI18nQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\MessageI18nQuery;
use Thelia\Model\StateI18nQuery;
use Thelia\Model\StateQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * `setup/insert.sql` only seeds the locales of the languages the installer
 * creates, so a language added afterwards used to land on a shop where none of
 * the seeded rows had a title in its locale.
 *
 * `pl_PL` ships in `setup/I18n` but is not one of the installed languages,
 * which makes it the case the shop hits.
 */
final class LangSeedI18nTest extends ActionIntegrationTestCase
{
    private const SEEDED_LOCALE = 'en_US';

    private const ADDED_LOCALE = 'pl_PL';

    protected function setUp(): void
    {
        parent::setUp();

        if (null !== LangQuery::create()->findOneByLocale(self::ADDED_LOCALE)) {
            self::markTestSkipped(self::ADDED_LOCALE.' is already installed on this shop.');
        }
    }

    public function testCreatingALanguageSeedsTheShippedTranslations(): void
    {
        self::assertNull(
            CountryI18nQuery::create()->filterByLocale(self::ADDED_LOCALE)->findOne(),
            'The added locale must not have any country title before the language exists.',
        );

        $this->createLang();

        $poland = $this->getCountryIdByEnglishTitle('Poland');

        self::assertSame(
            'Polska',
            CountryI18nQuery::create()->findPk([$poland, self::ADDED_LOCALE])?->getTitle(),
        );

        self::assertSame(
            'Pan',
            CustomerTitleI18nQuery::create()->findPk([1, self::ADDED_LOCALE])?->getShort(),
        );
    }

    public function testKeepsTheFallbackForRowsThatNobodyTranslated(): void
    {
        $this->createLang();

        // `Alabama` has no Polish translation in setup/I18n, `Aceh` has one.
        // Writing a row with a NULL title for Alabama would replace the
        // fallback to the default language by an empty title.
        self::assertNull(
            StateI18nQuery::create()->findPk([$this->getStateIdByEnglishTitle('Alabama'), self::ADDED_LOCALE]),
        );
        self::assertSame(
            'Aceh',
            StateI18nQuery::create()->findPk([$this->getStateIdByEnglishTitle('Aceh'), self::ADDED_LOCALE])?->getTitle(),
        );
    }

    public function testKeepsTheSourceWordingOnAnUntranslatedColumnOfATranslatedRow(): void
    {
        $this->createLang();

        // Only the subject of the order confirmation is translated in Polish.
        // The row is still written, and the title keeps the wording of the
        // source locale rather than leaving the mail without one.
        $addedMessage = MessageI18nQuery::create()->findPk([1, self::ADDED_LOCALE]);
        $seededMessage = MessageI18nQuery::create()->findPk([1, self::SEEDED_LOCALE]);

        self::assertSame(
            'Twoje zamówienie {{ order_ref }} w {{ config("store_name") }}',
            $addedMessage?->getSubject(),
            'A seeded mail subject must reach the added locale with the placeholders the mailer renders.',
        );
        self::assertSame($seededMessage?->getTitle(), $addedMessage?->getTitle());
    }

    public function testSeedingAgainWritesNothingMore(): void
    {
        $lang = $this->createLang();
        $seededRows = $this->countSeededCountryTitles();

        self::assertGreaterThan(0, $seededRows);

        $this->dispatch(new LangToggleActiveEvent($lang->getId()), TheliaEvents::LANG_TOGGLEACTIVE);
        $this->dispatch(new LangToggleActiveEvent($lang->getId()), TheliaEvents::LANG_TOGGLEACTIVE);

        self::assertSame($seededRows, $this->countSeededCountryTitles());
    }

    public function testLeavesAlreadyTranslatedRowsUntouched(): void
    {
        $poland = $this->getCountryIdByEnglishTitle('Poland');

        (new CountryI18n())
            ->setId($poland)
            ->setLocale(self::ADDED_LOCALE)
            ->setTitle('Set by the shop owner')
            ->save();

        $this->createLang();

        self::assertSame(
            'Set by the shop owner',
            CountryI18nQuery::create()->findPk([$poland, self::ADDED_LOCALE])?->getTitle(),
        );
    }

    public function testIgnoresALocaleThatShipsNoTranslationFile(): void
    {
        // The code stays `pl` so the listener that copies a missing flag has
        // nothing to write outside the database.
        $lang = $this->createLang('xx_XX');

        self::assertNotNull($lang->getId());
        self::assertNull(CountryI18nQuery::create()->filterByLocale('xx_XX')->findOne());
    }

    private function createLang(string $locale = self::ADDED_LOCALE): Lang
    {
        $event = new LangCreateEvent();
        $event
            ->setTitle('Polski')
            ->setCode('pl')
            ->setLocale($locale)
            ->setDateFormat('d/m/Y')
            ->setTimeFormat('H:i:s')
            ->setDateTimeFormat('d/m/Y H:i:s')
            ->setDecimalSeparator(',')
            ->setThousandsSeparator(' ')
            ->setDecimals('2');

        $this->dispatch($event, TheliaEvents::LANG_CREATE);

        $lang = $event->getLang();

        self::assertNotNull($lang);

        return $lang;
    }

    private function countSeededCountryTitles(): int
    {
        return CountryI18nQuery::create()->filterByLocale(self::ADDED_LOCALE)->count();
    }

    private function getCountryIdByEnglishTitle(string $title): int
    {
        $country = CountryQuery::create()
            ->useCountryI18nQuery()
                ->filterByLocale(self::SEEDED_LOCALE)
                ->filterByTitle($title)
            ->endUse()
            ->findOne();

        self::assertNotNull($country, \sprintf('No country titled "%s" in %s.', $title, self::SEEDED_LOCALE));

        return $country->getId();
    }

    private function getStateIdByEnglishTitle(string $title): int
    {
        $state = StateQuery::create()
            ->useStateI18nQuery()
                ->filterByLocale(self::SEEDED_LOCALE)
                ->filterByTitle($title)
            ->endUse()
            ->findOne();

        self::assertNotNull($state, \sprintf('No state titled "%s" in %s.', $title, self::SEEDED_LOCALE));

        return $state->getId();
    }
}
