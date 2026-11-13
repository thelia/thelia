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

namespace Thelia\Tests\Integration\Domain\Catalog\Quantity;

use Thelia\Domain\Catalog\Quantity\Twig\QuantityExtension;
use Thelia\Test\IntegrationTestCase;
use Twig\Environment;

/**
 * The e-mail and PDF templates depend on the core only: the core declares `format_quantity` itself,
 * and writes it without a request.
 */
final class QuantityExtensionTest extends IntegrationTestCase
{
    public function testTheCoreDeclaresTheFilter(): void
    {
        $callable = $this->twig()->getFilter('format_quantity')?->getCallable();

        self::assertIsArray($callable);
        self::assertInstanceOf(QuantityExtension::class, $callable[0]);
    }

    public function testAMeasureIsWrittenWithItsUnitInTheLanguageGiven(): void
    {
        self::assertSame("4,25\u{a0}kg", $this->render("{{ 4.25|format_quantity(2, 'kg', 'fr_FR') }}"));
        self::assertSame('4.25 kg', $this->render("{{ 4.25|format_quantity(2, 'kg', 'en_US') }}"));
    }

    public function testAQuantitySoldByThePieceIsWrittenAlone(): void
    {
        self::assertSame('6', $this->render('{{ 6|format_quantity(0) }}'));
        self::assertSame('6', $this->render("{{ '6'|format_quantity('0', 'pc', 'fr_FR') }}"));
    }

    private function render(string $template): string
    {
        return $this->twig()->createTemplate($template)->render();
    }

    private function twig(): Environment
    {
        return static::getContainer()->get('twig');
    }
}
