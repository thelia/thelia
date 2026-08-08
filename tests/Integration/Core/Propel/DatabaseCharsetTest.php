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

namespace Thelia\Tests\Integration\Core\Propel;

use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * MySQL and MariaDB read "utf8" as utf8mb3, which stores three bytes per
 * character at most. On such a connection every character outside the Basic
 * Multilingual Plane (emoji, historic scripts, some CJK extensions) is
 * rejected with "1366 Incorrect string value", whatever the column charset is.
 */
final class DatabaseCharsetTest extends IntegrationTestCase
{
    private const EMOJI_TITLE = "\u{1F4F7} Appareil photo";

    public function testConnectionCharsetHandlesFourByteCharacters(): void
    {
        $variables = $this->getPropelConnection()
            ->query("SHOW VARIABLES WHERE Variable_name IN ('character_set_client', 'character_set_connection')")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        self::assertSame('utf8mb4', $variables['character_set_client']);
        self::assertSame('utf8mb4', $variables['character_set_connection']);
    }

    public function testTranslatableCoreColumnStoresCharactersOutsideTheBmp(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $product->setLocale('fr_FR')->setTitle(self::EMOJI_TITLE)->save($this->getPropelConnection());

        $reloaded = ProductQuery::create()->findPk($product->getId(), $this->getPropelConnection());

        self::assertNotNull($reloaded);
        self::assertSame(self::EMOJI_TITLE, $reloaded->setLocale('fr_FR')->getTitle());
    }
}
