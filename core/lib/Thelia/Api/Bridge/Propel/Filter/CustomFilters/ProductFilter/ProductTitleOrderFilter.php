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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Model\Lang;

/**
 * Sorts the product collection on the translated title, with "order[title]=asc|desc".
 *
 * The generic OrderFilter cannot do it: the i18n rows form a collection, so a path
 * such as "i18ns.title" would order on an unconstrained join and multiply the rows.
 * This filter therefore lays its own joins, one per locale it reads, each restricted
 * to a single locale so a product still yields exactly one row.
 *
 * The sort key falls back to the title of the shop default language, mirroring what
 * the front already does when a translation is missing. A product with no title at
 * all stays in the collection and is pushed to the end whatever the direction.
 *
 * Pagination stability across pages is the caller's responsibility: add an
 * "order[ref]=asc" tie-breaker, as products sharing a title are otherwise ordered
 * by whatever the database returns.
 */
class ProductTitleOrderFilter extends AbstractFilter
{
    public const ORDER_PROPERTY = 'title';

    private const ORDER_PARAMETER = 'order';
    private const LOCALE_JOIN_ALIAS = 'product_title_order_lang';
    private const FALLBACK_JOIN_ALIAS = 'product_title_order_fallback_lang';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (self::ORDER_PARAMETER !== $property || !\is_array($value)) {
            return;
        }

        $direction = $this->normalizeDirection($value[self::ORDER_PROPERTY] ?? null);

        if (null === $direction) {
            return;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $locale = $this->resolveLocale($context, $defaultLocale);

        $titleColumns = [$this->joinTitles($query, self::LOCALE_JOIN_ALIAS, $locale)];

        if ($locale !== $defaultLocale) {
            $titleColumns[] = $this->joinTitles($query, self::FALLBACK_JOIN_ALIAS, $defaultLocale);
        }

        $sortKey = 1 === \count($titleColumns)
            ? $titleColumns[0]
            : 'COALESCE('.implode(', ', $titleColumns).')';

        // Untranslated products keep a NULL sort key: send them last in both directions.
        $query->addAscendingOrderByColumn('ISNULL('.$sortKey.')');

        if (OrderFilter::DIRECTION_ASC === $direction) {
            $query->addAscendingOrderByColumn($sortKey);

            return;
        }

        $query->addDescendingOrderByColumn($sortKey);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            \sprintf('%s[%s]', self::ORDER_PARAMETER, self::ORDER_PROPERTY) => [
                'property' => self::ORDER_PROPERTY,
                'type' => 'string',
                'required' => false,
                'description' => 'Sort on the product title of the requested locale, falling back to the shop default language. Products without a title come last. Add order[ref] to keep the order stable across pages.',
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilter::DIRECTION_ASC),
                        strtolower(OrderFilter::DIRECTION_DESC),
                    ],
                ],
            ],
        ];
    }

    private function joinTitles(ModelCriteria $query, string $alias, string $locale): string
    {
        $query->joinProductI18n($alias, Criteria::LEFT_JOIN);
        $query->addJoinCondition($alias, $alias.'.locale = ?', $locale, null, \PDO::PARAM_STR);

        return $alias.'.title';
    }

    private function resolveLocale(array $context, string $defaultLocale): string
    {
        $locale = $context['filters']['locale'] ?? $defaultLocale;

        // A locale reaches the join condition, so only an active language is accepted,
        // exactly as AbstractFilter::getLocalizedPropertyQueryPath() does.
        $activeLocales = array_map(static fn (Lang $lang): string => $lang->getLocale(), Lang::getActiveLangs()->getData());

        if (!\is_string($locale) || !\in_array($locale, $activeLocales, true)) {
            throw new InvalidArgumentException(\sprintf('Unknown locale "%s" for sorting on "%s". Active locales: %s', \is_scalar($locale) ? (string) $locale : \gettype($locale), self::ORDER_PROPERTY, implode(', ', $activeLocales)));
        }

        return $locale;
    }

    private function normalizeDirection(mixed $value): ?string
    {
        if (!\is_scalar($value)) {
            return null;
        }

        $direction = strtoupper((string) $value);

        if (!\in_array($direction, [OrderFilter::DIRECTION_ASC, OrderFilter::DIRECTION_DESC], true)) {
            return null;
        }

        return $direction;
    }
}
