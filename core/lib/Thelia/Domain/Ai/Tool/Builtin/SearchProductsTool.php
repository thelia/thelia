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
        $products = [];

        /** @var Product $product */
        foreach ($query->orderByRef()->limit($limit)->find() as $product) {
            $product->setLocale($context->locale);
            $products[] = [
                'id' => $product->getId(),
                'ref' => $product->getRef(),
                'title' => (string) $product->getTitle(),
                'variants' => $this->variants($product->getId(), $currency?->getId(), $currency?->getCode()),
            ];
        }

        return ['count' => \count($products), 'products' => $products];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variants(int $productId, ?int $currencyId, ?string $currencyCode): array
    {
        $variants = [];

        /** @var ProductSaleElements $pse */
        foreach (ProductSaleElementsQuery::create()->filterByProductId($productId)->orderById()->find() as $pse) {
            $price = null === $currencyId ? null : ProductPriceQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->filterByCurrencyId($currencyId)
                ->findOne();

            $variants[] = [
                'pse_id' => $pse->getId(),
                'ref' => $pse->getRef(),
                'price' => $price?->getPrice(),
                'currency' => $currencyCode,
                'stock' => $pse->getQuantity(),
            ];
        }

        return $variants;
    }
}
