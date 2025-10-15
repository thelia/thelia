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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\Product;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\TaxRuleI18n;

readonly class OrderProductFactory
{
    public function __construct(private TranslationProvider $translationProvider)
    {
    }

    public function createOrderProduct(
        ModelOrder $placedOrder,
        Product $product,
        ProductSaleElements $productSaleElements,
        ProductI18n $productI18n,
        $cartItem,
        VirtualProductContext $virtualContext,
        TaxRuleI18n $taxRuleI18n,
        ConnectionInterface $connection,
    ): OrderProduct {
        $orderProduct = (new OrderProduct())
            ->setOrderId($placedOrder->getId())
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle($productI18n->getTitle())
            ->setChapo($productI18n->getChapo())
            ->setDescription($productI18n->getDescription())
            ->setPostscriptum($productI18n->getPostscriptum())
            ->setVirtual($virtualContext->isVirtual ? 1 : 0)
            ->setVirtualDocument($virtualContext->virtualDocumentPath)
            ->setQuantity($cartItem->getQuantity())
            ->setPrice($cartItem->getPrice())
            ->setPromoPrice($cartItem->getPromoPrice())
            ->setWasNew($productSaleElements->getNewness() ?? 0)
            ->setWasInPromo($cartItem->getPromo() ?? 0)
            ->setWeight($productSaleElements->getWeight())
            ->setTaxRuleTitle($taxRuleI18n->getTitle())
            ->setTaxRuleDescription($taxRuleI18n->getDescription())
            ->setEanCode($productSaleElements->getEanCode())
            ->setCartItemId($cartItem->getId());

        $orderProduct->save($connection);

        return $orderProduct;
    }

    public function persistAttributeCombinations(
        OrderProduct $orderProduct,
        ProductSaleElements $productSaleElements,
        string $locale,
        ConnectionInterface $connection,
    ): void {
        foreach ($productSaleElements->getAttributeCombinations() as $attributeCombination) {
            /** @var Attribute $attribute */
            $attribute = $this->translationProvider->getAttributeTranslation($locale, $attributeCombination->getAttributeId());

            /** @var AttributeAv $attributeAv */
            $attributeAv = $this->translationProvider->getAttributeAvTranslation($locale, $attributeCombination->getAttributeAvId());

            (new OrderProductAttributeCombination())
                ->setOrderProductId($orderProduct->getId())
                ->setAttributeTitle($attribute->getTitle())
                ->setAttributeChapo($attribute->getChapo())
                ->setAttributeDescription($attribute->getDescription())
                ->setAttributePostscriptum($attribute->getPostscriptum())
                ->setAttributeAvTitle($attributeAv->getTitle())
                ->setAttributeAvChapo($attributeAv->getChapo())
                ->setAttributeAvDescription($attributeAv->getDescription())
                ->setAttributeAvPostscriptum($attributeAv->getPostscriptum())
                ->save($connection);
        }
    }
}
