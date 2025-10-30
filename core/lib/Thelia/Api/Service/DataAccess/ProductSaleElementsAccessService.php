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

namespace Thelia\Api\Service\DataAccess;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use TheliaSmarty\Events\PseByProductEvent;

class ProductSaleElementsAccessService
{
    protected ?Request $request;

    public function __construct(
        RequestStack $requestStack,
        private readonly TaxEngine $taxEngine,
        private readonly SecurityContext $securityContext,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->request = $requestStack->getMainRequest();
    }

    public function psesByProduct($productId)
    {
        $result = [];

        if (!$productId) {
            return [];
        }

        $discount = 0;
        $taxCountry = $this->taxEngine->getDeliveryCountry();

        if ($this->securityContext->hasCustomerUser()) {
            $discount = $this->securityContext->getCustomerUser()->getDiscount();
        }

        foreach (ProductSaleElementsQuery::create()->filterByVisible(true)->orderByPosition()->findByProductId($productId) as $pse) {
            $attributes = [];
            $price = ProductPriceQuery::create()->filterByProductSaleElements($pse)->findOne();

            $basePrice = $price->getPrice() * (1 - ($discount / 100));
            $promoPrice = $price->getPromoPrice() * (1 - ($discount / 100));
            $pse->setVirtualColumn('price_PRICE', (float) $basePrice);
            $pse->setVirtualColumn('price_PROMO_PRICE', (float) $promoPrice);

            foreach ($pse->getAttributeCombinations() as $attribute) {
                $attributes[$attribute->getAttributeId()] = $attribute->getAttributeAvId();
            }

            $this->eventDispatcher->dispatch(new PseByProductEvent($pse));

            $result[] = [
                'id' => $pse->getId(),
                'isDefault' => $pse->isDefault(),
                'isPromo' => $pse->getPromo() ? true : false,
                'isNew' => $pse->getNewness() ? true : false,
                'ref' => $pse->getRef(),
                'ean' => $pse->getEanCode(),
                'quantity' => $pse->getQuantity(),
                'weight' => $pse->getWeight(),
                'price' => $pse->getTaxedPrice($taxCountry),
                'untaxedPrice' => $pse->getPrice(),
                'promoPrice' => $pse->getTaxedPromoPrice($taxCountry),
                'promoUntaxedPrice' => $pse->getPromoPrice(),
                'combination' => $attributes,
            ];
        }

        return json_encode($result);
    }

    public function attrAvByProduct($product_id)
    {
        $locale = Lang::getDefaultLanguage()->getLocale();
        $attributes = [];
        $attributesId = [];
        $attributeAvailabilitiesId = [];

        foreach (ProductSaleElementsQuery::create()->findByProductId($product_id) as $pse) {
            foreach ($pse->getAttributeCombinations() as $combination) {
                $attributesId[] = $combination->getAttributeId();
                $attributeAvailabilitiesId[] = $combination->getAttributeAvId();
            }
        }

        foreach (array_unique($attributesId) as $atributeId) {
            $attribute = AttributeQuery::create()->joinWithI18n($locale)->findOneById($atributeId);

            $attributes[$atributeId] = [
                'label' => $attribute->getTitle(),
                'id' => $attribute->getId(),
            ];
        }

        foreach (array_unique($attributeAvailabilitiesId) as $attributeAvId) {
            $attributeAv = AttributeAvQuery::create()->joinWithI18n($locale)->findOneById($attributeAvId);
            $attributes[$attributeAv->getAttributeId()]['values'][] = [
                'id' => $attributeAv->getId(),
                'label' => $attributeAv->getTitle(),
            ];
        }

        return $attributes;
    }
}
