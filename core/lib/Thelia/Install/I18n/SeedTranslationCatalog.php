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

namespace Thelia\Install\I18n;

/**
 * Gives access to the translation files shipped in `setup/I18n`.
 *
 * Those files hold the wording the installer writes into the i18n tables: the
 * `generate:sql` command loads them in the `install` domain and resolves every
 * `{intl l='...' locale='...'}` of `setup/insert.sql.tpl` against them. Their
 * keys are the English strings.
 */
class SeedTranslationCatalog
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $catalogs = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $reverseCatalogs = [];

    private readonly string $directory;

    public function __construct(?string $seedTranslationDirectory = null)
    {
        $seedTranslationDirectory ??= \defined('THELIA_SETUP_DIRECTORY')
            ? THELIA_SETUP_DIRECTORY.'I18n'
            : '';

        $this->directory = rtrim($seedTranslationDirectory, \DIRECTORY_SEPARATOR);
    }

    public function hasLocale(string $locale): bool
    {
        return [] !== $this->getCatalog($locale);
    }

    public function translate(string $key, string $locale): ?string
    {
        $translation = $this->getCatalog($locale)[$key] ?? null;

        return '' === $translation ? null : $translation;
    }

    /**
     * Recovers the key a seeded value was produced from.
     *
     * The installer stores translations, not keys, so a row read back from the
     * database only gives the wording of its own locale. `en_US.php` is almost
     * an identity map, but not entirely: `Taïwan` is seeded as `Taiwan`, and
     * `Confirm your %store account` as `Confirm your {config key="store_name"}
     * account`. Going through the reverse index recovers the original key.
     */
    public function getKeyForTranslation(string $translation, string $locale): ?string
    {
        return $this->getReverseCatalog($locale)[$translation] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function getCatalog(string $locale): array
    {
        if (isset($this->catalogs[$locale])) {
            return $this->catalogs[$locale];
        }

        return $this->catalogs[$locale] = $this->loadCatalog($locale);
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalog(string $locale): array
    {
        if ('' === $this->directory || 1 !== preg_match('/^[a-zA-Z0-9_-]+$/', $locale)) {
            return [];
        }

        $file = $this->directory.\DIRECTORY_SEPARATOR.$locale.'.php';

        if (!is_file($file)) {
            return [];
        }

        $catalog = include $file;

        return \is_array($catalog) ? $catalog : [];
    }

    /**
     * @return array<string, string>
     */
    private function getReverseCatalog(string $locale): array
    {
        if (isset($this->reverseCatalogs[$locale])) {
            return $this->reverseCatalogs[$locale];
        }

        $reverseCatalog = [];

        foreach ($this->getCatalog($locale) as $key => $translation) {
            if ('' === $translation) {
                continue;
            }

            // Several keys may share the same translation. An entry that
            // translates to itself is the one the installer used as key, so it
            // wins over any other candidate.
            if (!isset($reverseCatalog[$translation]) || $translation === $key) {
                $reverseCatalog[$translation] = $key;
            }
        }

        return $this->reverseCatalogs[$locale] = $reverseCatalog;
    }
}
