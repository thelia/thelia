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

namespace Thelia\Domain\Ai\Tool\Builtin;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\Tool\AiReadToolInterface;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiToolRisk;
use Thelia\Domain\Ai\Tool\AsAiTool;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Finds products by reference or title, with the variants an agent needs to propose a price.
 */
#[AsAiTool(
    name: 'thelia_search_products',
    description: 'Searches the catalogue by reference or title. Returns at most 20 products with their variants (pse_id), stock and pre-tax prices in the default currency.',
    resource: AdminResources::PRODUCT,
    access: AccessManager::VIEW,
    risks: [AiToolRisk::PrivateData],
)]
final readonly class SearchProductsTool implements AiReadToolInterface
{
    private const int DEFAULT_LIMIT = 5;

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100, 'description' => 'Part of a product reference or title'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'description' => 'How many products to return, 5 by default'],
            ],
        ];
    }

    public function execute(array $arguments, AiToolContext $context): array
    {
        $pattern = '%'.addcslashes((string) $arguments['query'], '%_\\').'%';
        $limit = (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT);

        $byTitle = ProductI18nQuery::create()
            ->filterByLocale($context->locale)
            ->filterByTitle($pattern, Criteria::LIKE)
            ->useProductQuery()
                ->orderByRef()
            ->endUse()
            ->limit($limit)
            ->select('Id')
            ->find()
            ->toArray();

        $query = ProductQuery::create()->filterByRef($pattern, Criteria::LIKE);

        if ([] !== $byTitle) {
            $query->_or()->filterById($byTitle, Criteria::IN);
        }

        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne();
        $found = iterator_to_array($query->orderByRef()->limit($limit)->find(), false);
        $variants = $this->variantsByProduct(
            array_map(static fn (Product $product): int => $product->getId(), $found),
            $currency?->getId(),
            $currency?->getCode(),
        );
        $products = [];

        /** @var Product $product */
        foreach ($found as $product) {
            $product->setLocale($context->locale);
            $products[] = [
                'id' => $product->getId(),
                'ref' => $product->getRef(),
                'title' => (string) $product->getTitle(),
                'variants' => $variants[$product->getId()] ?? [],
            ];
        }

        return ['count' => \count($products), 'products' => $products];
    }

    /**
     * Reads the variants of every product and their prices in two statements, whatever the number of products.
     *
     * @param list<int> $productIds
     *
     * @return array<int, list<array<string, mixed>>> the variants keyed by product id
     */
    private function variantsByProduct(array $productIds, ?int $currencyId, ?string $currencyCode): array
    {
        if ([] === $productIds) {
            return [];
        }

        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->orderById()
            ->find();

        $prices = [];

        if (null !== $currencyId && 0 < $productSaleElements->count()) {
            /** @var ProductPrice $price */
            foreach (ProductPriceQuery::create()
                ->filterByProductSaleElementsId($productSaleElements->getPrimaryKeys(false), Criteria::IN)
                ->filterByCurrencyId($currencyId)
                ->find() as $price) {
                $prices[$price->getProductSaleElementsId()] = $price->getPrice();
            }
        }

        $variants = [];

        /** @var ProductSaleElements $pse */
        foreach ($productSaleElements as $pse) {
            $variants[$pse->getProductId()][] = [
                'pse_id' => $pse->getId(),
                'ref' => $pse->getRef(),
                'price' => $prices[$pse->getId()] ?? null,
                'currency' => $currencyCode,
                'stock' => $pse->getQuantity(),
            ];
        }

        return $variants;
    }
}
