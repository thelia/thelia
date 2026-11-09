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
use Thelia\Domain\Search\Document\SearchDocument;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

final class ProductDocumentProvider extends TranslatedCatalogueDocumentProvider
{
    public const string FAMILY = 'product';

    private const array WEIGHTS = [
        'title' => 10,
        'ref' => 5,
        'references' => 5,
        'ean_codes' => 5,
        'chapo' => 5,
        'brand' => 3,
    ];

    public function family(): string
    {
        return self::FAMILY;
    }

    public function fieldWeights(): array
    {
        return self::WEIGHTS;
    }

    public function documents(array $ids, ?string $locale): array
    {
        if ([] === $ids || null === $locale) {
            return [];
        }

        /** @var list<array{Id: int|string, Ref: string, Visible: int|string|null, BrandId: int|string|null}> $products */
        $products = ProductQuery::create()->filterById($ids, Criteria::IN)->select(['Id', 'Ref', 'Visible', 'BrandId'])->find()->getData();

        if ([] === $products) {
            return [];
        }

        $productIds = array_map(static fn (array $product): int => (int) $product['Id'], $products);
        $translations = $this->translations($productIds, $locale);
        $saleElements = $this->saleElements($productIds);
        $brandTitles = $this->brandTitles($products, $locale);

        $documents = [];

        foreach ($products as $product) {
            $id = (int) $product['Id'];
            $translation = $translations[$id] ?? null;

            if (null === $translation) {
                continue;
            }

            $texts = self::texts([
                'title' => $translation['Title'],
                'ref' => $product['Ref'],
                'references' => implode(' ', $saleElements[$id]['references'] ?? []),
                'ean_codes' => implode(' ', $saleElements[$id]['ean_codes'] ?? []),
                'brand' => null === $product['BrandId'] ? null : ($brandTitles[(int) $product['BrandId']] ?? null),
                'chapo' => $translation['Chapo'],
                'description' => $translation['Description'],
                'postscriptum' => $translation['Postscriptum'],
            ]);

            if (!isset($texts['title'])) {
                continue;
            }

            $documents[] = new SearchDocument(self::FAMILY, $id, $locale, $this->scope(), 1 === (int) $product['Visible'], $texts, self::WEIGHTS);
        }

        return $documents;
    }

    protected function entityQuery(): ModelCriteria
    {
        return ProductQuery::create();
    }

    protected function i18nQuery(): ModelCriteria
    {
        return ProductI18nQuery::create();
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, array{Title: ?string, Chapo: ?string, Description: ?string, Postscriptum: ?string}>
     */
    private function translations(array $productIds, string $locale): array
    {
        /** @var list<array{Id: int|string, Title: ?string, Chapo: ?string, Description: ?string, Postscriptum: ?string}> $rows */
        $rows = ProductI18nQuery::create()
            ->filterById($productIds, Criteria::IN)
            ->filterByLocale($locale)
            ->select(['Id', 'Title', 'Chapo', 'Description', 'Postscriptum'])
            ->find()
            ->getData();

        $translations = [];

        foreach ($rows as $row) {
            $translations[(int) $row['Id']] = $row;
        }

        return $translations;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, array{references: list<string>, ean_codes: list<string>}>
     */
    private function saleElements(array $productIds): array
    {
        /** @var list<array{ProductId: int|string, Ref: ?string, EanCode: ?string}> $rows */
        $rows = ProductSaleElementsQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->orderById()
            ->select(['ProductId', 'Ref', 'EanCode'])
            ->find()
            ->getData();

        $saleElements = [];

        foreach ($rows as $row) {
            $productId = (int) $row['ProductId'];
            $saleElements[$productId] ??= ['references' => [], 'ean_codes' => []];

            if (null !== $row['Ref'] && '' !== $row['Ref']) {
                $saleElements[$productId]['references'][] = $row['Ref'];
            }

            if (null !== $row['EanCode'] && '' !== $row['EanCode']) {
                $saleElements[$productId]['ean_codes'][] = $row['EanCode'];
            }
        }

        return $saleElements;
    }

    /**
     * @param list<array{Id: int|string, Ref: string, Visible: int|string|null, BrandId: int|string|null}> $products
     *
     * @return array<int, string>
     */
    private function brandTitles(array $products, string $locale): array
    {
        $brandIds = array_values(array_unique(array_filter(array_map(static fn (array $product): int => (int) $product['BrandId'], $products))));

        if ([] === $brandIds) {
            return [];
        }

        /** @var list<array{Id: int|string, Title: ?string}> $rows */
        $rows = BrandI18nQuery::create()->filterById($brandIds, Criteria::IN)->filterByLocale($locale)->select(['Id', 'Title'])->find()->getData();

        $titles = [];

        foreach ($rows as $row) {
            $titles[(int) $row['Id']] = (string) $row['Title'];
        }

        return $titles;
    }
}
