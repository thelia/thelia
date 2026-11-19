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

namespace Thelia\Domain\Search\Dictionary;

use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Search\Event\SearchSynonymWriteEvent;
use Thelia\Domain\Search\Event\SearchTermRedirectWriteEvent;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\SearchSynonymTableMap;
use Thelia\Model\SearchSynonymQuery;
use Thelia\Model\SearchTermRedirectQuery;

final readonly class SearchDictionaryImporter
{
    public const int MAX_BYTES = 1048576;
    public const int MAX_ROWS = 5000;
    private const string DELIMITER = ';';

    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function importSynonyms(string $path): SearchDictionaryImportReport
    {
        return $this->import($path, 'term', function (Lang $lang, array $row): bool {
            $term = SearchTermNormalizer::normalize($row[1] ?? '');
            $existing = SearchSynonymQuery::create()->filterByLangId($lang->getId())->filterByTerm($term)->findOne();
            $event = (new SearchSynonymWriteEvent())
                ->setSynonym($existing)
                ->setLangId($lang->getId())
                ->setTerm($term)
                ->setSynonyms(explode('|', $row[2] ?? ''))
                ->setBidirectional(self::isYes($row[3] ?? ''))
                ->setActive(null === $existing || 1 === $existing->getActive());

            $this->dispatcher->dispatch($event, null === $existing ? TheliaEvents::SEARCH_SYNONYM_CREATE : TheliaEvents::SEARCH_SYNONYM_UPDATE);

            return null === $existing;
        });
    }

    public function importRedirects(string $path): SearchDictionaryImportReport
    {
        return $this->import($path, 'term', function (Lang $lang, array $row): bool {
            $term = SearchTermNormalizer::normalize($row[1] ?? '');
            $type = strtolower(trim($row[2] ?? ''));
            $target = trim($row[3] ?? '');
            $isUrl = SearchRedirectTargetType::URL === $type;
            $existing = SearchTermRedirectQuery::create()->filterByLangId($lang->getId())->filterByTerm($term)->findOne();
            $event = (new SearchTermRedirectWriteEvent())
                ->setRedirect($existing)
                ->setLangId($lang->getId())
                ->setTerm($term)
                ->setTargetType($type)
                ->setTargetId($isUrl || 1 !== preg_match('/^\d+$/', $target) ? null : (int) $target)
                ->setUrl($isUrl ? $target : null)
                ->setActive(null === $existing || 1 === $existing->getActive())
                ->setCommonTermChecked(false);

            $this->dispatcher->dispatch($event, null === $existing ? TheliaEvents::SEARCH_TERM_REDIRECT_CREATE : TheliaEvents::SEARCH_TERM_REDIRECT_UPDATE);

            return null === $existing;
        });
    }

    /**
     * @param callable(Lang, list<string>): bool $write
     */
    private function import(string $path, string $headerSecondColumn, callable $write): SearchDictionaryImportReport
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size) {
            throw new \InvalidArgumentException(self::translate('The file cannot be read.'));
        }

        if ($size > self::MAX_BYTES) {
            throw new \InvalidArgumentException(self::translate('The file is larger than %max% kB.', ['%max%' => (string) intdiv(self::MAX_BYTES, 1024)]));
        }

        $content = (string) file_get_contents($path);

        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new \InvalidArgumentException(self::translate('The file must be encoded in UTF-8.'));
        }

        $lines = preg_split('/\r\n|\r|\n/', preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content) ?: [];
        $lines = array_filter($lines, static fn (string $line): bool => '' !== trim($line));

        $firstLine = array_key_first($lines);
        $headerLine = null !== $firstLine && self::isHeader($lines[$firstLine], $headerSecondColumn) ? $firstLine : null;

        if (\count($lines) - (null === $headerLine ? 0 : 1) > self::MAX_ROWS) {
            throw new \InvalidArgumentException(self::translate('The file holds more than %max% lines.', ['%max%' => (string) self::MAX_ROWS]));
        }

        $created = 0;
        $updated = 0;
        $refused = [];
        $langs = [];
        $connection = Propel::getWriteConnection(SearchSynonymTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            foreach ($lines as $index => $line) {
                $row = array_map(trim(...), str_getcsv($line, self::DELIMITER, '"', ''));
                $number = $index + 1;

                if ($index === $headerLine) {
                    continue;
                }

                if (!\array_key_exists($row[0], $langs)) {
                    $langs[$row[0]] = LangQuery::create()->findOneByLocale($row[0]);
                }

                $lang = $langs[$row[0]];

                if (null === $lang) {
                    $refused[$number] = self::translate('Unknown language "%locale%".', ['%locale%' => mb_substr($row[0], 0, 10)]);

                    continue;
                }

                try {
                    $write($lang, $row) ? ++$created : ++$updated;
                } catch (\InvalidArgumentException $exception) {
                    $refused[$number] = $exception->getMessage();
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return new SearchDictionaryImportReport($created, $updated, $refused);
    }

    private static function isHeader(string $line, string $secondColumn): bool
    {
        $row = array_map(trim(...), str_getcsv($line, self::DELIMITER, '"', ''));

        return 'locale' === strtolower((string) $row[0]) && $secondColumn === strtolower($row[1] ?? '');
    }

    private static function isYes(string $value): bool
    {
        return \in_array(strtolower(trim($value)), ['1', 'yes', 'oui', 'true'], true);
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function translate(string $message, array $parameters = []): string
    {
        return Translator::getInstance()->trans($message, $parameters, 'core');
    }
}
