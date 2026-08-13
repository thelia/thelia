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

namespace Thelia\Tests\Integration\Install;

use Thelia\Install\Generator\SeedSqlGenerator;
use Thelia\Test\IntegrationTestCase;

/**
 * `setup/insert.sql` is generated from `setup/insert.sql.tpl` and the files of
 * `setup/I18n`. The generator was unrunnable for the whole Thelia 3 line, and
 * the seed drifted from its own template while nothing could tell.
 *
 * Running `php Thelia generate:sql` has to leave the file untouched.
 */
final class SeedSqlGeneratorTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    public function testTheShippedSeedIsWhatTheTemplateProduces(): void
    {
        $generator = $this->getService(SeedSqlGenerator::class);

        self::assertSame(
            file_get_contents($generator->getOutputPath()),
            $generator->generate(),
            'setup/insert.sql is not what setup/insert.sql.tpl and setup/I18n produce. Run: php Thelia generate:sql',
        );
    }

    public function testTheSeededLocalesAreTheLanguagesTheSeedCreates(): void
    {
        $generator = $this->getService(SeedSqlGenerator::class);
        $seededLocales = $generator->getSeededLocales();

        self::assertNotEmpty($seededLocales);
        self::assertSame(array_values(array_unique($seededLocales)), $seededLocales);

        // A locale the seed writes wording for but creates no language for
        // would sit in the database unreachable, and the other way round a
        // language without wording is the gap #3697 closed.
        self::assertSame($seededLocales, $this->getLocalesOfTheSeededMessages());
        self::assertEmpty(array_diff($seededLocales, $generator->getAvailableLocales()));
    }

    /**
     * @return list<string>
     */
    private function getLocalesOfTheSeededMessages(): array
    {
        $seed = file_get_contents($this->getService(SeedSqlGenerator::class)->getOutputPath());

        self::assertIsString($seed);
        self::assertSame(1, preg_match('/INSERT INTO `message_i18n`.*?\n;/s', $seed, $block));

        preg_match_all("/^\s*\(\d+, '([^']+)'/m", $block[0], $matches);

        $locales = array_values(array_unique($matches[1]));
        sort($locales);

        return $locales;
    }
}
