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

use Thelia\Core\Template\Helper\FormatService;
use Thelia\Model\Currency;
use Thelia\Test\IntegrationTestCase;
use Twig\Environment;

/**
 * The TwigEngine FormatExtension is a thin adapter over the core FormatService: rendering
 * a template must yield exactly what the service returns. This also guards the argument
 * mapping (e.g. format_date's second argument is the output, not the php format) and the
 * fact these are Twig functions, so they are not shadowed by Symfony's IntlExtension filters.
 */
final class FormatExtensionTest extends IntegrationTestCase
{
    public function testFormatMoneyFunctionMatchesService(): void
    {
        $currency = $this->currencyWithFormat('%n %s');
        $rendered = $this->render('{{ format_money(1234.5, currencyId) }}', ['currencyId' => $currency->getId()]);

        self::assertSame($this->service()->money(1234.5, $currency->getId()), $rendered);
        self::assertStringContainsString('€', $rendered);
    }

    public function testFormatDateFunctionUsesSecondArgumentAsOutput(): void
    {
        $rendered = $this->render("{{ format_date(date, 'date', null, 'fr_FR') }}", ['date' => new \DateTime('2026-01-15 09:00:00')]);

        self::assertSame($this->service()->date(new \DateTime('2026-01-15 09:00:00'), null, 'date', 'fr_FR'), $rendered);
    }

    public function testFormatNumberFunctionMatchesService(): void
    {
        $rendered = $this->render('{{ format_number(1234.567, 2, locale="fr_FR") }}');

        self::assertSame($this->service()->number(1234.567, 2, locale: 'fr_FR'), $rendered);
    }

    public function testFormatAddressFunctionMatchesService(): void
    {
        $orderAddress = $this->createFixtureFactory()->orderAddress(null, null, ['city' => 'Lyon', 'zipcode' => '69001']);
        $rendered = $this->render('{{ format_address(id, "fr_FR", false) }}', ['id' => $orderAddress->getId()]);

        self::assertSame($this->service()->address($orderAddress->getId(), 'fr_FR', html: false), $rendered);
        self::assertStringContainsString('Lyon', $rendered);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context = []): string
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate($template)->render($context);
    }

    private function service(): FormatService
    {
        return $this->getService(FormatService::class);
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
