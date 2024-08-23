<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Service\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;

readonly class AttributeService
{
    public function __construct(
        private LangService $langService
    ) {
    }

    /**
     * @throws PropelException
     *
     * @return array An array of attributes and their values
     */
    public function getAttributesAndValues(int $productId): array
    {
        $product = ProductQuery::create()->findPk($productId);
        if (null === $product) {
            return [];
        }
        $template = $product->getTemplate();
        if (null === $template) {
            return [];
        }
        $locale = $this->langService->getLocale();
        $attributesAndValues = [];
        foreach ($this->getAttributeInPses($product, $locale) as $attribute) {
            $attributeValues = $this->getAttributeAvInPses($product, $attribute, $locale);
            $values = [];
            /** @var AttributeAv $attributeValue */
            foreach ($attributeValues as $attributeValue) {
                $values[] = [
                    'id' => $attributeValue->getId(),
                    'title' => $attributeValue->getTitle(),
                ];
            }
            $attributesAndValues[] = [
                'id' => $attribute->getId(),
                'title' => $attribute->getTitle(),
                'values' => $values,
            ];
        }

        return $attributesAndValues;
    }

    /**
     * @throws PropelException
     */
    private function getAttributeInPses(Product $product, string $locale): array
    {
        $attributes = [];
        $productSaleElements = $product->getProductSaleElementss();
        /** @var ProductSaleElements $productSaleElement */
        foreach ($productSaleElements as $productSaleElement) {
            $attributeCombinations = $productSaleElement->getAttributeCombinations();
            foreach ($attributeCombinations as $attributeCombination) {
                $attribute = $attributeCombination->getAttribute();
                $attributes[$attribute->getId()] = $attribute->setLocale($locale);
            }
        }

        return $attributes;
    }

    /**
     * @throws PropelException
     */
    private function getAttributeAvInPses(Product $product, Attribute $attribute, string $locale): array
    {
        $attributeAvs = [];
        $productSaleElements = $product->getProductSaleElementss();
        /** @var ProductSaleElements $productSaleElement */
        foreach ($productSaleElements as $productSaleElement) {
            $attributeCombinations = $productSaleElement->getAttributeCombinations();
            foreach ($attributeCombinations as $attributeCombination) {
                if ($attributeCombination->getAttributeId() !== $attribute->getId()) {
                    continue;
                }
                $attributeAvs[] = $attributeCombination->getAttributeAv()->setLocale($locale);
            }
        }

        return $attributeAvs;
    }
}
