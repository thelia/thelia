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
use Twig\Loader\ArrayLoader;

/**
 * The e-mail and PDF templates depend on the core only: the core declares `format_quantity` itself,
 * and writes it without a request.
 */
final class QuantityExtensionTest extends IntegrationTestCase
{
    public function testTheCoreRegistersTheExtension(): void
    {
        self::assertInstanceOf(QuantityExtension::class, $this->coreExtension());
    }

    public function testAMeasureIsWrittenWithItsUnitInTheLanguageGiven(): void
    {
        self::assertSame('4,25 kg', $this->render("{{ 4.25|format_quantity(2, 'kg', 'fr_FR') }}"));
        self::assertSame('4.25 kg', $this->render("{{ 4.25|format_quantity(2, 'kg', 'en_US') }}"));
    }

    public function testAQuantitySoldByThePieceIsWrittenAlone(): void
    {
        self::assertSame('6', $this->render('{{ 6|format_quantity(0) }}'));
        self::assertSame('6', $this->render("{{ '6'|format_quantity('0', 'pc', 'fr_FR') }}"));
    }

    private function render(string $template): string
    {
        // A theme may declare the same filter, and Twig keeps the last one: render with the core's alone.
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension($this->coreExtension());

        return $twig->createTemplate($template)->render();
    }

    private function coreExtension(): QuantityExtension
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->getExtension(QuantityExtension::class);
    }
}
