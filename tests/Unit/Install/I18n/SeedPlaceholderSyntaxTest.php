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

namespace Thelia\Tests\Unit\Install\I18n;

use PHPUnit\Framework\TestCase;
use Thelia\Install\I18n\SeedTranslationCatalog;

/**
 * The mail templates are Twig, so the mailer renders a subject read from
 * `message_i18n` through Twig. `setup/insert.sql` was brought to that syntax by
 * hand while `setup/insert.sql.tpl` and `setup/I18n/*.php` kept the Smarty one,
 * which left a language added after the install with a subject the mailer
 * prints verbatim.
 *
 * `generate:sql` cannot run on Thelia 3 — it renders a Smarty template through
 * a `thelia.parser` service core no longer provides — so these tests stand in
 * for regenerating `insert.sql` and comparing.
 */
final class SeedPlaceholderSyntaxTest extends TestCase
{
    private const SMARTY_PLACEHOLDER = '/\{\$[a-z_]+\}|\{config\s+key=/i';

    private static function setupDirectory(): string
    {
        return \dirname(__DIR__, 4).'/setup';
    }

    public function testNoTranslationFileCarriesASmartyPlaceholder(): void
    {
        foreach ($this->getTranslationFiles() as $locale => $file) {
            $catalog = include $file;

            foreach ($catalog as $key => $translation) {
                self::assertDoesNotMatchRegularExpression(
                    self::SMARTY_PLACEHOLDER,
                    $key,
                    \sprintf('%s.php still keys a Smarty placeholder.', $locale),
                );
                self::assertDoesNotMatchRegularExpression(
                    self::SMARTY_PLACEHOLDER,
                    (string) $translation,
                    \sprintf('%s.php still translates to a Smarty placeholder.', $locale),
                );
            }
        }
    }

    public function testNoTranslationResolvesToAPlaceholderNothingReplaces(): void
    {
        foreach ($this->getTranslationFiles() as $locale => $file) {
            foreach (include $file as $key => $translation) {
                // `%store` is a Symfony parameter, and the seed passes no
                // parameters, so it reaches the database as it stands. It may
                // key a translation, never be its result.
                self::assertStringNotContainsString(
                    '%store',
                    (string) $translation,
                    \sprintf('%s.php translates %s to an unreplaced parameter.', $locale, $key),
                );
            }
        }
    }

    public function testTheSeedTemplateAsksForNoSmartyPlaceholder(): void
    {
        $template = file_get_contents(self::setupDirectory().'/insert.sql.tpl');

        self::assertIsString($template);
        self::assertNotSame(0, preg_match_all("/\{intl l='(.*?)' /", $template, $matches));

        foreach ($matches[1] as $key) {
            self::assertDoesNotMatchRegularExpression(self::SMARTY_PLACEHOLDER, $key);
        }
    }

    public function testTheSeededMailSubjectsAreWhatTheTemplateAndTheCatalogsProduce(): void
    {
        $catalog = new SeedTranslationCatalog(self::setupDirectory().'/I18n');
        $templateRows = $this->getTemplateMessageRows();
        $seededRows = $this->getSeededMessageRows();

        self::assertNotEmpty($templateRows);
        self::assertNotEmpty($seededRows);

        foreach ($seededRows as $locale => $rows) {
            foreach ($templateRows as $id => $keys) {
                self::assertArrayHasKey($id, $rows, \sprintf('insert.sql has no message %d in %s.', $id, $locale));

                foreach (['title', 'subject'] as $position => $column) {
                    self::assertSame(
                        $catalog->translate($keys[$position], $locale),
                        $rows[$id][$position],
                        \sprintf('insert.sql message %d in %s has a %s the seed sources do not produce.', $id, $locale, $column),
                    );
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function getTranslationFiles(): array
    {
        $files = [];

        foreach (glob(self::setupDirectory().'/I18n/*.php') ?: [] as $file) {
            $files[basename($file, '.php')] = $file;
        }

        self::assertNotEmpty($files);

        return $files;
    }

    /**
     * The keys `insert.sql.tpl` translates for every locale, per message id.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function getTemplateMessageRows(): array
    {
        $block = $this->getMessageBlock(file_get_contents(self::setupDirectory().'/insert.sql.tpl') ?: '');

        preg_match_all(
            "/\((\d+), '\{\\\$locale\}', \{intl l='(.*?)' locale=\\\$locale\}, \{intl l='(.*?)' locale=\\\$locale\}/",
            $block,
            $matches,
            \PREG_SET_ORDER,
        );

        $rows = [];

        foreach ($matches as $match) {
            $rows[(int) $match[1]] = [$match[2], $match[3]];
        }

        return $rows;
    }

    /**
     * The titles and subjects `insert.sql` ships, per locale and message id.
     *
     * @return array<string, array<int, array{0: ?string, 1: ?string}>>
     */
    private function getSeededMessageRows(): array
    {
        $block = $this->getMessageBlock(file_get_contents(self::setupDirectory().'/insert.sql') ?: '');
        $rows = [];

        foreach (explode("\n", $block) as $line) {
            if (1 !== preg_match('/^\s*\((\d+),(.*)$/', $line, $matches)) {
                continue;
            }

            $values = $this->readRowValues($matches[2]);

            if (\count($values) < 3) {
                continue;
            }

            $rows[(string) $values[0]][(int) $matches[1]] = [$values[1], $values[2]];
        }

        return $rows;
    }

    private function getMessageBlock(string $content): string
    {
        self::assertSame(1, preg_match('/INSERT INTO `message_i18n`.*?\n;/s', $content, $matches));

        return $matches[0];
    }

    /**
     * Reads the `NULL` and single quoted values of one seeded row.
     *
     * @return list<string|null>
     */
    private function readRowValues(string $line): array
    {
        $values = [];
        $length = \strlen($line);

        for ($position = 0; $position < $length; ++$position) {
            if ("'" === $line[$position]) {
                $value = '';

                while (++$position < $length && "'" !== $line[$position]) {
                    // PDO::quote() escapes the quotes of the wording itself.
                    $value .= '\\' === $line[$position] ? $line[++$position] : $line[$position];
                }

                $values[] = $value;

                continue;
            }

            if (0 === substr_compare($line, 'NULL', $position, 4)) {
                $values[] = null;
                $position += 3;
            }
        }

        return $values;
    }
}
