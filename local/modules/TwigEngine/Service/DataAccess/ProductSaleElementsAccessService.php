<?php

namespace TwigEngine\Service\DataAccess;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\TaxEngine\TaxEngine;
use TheliaSmarty\Events\PseByProductEvent;

class ProductSaleElementsAccessService
{
    protected ?Request $request;

    public function __construct(
        RequestStack $requestStack,
        private readonly TaxEngine $taxEngine,
        private readonly SecurityContext $securityContext,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->request = $requestStack->getCurrentRequest();
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

        foreach (ProductSaleElementsQuery::create()->findByProductId($productId) as $pse) {
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
}
