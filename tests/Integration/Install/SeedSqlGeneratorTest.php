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

use Thelia\Core\Template\TemplateDefinition;
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
     * The subject is the one line of a mail a recipient reads before deciding to open it,
     * and the account confirmation mail is one they have to find again in their mailbox.
     * The seed left it empty in five of its eight locales (#3936): the shop then sent, in
     * silence, a mail with no subject line at all.
     */
    public function testEverySeededMessageHasASubjectInEveryLocale(): void
    {
        $messageNames = $this->getSeededMessageNames();
        $translations = $this->getSeededMessageTranslations();

        // Guard: a translation block the regex above stopped matching would let every
        // assertion below pass on an empty list.
        self::assertNotEmpty($messageNames);
        self::assertCount(
            \count($messageNames) * \count($this->getService(SeedSqlGenerator::class)->getSeededLocales()),
            $translations,
            'The seed does not translate every message into every seeded locale.',
        );

        $withoutSubject = [];

        foreach ($translations as [$id, $locale, $subject]) {
            if ('NULL' === $subject || "''" === $subject) {
                $withoutSubject[] = ($messageNames[$id] ?? (string) $id).' ('.$locale.')';
            }
        }

        self::assertSame(
            [],
            $withoutSubject,
            'Seeded messages a shop would send with an empty subject line: '.implode(', ', $withoutSubject),
        );
    }

    public function testTheSeedCreatesEveryActiveTemplateConfigTheCodeWrites(): void
    {
        $seededConfigNames = $this->getSeededConfigNames();

        foreach (TemplateDefinition::CONFIG_NAMES as $templateType => $configName) {
            self::assertContains(
                $configName,
                $seededConfigNames,
                \sprintf('The %s template is stored under a config variable the seed never creates.', $templateType),
            );
        }
    }

    /**
     * The messages the seed creates, by the id its translations refer to.
     *
     * @return array<int, string>
     */
    private function getSeededMessageNames(): array
    {
        self::assertSame(1, preg_match('/INSERT INTO `message`.*?\n;/s', $this->readSeed(), $block));

        preg_match_all("/^\s*\((\d+), '([^']+)'/m", $block[0], $matches, \PREG_SET_ORDER);

        $names = [];

        foreach ($matches as $match) {
            $names[(int) $match[1]] = $match[2];
        }

        return $names;
    }

    /**
     * The id, the locale and the raw subject column of every seeded message translation,
     * the subject being either `NULL` or a quoted SQL string.
     *
     * @return list<array{int, string, string}>
     */
    private function getSeededMessageTranslations(): array
    {
        self::assertSame(1, preg_match('/INSERT INTO `message_i18n`.*?\n;/s', $this->readSeed(), $block));

        // Title then subject, each a quoted SQL string (apostrophes backslash-escaped by
        // the connection that quoted them) or NULL.
        $column = "(?:NULL|'(?:\\\\.|[^'\\\\])*')";

        preg_match_all(
            "/^\s*\((\d+), '([^']+)', {$column}, ({$column})/m",
            $block[0],
            $matches,
            \PREG_SET_ORDER,
        );

        return array_map(
            static fn (array $match): array => [(int) $match[1], $match[2], $match[3]],
            $matches,
        );
    }

    private function readSeed(): string
    {
        $seed = file_get_contents($this->getService(SeedSqlGenerator::class)->getOutputPath());

        self::assertIsString($seed);

        return $seed;
    }

    /**
     * @return list<string>
     */
    private function getSeededConfigNames(): array
    {
        $seed = file_get_contents($this->getService(SeedSqlGenerator::class)->getOutputPath());

        self::assertIsString($seed);
        self::assertSame(1, preg_match('/INSERT INTO `config`.*?\n;/s', $seed, $block));

        preg_match_all("/^\s*\(\d+, '([^']+)'/m", $block[0], $matches);

        return $matches[1];
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
