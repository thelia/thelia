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

namespace Thelia\Domain\Webhook;

use Propel\Runtime\Map\TableMap;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class ProductChangeFingerprint
{
    private const array IGNORED_COLUMNS = ['created_at', 'updated_at', 'version', 'version_created_at', 'version_created_by'];

    public function of(int $productId, ?string $locale): string
    {
        $product = ProductQuery::create()->filterById($productId)->findOne();
        $i18n = null === $locale ? null : ProductI18nQuery::create()->filterById($productId)->filterByLocale($locale)->findOne();
        $saleElements = ProductSaleElementsQuery::create()->filterByProductId($productId)->filterByIsDefault(1)->findOne();
        $defaultCategory = ProductCategoryQuery::create()->filterByProductId($productId)->filterByDefaultCategory(true)->findOne();

        return md5(json_encode([
            $this->columns($product),
            $this->columns($i18n),
            $this->columns($saleElements),
            $this->columns($defaultCategory),
        ], \JSON_THROW_ON_ERROR | \JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(?object $model): array
    {
        if (null === $model) {
            return [];
        }

        return array_diff_key($model->toArray(TableMap::TYPE_FIELDNAME), array_flip(self::IGNORED_COLUMNS));
    }
}
