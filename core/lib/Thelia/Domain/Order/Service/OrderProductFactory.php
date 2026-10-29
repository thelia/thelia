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
use Thelia\Model\CartItem;
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
        return $this->saveLineOf(
            $this->lineOfCartItem($placedOrder, $product, $productSaleElements, $productI18n, $cartItem, $virtualContext, $taxRuleI18n),
            $connection,
        );
    }

    /**
     * The line of a pack, written exactly like the line of a product: the price, the promo
     * and the quantity are the ones the cart settled, and the tax rule is the one of the
     * bundle product. What differs is the type, and the weight: the pack weighs what its
     * own sale element says when that is above zero, and nothing otherwise, its components
     * carrying their own weight (see createBundleComponentLine()).
     */
    public function createBundleLine(
        ModelOrder $placedOrder,
        Product $product,
        ProductSaleElements $productSaleElements,
        ProductI18n $productI18n,
        CartItem $cartItem,
        VirtualProductContext $virtualContext,
        TaxRuleI18n $taxRuleI18n,
        ConnectionInterface $connection,
    ): OrderProduct {
        return $this->saveLineOf(
            $this->lineOfCartItem($placedOrder, $product, $productSaleElements, $productI18n, $cartItem, $virtualContext, $taxRuleI18n)
                ->setLineType(OrderProduct::LINE_TYPE_BUNDLE)
                ->setWeight(self::bundleWeightOf($productSaleElements)),
            $connection,
        );
    }

    /**
     * One component of a pack: the sale element it takes from the stock, its quantity (the
     * quantity of the component times the quantity of the pack), its title and its
     * reference. It costs nothing and carries no tax, since the line of the pack holds the
     * price, and it names that line as its parent. It weighs what the component weighs,
     * unless the pack has a weight of its own, which already counts it.
     */
    public function createBundleComponentLine(
        ModelOrder $placedOrder,
        OrderProduct $bundleLine,
        Product $product,
        ProductSaleElements $productSaleElements,
        ProductI18n $productI18n,
        float $quantity,
        VirtualProductContext $virtualContext,
        ConnectionInterface $connection,
    ): OrderProduct {
        $weight = (float) $bundleLine->getWeight() > 0
            ? '0'
            : ($productSaleElements->getWeight() !== null ? (string) $productSaleElements->getWeight() : null);

        return $this->saveLineOf(
            $this->lineOf($placedOrder, $product, $productSaleElements, $productI18n, $virtualContext)
                ->setLineType(OrderProduct::LINE_TYPE_BUNDLE_COMPONENT)
                ->setParent($bundleLine->getId())
                ->setQuantity($quantity)
                ->setPrice('0')
                ->setPromoPrice('0')
                ->setWasInPromo(0)
                ->setIsOffered(0)
                ->setWeight($weight),
            $connection,
        );
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

    private function lineOfCartItem(
        ModelOrder $placedOrder,
        Product $product,
        ProductSaleElements $productSaleElements,
        ProductI18n $productI18n,
        CartItem $cartItem,
        VirtualProductContext $virtualContext,
        TaxRuleI18n $taxRuleI18n,
    ): OrderProduct {
        return $this->lineOf($placedOrder, $product, $productSaleElements, $productI18n, $virtualContext)
            ->setQuantity($cartItem->getQuantity())
            ->setPrice($cartItem->getPrice())
            ->setPromoPrice($cartItem->getPromoPrice())
            ->setWasInPromo($cartItem->getPromo() ?? 0)
            ->setTaxRuleTitle($taxRuleI18n->getTitle())
            ->setTaxRuleDescription($taxRuleI18n->getDescription())
            // A line a promotion offered is priced at zero by the cart discount, not by
            // its own price: without this marker the order cannot tell the gift from a
            // purchase once the cart is gone. The generated getter is ?int, so a strict
            // comparison against a boolean would never be true.
            ->setIsOffered((int) ($cartItem->getIsOffered() ?? 0))
            ->setCartItemId($cartItem->getId());
    }

    /**
     * What every kind of line says of the sale element it stands for.
     */
    private function lineOf(
        ModelOrder $placedOrder,
        Product $product,
        ProductSaleElements $productSaleElements,
        ProductI18n $productI18n,
        VirtualProductContext $virtualContext,
    ): OrderProduct {
        return (new OrderProduct())
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
            ->setWasNew($productSaleElements->getNewness() ?? 0)
            ->setWeight($productSaleElements->getWeight() !== null ? (string) $productSaleElements->getWeight() : null)
            ->setEanCode($productSaleElements->getEanCode())
            ->setMpn($productSaleElements->getMpn());
    }

    private function saveLineOf(OrderProduct $orderProduct, ConnectionInterface $connection): OrderProduct
    {
        $orderProduct->save($connection);

        return $orderProduct;
    }

    private static function bundleWeightOf(ProductSaleElements $productSaleElements): string
    {
        $weight = (float) $productSaleElements->getWeight();

        return $weight > 0 ? (string) $productSaleElements->getWeight() : '0';
    }
}
