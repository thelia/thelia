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

namespace Thelia\Domain\Search\Provider;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;
use Thelia\Domain\Search\Contract\SearchScope;
use Thelia\Domain\Search\Document\SearchDocument;

abstract class TranslatedCatalogueDocumentProvider implements SearchableDocumentProviderInterface
{
    private const array WEIGHTS = ['title' => 10, 'chapo' => 5];

    private const array HTML_FIELDS = ['chapo', 'description', 'postscriptum'];

    private const array CODE_FIELDS = ['ref', 'references', 'ean_codes'];

    abstract protected function entityQuery(): ModelCriteria;

    abstract protected function i18nQuery(): ModelCriteria;

    public function scope(): SearchScope
    {
        return SearchScope::Shop;
    }

    public function isTranslatable(): bool
    {
        return true;
    }

    public function fieldWeights(): array
    {
        return self::WEIGHTS;
    }

    public function idsAfter(int $afterId, int $limit): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->entityQuery()
            ->filterById($afterId, Criteria::GREATER_THAN)
            ->orderById()
            ->limit($limit)
            ->select(['Id'])
            ->find()
            ->getData();

        return array_map(intval(...), $ids);
    }

    public function visibleIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<int|string> $visibleIds */
        $visibleIds = $this->entityQuery()
            ->filterById($ids, Criteria::IN)
            ->filterByVisible(1)
            ->select(['Id'])
            ->find()
            ->getData();

        return array_map(intval(...), $visibleIds);
    }

    public function documents(array $ids, ?string $locale): array
    {
        if ([] === $ids || null === $locale) {
            return [];
        }

        $visibility = [];

        /** @var list<array{Id: int|string, Visible: int|string|null}> $rows */
        $rows = $this->entityQuery()->filterById($ids, Criteria::IN)->select(['Id', 'Visible'])->find()->getData();

        foreach ($rows as $row) {
            $visibility[(int) $row['Id']] = 1 === (int) $row['Visible'];
        }

        $documents = [];

        /** @var list<array{Id: int|string, Title: ?string, Chapo: ?string, Description: ?string, Postscriptum: ?string}> $translations */
        $translations = $this->i18nQuery()
            ->filterById(array_keys($visibility), Criteria::IN)
            ->filterByLocale($locale)
            ->select(['Id', 'Title', 'Chapo', 'Description', 'Postscriptum'])
            ->find()
            ->getData();

        foreach ($translations as $translation) {
            $id = (int) $translation['Id'];
            $texts = self::texts([
                'title' => $translation['Title'],
                'chapo' => $translation['Chapo'],
                'description' => $translation['Description'],
                'postscriptum' => $translation['Postscriptum'],
            ]);

            if (!isset($texts['title'])) {
                continue;
            }

            $documents[] = new SearchDocument($this->family(), $id, $locale, $this->scope(), $visibility[$id], $texts, $this->fieldWeights());
        }

        return $documents;
    }

    /**
     * @param array<string, string|null> $values
     *
     * @return array<string, string>
     */
    protected static function texts(array $values): array
    {
        $texts = [];

        foreach ($values as $field => $value) {
            $text = (string) $value;

            if (\in_array($field, self::HTML_FIELDS, true)) {
                $text = html_entity_decode(self::withoutTags((string) preg_replace('/<!--.*?-->/su', ' ', $text)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            }

            if (!\in_array($field, self::CODE_FIELDS, true)) {
                $text = self::withoutTags($text);
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', $text));

            if ('' !== $text) {
                $texts[$field] = $text;
            }
        }

        return $texts;
    }

    private static function withoutTags(string $text): string
    {
        return (string) preg_replace('#</?[a-z][^>]*>#iu', ' ', $text);
    }
}
