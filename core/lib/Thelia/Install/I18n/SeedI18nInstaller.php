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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Map\LangTableMap;

/**
 * Writes the translations shipped in `setup/I18n` into the i18n tables the
 * installer seeds, for a locale that was not part of the installation.
 *
 * `setup/insert.sql` only carries the locales of the languages created at
 * install time. A language added afterwards therefore lands on a shop where
 * every seeded row — countries, states, messages, hooks… — is missing its
 * wording, although that wording ships in `setup/I18n/<locale>.php`.
 *
 * Rows are read back from a locale already present in the table, their values
 * are mapped back to their translation key, and the key is then translated into
 * the target locale. A row is only written when at least one of its columns has
 * a translation, so a row nobody translated keeps falling back to the default
 * language instead of turning into a row full of NULLs. The columns of a
 * written row that have no translation keep the source wording.
 */
class SeedI18nInstaller
{
    /**
     * The i18n tables `setup/insert.sql.tpl` fills per locale, with the columns
     * it translates.
     *
     * @var array<string, list<string>>
     */
    private const SEEDED_TABLES = [
        'config_i18n' => ['title', 'chapo', 'description', 'postscriptum'],
        'module_i18n' => ['title', 'chapo', 'description', 'postscriptum'],
        'hook_i18n' => ['title', 'chapo', 'description'],
        'customer_title_i18n' => ['short', 'long'],
        'currency_i18n' => ['name'],
        'country_i18n' => ['title', 'chapo', 'description', 'postscriptum'],
        'state_i18n' => ['title'],
        'tax_i18n' => ['title', 'description'],
        'tax_rule_i18n' => ['title', 'description'],
        'order_status_i18n' => ['title', 'description', 'chapo', 'postscriptum'],
        'resource_i18n' => ['title', 'chapo', 'description', 'postscriptum'],
        'message_i18n' => ['title', 'subject'],
    ];

    public function __construct(private readonly SeedTranslationCatalog $catalog)
    {
    }

    /**
     * @return int the number of rows written
     */
    public function installLocale(string $locale, ?string $sourceLocale = null): int
    {
        if (!$this->catalog->hasLocale($locale)) {
            return 0;
        }

        $connection = Propel::getConnection(LangTableMap::DATABASE_NAME);
        $writtenRows = 0;

        foreach (self::SEEDED_TABLES as $table => $columns) {
            $writtenRows += $this->installTable($connection, $table, $columns, $locale, $sourceLocale);
        }

        return $writtenRows;
    }

    /**
     * @param list<string> $columns
     */
    private function installTable(
        ConnectionInterface $connection,
        string $table,
        array $columns,
        string $locale,
        ?string $sourceLocale,
    ): int {
        $sourceLocale ??= $this->resolveSourceLocale($connection, $table, $locale);

        if (null === $sourceLocale || $sourceLocale === $locale) {
            return 0;
        }

        $existingIds = $this->getExistingIds($connection, $table, $locale);
        $quotedColumns = array_map(static fn (string $column): string => '`'.$column.'`', $columns);

        $sourceRows = $connection->prepare(
            'SELECT `id`, '.implode(', ', $quotedColumns).' FROM `'.$table.'` WHERE `locale` = :locale',
        );
        $sourceRows->execute(['locale' => $sourceLocale]);

        $insert = $connection->prepare(
            'INSERT INTO `'.$table.'` (`id`, `locale`, '.implode(', ', $quotedColumns).') '
            .'VALUES (:id, :locale, :'.implode(', :', $columns).')',
        );

        $writtenRows = 0;

        foreach ($sourceRows->fetchAll(\PDO::FETCH_ASSOC) as $sourceRow) {
            if (isset($existingIds[(int) $sourceRow['id']])) {
                continue;
            }

            $values = $this->translateRow($sourceRow, $columns, $sourceLocale, $locale);

            if (null === $values) {
                continue;
            }

            $insert->execute(['id' => $sourceRow['id'], 'locale' => $locale] + $values);
            ++$writtenRows;
        }

        return $writtenRows;
    }

    /**
     * @param array<string, string|null> $sourceRow
     * @param list<string>               $columns
     *
     * @return array<string, string|null>|null null when nothing could be translated
     */
    private function translateRow(array $sourceRow, array $columns, string $sourceLocale, string $locale): ?array
    {
        $values = [];
        $hasTranslation = false;

        foreach ($columns as $column) {
            $sourceValue = $sourceRow[$column] ?? null;
            $values[$column] = $sourceValue;

            if (null === $sourceValue || '' === $sourceValue) {
                continue;
            }

            $key = $this->catalog->getKeyForTranslation($sourceValue, $sourceLocale) ?? $sourceValue;
            $translation = $this->catalog->translate($key, $locale);

            if (null === $translation) {
                // The source wording is kept: a mail whose subject column ends
                // up empty is worse than a mail whose subject is not translated
                // yet.
                continue;
            }

            $values[$column] = $translation;
            $hasTranslation = true;
        }

        return $hasTranslation ? $values : null;
    }

    /**
     * Picks the locale the rows are read from.
     *
     * `en_US` comes first because the seed keys are the English strings, so its
     * rows map back to their key without going through a translation table.
     */
    private function resolveSourceLocale(ConnectionInterface $connection, string $table, string $locale): ?string
    {
        $statement = $connection->prepare(
            'SELECT `locale`, COUNT(*) AS `rows_count` FROM `'.$table.'` GROUP BY `locale`',
        );
        $statement->execute();

        $candidates = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $candidate = (string) $row['locale'];

            if ($candidate === $locale || !$this->catalog->hasLocale($candidate)) {
                continue;
            }

            $candidates[$candidate] = (int) $row['rows_count'];
        }

        if (isset($candidates['en_US'])) {
            return 'en_US';
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    /**
     * @return array<int, true>
     */
    private function getExistingIds(ConnectionInterface $connection, string $table, string $locale): array
    {
        $statement = $connection->prepare('SELECT `id` FROM `'.$table.'` WHERE `locale` = :locale');
        $statement->execute(['locale' => $locale]);

        $existingIds = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $existingIds[(int) $id] = true;
        }

        return $existingIds;
    }
}
