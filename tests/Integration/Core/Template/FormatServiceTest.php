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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\NumberFormat;

/**
 * FormatService must render exactly like the legacy Thelia\Tools\* helpers used by the
 * Smarty format plugins, but without requiring an HTTP request (email/PDF are rendered
 * from the CLI). Each test pins the language explicitly so both sides resolve the same
 * decimals/separators/currency and the comparison is deterministic.
 */
final class FormatServiceTest extends IntegrationTestCase
{
    public function testNumberMatchesLegacyNumberFormat(): void
    {
        $lang = $this->defaultLang();

        $legacy = NumberFormat::getInstance($this->request())->format(1234.567, 2);
        $actual = $this->formatService()->number(1234.567, 2, locale: $lang->getLocale());

        self::assertSame($legacy, $actual);
    }

    public function testMoneyByCurrencyMatchesLegacy(): void
    {
        $lang = $this->defaultLang();
        $currency = $this->currencyWithFormat('%n %s');

        $legacy = MoneyFormat::getInstance($this->request())->formatByCurrency(1234.5, null, null, null, $currency->getId());
        $actual = $this->formatService()->money(1234.5, $currency->getId(), $lang->getLocale());

        self::assertSame($legacy, $actual);
        self::assertStringContainsString('€', $actual);
    }

    public function testMoneyWithoutCurrencyFormatFallsBackToPlainNumber(): void
    {
        $lang = $this->defaultLang();
        $currency = $this->currencyWithFormat('');

        $actual = $this->formatService()->money(1234.5, $currency->getId(), $lang->getLocale());

        self::assertSame($this->formatService()->number(1234.5, locale: $lang->getLocale()), $actual);
    }

    public function testDateWithExplicitPhpFormatWithoutLocale(): void
    {
        $date = new \DateTime('2026-01-15 14:30:00');

        self::assertSame('2026-01-15', $this->formatService()->date($date, format: 'Y-m-d'));
    }

    public function testLocalizedDateConvertsPhpFormatToIcu(): void
    {
        $date = new \DateTime('2026-01-15');

        // "j F Y" (php) -> "d MMMM yyyy" (ICU) localized in French.
        $formatted = $this->formatService()->date($date, format: 'j F Y', locale: 'fr_FR');

        self::assertStringContainsString('janvier', $formatted);
        self::assertStringContainsString('2026', $formatted);
    }

    public function testDateUsesLanguageFormatForOutput(): void
    {
        $lang = $this->defaultLang();
        $date = new \DateTime('2026-01-15 14:30:00');

        self::assertSame(
            $date->format((string) $lang->getDateFormat()),
            $this->formatService()->date($date, output: 'date', locale: $lang->getLocale()),
        );
    }

    public function testAddressFormatsOrderAddress(): void
    {
        $factory = $this->createFixtureFactory();
        $orderAddress = $factory->orderAddress(null, null, ['city' => 'Marseille', 'zipcode' => '13001']);

        $formatted = $this->formatService()->address($orderAddress->getId(), 'fr_FR', html: false);

        self::assertStringContainsString('Marseille', $formatted);
        self::assertStringContainsString('13001', $formatted);
    }

    public function testUnknownOrderAddressReturnsEmptyString(): void
    {
        self::assertSame('', $this->formatService()->address(0));
    }

    public function testEmptyNumberReturnsEmptyString(): void
    {
        self::assertSame('', $this->formatService()->number(''));
        self::assertSame('', $this->formatService()->money(''));
    }

    private function formatService(): FormatService
    {
        return $this->getService(FormatService::class);
    }

    private function request(): \Symfony\Component\HttpFoundation\Request
    {
        /** @var RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        return $requestStack->getCurrentRequest();
    }

    private function defaultLang(): Lang
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        self::assertInstanceOf(Lang::class, $lang);

        // Pin the session language so the legacy tools resolve the same decimals/separators.
        $session = $this->request()->getSession();
        \assert($session instanceof Session);
        $session->setLang($lang);

        return $lang;
    }

    private function currencyWithFormat(string $format): Currency
    {
        $currency = new Currency();
        $currency->setCode('TST');
        $currency->setSymbol('€');
        $currency->setRate(1.0);
        $currency->setVisible(1);
        $currency->setByDefault(0);
        $currency->setFormat($format);
        $currency->save($this->getPropelConnection());

        return $currency;
    }
}
