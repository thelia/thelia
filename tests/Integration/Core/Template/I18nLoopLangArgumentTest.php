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

namespace Thelia\Tests\Integration\Core\Template;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The `lang` argument of an i18n loop picks the language its texts are read in, whatever the language of the
 * session: an invoice printed by a French administrator for a German customer names the countries in German.
 */
final class I18nLoopLangArgumentTest extends IntegrationTestCase
{
    public function testTheLangArgumentWinsOverTheLanguageOfTheSession(): void
    {
        [$session, $other] = $this->twoLanguages();
        $countryId = $this->countryNamedIn($session, $other);

        self::assertSame('In '.$session->getLocale(), $this->title($countryId, []), 'no argument: the language of the session');
        self::assertSame('In '.$other->getLocale(), $this->title($countryId, ['lang' => $other->getId()]));
        self::assertSame('In '.$other->getLocale(), $this->title($countryId, ['lang' => $other->getLocale()]), 'a locale names a language too');
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        [$session, $other] = $this->twoLanguages();
        $countryId = $this->countryNamedIn($session, $other);

        $this->expectException(\InvalidArgumentException::class);
        $this->title($countryId, ['lang' => 'xx_XX']);
    }

    /**
     * @return array{0: Lang, 1: Lang} the language of the session and another one
     */
    private function twoLanguages(): array
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()->getSession()->getLang();
        $other = LangQuery::create()->filterById($session->getId(), Criteria::NOT_EQUAL)->findOne();
        self::assertNotNull($other, 'the test shop has a second language');

        return [$session, $other];
    }

    private function countryNamedIn(Lang ...$languages): int
    {
        $country = $this->createFixtureFactory()->country([
            'isocode' => '903',
            'isoalpha2' => 'ZX',
            'isoalpha3' => 'ZXX',
            'shopCountry' => false,
        ]);

        foreach ($languages as $lang) {
            $country->setLocale($lang->getLocale())->setTitle('In '.$lang->getLocale())->save();
        }

        return $country->getId();
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private function title(int $countryId, array $arguments): string
    {
        $rows = [];
        foreach ($this->getService(LoopExecutor::class)->execute('country', ['id' => $countryId, 'visible' => '*'] + $arguments) as $row) {
            $rows[] = $row->getVarVal();
        }

        self::assertCount(1, $rows);

        return (string) $rows[0]['TITLE'];
    }
}
