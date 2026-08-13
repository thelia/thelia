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

/**
 * The mail templates are Twig, so the mailer renders a subject read from
 * `message_i18n` through Twig. `setup/insert.sql` was brought to that syntax by
 * hand while `setup/insert.sql.tpl` and `setup/I18n/*.php` kept the Smarty one,
 * which left a language added after the install with a subject the mailer
 * prints verbatim.
 *
 * Whether the seed on disk is what those sources produce is proved by
 * SeedSqlGeneratorTest, which regenerates the whole file; these tests guard the
 * syntax itself, without needing a database.
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
        self::assertNotSame(0, preg_match_all("/\{\{ intl\('(.*?)', locale/", $template, $matches));

        foreach ($matches[1] as $key) {
            self::assertDoesNotMatchRegularExpression(self::SMARTY_PLACEHOLDER, $key);
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
}
