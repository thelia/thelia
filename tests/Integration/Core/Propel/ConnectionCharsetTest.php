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

use PHPUnit\Framework\Attributes\Test;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The character set asked for in the DSN has to be the one the connection
 * actually speaks: it replaces the SET NAMES that used to be run on every
 * connection, so nothing else sets it any more.
 */
final class ConnectionCharsetTest extends IntegrationTestCase
{
    #[Test]
    public function theConnectionSpeaksFourByteUnicode(): void
    {
        $variables = $this->getPropelConnection()
            ->query("SHOW VARIABLES LIKE 'character_set_client'")
            ->fetch(\PDO::FETCH_ASSOC);

        self::assertSame('utf8mb4', $variables['Value']);
    }

    #[Test]
    public function aFourByteCharacterSurvivesARoundTrip(): void
    {
        $name = 'charset_probe_'.bin2hex(random_bytes(4));
        $value = 'Caffè 🍰 漢字';

        (new Config())
            ->setName($name)
            ->setValue($value)
            ->save();

        ConfigQuery::resetCache();

        self::assertSame(
            $value,
            ConfigQuery::create()->findOneByName($name)->getValue(),
            'A character outside the Basic Multilingual Plane must not be lost.',
        );
    }
}
