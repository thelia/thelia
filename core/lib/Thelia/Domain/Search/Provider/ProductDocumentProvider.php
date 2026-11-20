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
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Search\Document\SearchDocument;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Map\ProductTableMap;
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

    public function __construct(
        private readonly ?ProductVisibility $productVisibility = null,
    ) {
    }

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
        $featureValues = $this->featureValues($productIds, $locale);
        $attributeValues = $this->attributeValues($saleElements, $locale);

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
                'features' => implode(' ', $featureValues[$id] ?? []),
                'attributes' => implode(' ', $attributeValues[$id] ?? []),
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

    protected function narrowToTheVisitor(ModelCriteria $query): void
    {
        $this->productVisibility?->applyTo($query, ProductTableMap::COL_ID);
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
     * @return array<int, array{ids: list<int>, references: list<string>, ean_codes: list<string>}>
     */
    private function saleElements(array $productIds): array
    {
        /** @var list<array{Id: int|string, ProductId: int|string, Ref: ?string, EanCode: ?string}> $rows */
        $rows = ProductSaleElementsQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->orderById()
            ->select(['Id', 'ProductId', 'Ref', 'EanCode'])
            ->find()
            ->getData();

        $saleElements = [];

        foreach ($rows as $row) {
            $productId = (int) $row['ProductId'];
            $saleElements[$productId] ??= ['ids' => [], 'references' => [], 'ean_codes' => []];
            $saleElements[$productId]['ids'][] = (int) $row['Id'];

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

    /**
     * @param list<int> $productIds
     *
     * @return array<int, list<string>>
     */
    private function featureValues(array $productIds, string $locale): array
    {
        /** @var list<array{ProductId: int|string, FeatureAvId: int|string|null}> $rows */
        $rows = FeatureProductQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->filterByFeatureAvId(null, Criteria::ISNOTNULL)
            ->orderByPosition()
            ->select(['ProductId', 'FeatureAvId'])
            ->find()
            ->getData();

        $valueIdsByProduct = [];

        foreach ($rows as $row) {
            $valueIdsByProduct[(int) $row['ProductId']][] = (int) $row['FeatureAvId'];
        }

        return self::titlesByProduct($valueIdsByProduct, $this->valueTitles(FeatureAvI18nQuery::create(), $valueIdsByProduct, $locale));
    }

    /**
     * @param array<int, array{ids: list<int>, references: list<string>, ean_codes: list<string>}> $saleElements
     *
     * @return array<int, list<string>>
     */
    private function attributeValues(array $saleElements, string $locale): array
    {
        $productOfSaleElements = [];

        foreach ($saleElements as $productId => $productSaleElements) {
            foreach ($productSaleElements['ids'] as $saleElementsId) {
                $productOfSaleElements[$saleElementsId] = $productId;
            }
        }

        if ([] === $productOfSaleElements) {
            return [];
        }

        /** @var list<array{ProductSaleElementsId: int|string, AttributeAvId: int|string}> $rows */
        $rows = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId(array_keys($productOfSaleElements), Criteria::IN)
            ->orderByProductSaleElementsId()
            ->orderByPosition()
            ->select(['ProductSaleElementsId', 'AttributeAvId'])
            ->find()
            ->getData();

        $valueIdsByProduct = [];

        foreach ($rows as $row) {
            $valueIdsByProduct[$productOfSaleElements[(int) $row['ProductSaleElementsId']]][] = (int) $row['AttributeAvId'];
        }

        return self::titlesByProduct($valueIdsByProduct, $this->valueTitles(AttributeAvI18nQuery::create(), $valueIdsByProduct, $locale));
    }

    /**
     * @param array<int, list<int>> $valueIdsByProduct
     *
     * @return array<int, string>
     */
    private function valueTitles(FeatureAvI18nQuery|AttributeAvI18nQuery $i18nQuery, array $valueIdsByProduct, string $locale): array
    {
        $valueIds = array_values(array_unique(array_merge(...array_values($valueIdsByProduct))));

        if ([] === $valueIds) {
            return [];
        }

        /** @var list<array{Id: int|string, Title: ?string}> $rows */
        $rows = $i18nQuery->filterById($valueIds, Criteria::IN)->filterByLocale($locale)->select(['Id', 'Title'])->find()->getData();

        $titles = [];

        foreach ($rows as $row) {
            if (null !== $row['Title'] && '' !== trim($row['Title'])) {
                $titles[(int) $row['Id']] = $row['Title'];
            }
        }

        return $titles;
    }

    /**
     * @param array<int, list<int>> $valueIdsByProduct
     * @param array<int, string>    $titles
     *
     * @return array<int, list<string>>
     */
    private static function titlesByProduct(array $valueIdsByProduct, array $titles): array
    {
        $titlesByProduct = [];

        foreach ($valueIdsByProduct as $productId => $valueIds) {
            $productTitles = [];

            foreach ($valueIds as $valueId) {
                if (isset($titles[$valueId])) {
                    $productTitles[] = $titles[$valueId];
                }
            }

            $titlesByProduct[$productId] = array_values(array_unique($productTitles));
        }

        return $titlesByProduct;
    }
}
