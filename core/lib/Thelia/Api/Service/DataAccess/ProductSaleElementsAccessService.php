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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Attribute\AttributeAvProductEvent;
use Thelia\Core\Event\ProductSaleElement\PseByProductEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;

class ProductSaleElementsAccessService
{
    protected ?Request $request;

    public function __construct(
        RequestStack $requestStack,
        private readonly TaxEngine $taxEngine,
        private readonly SecurityContext $securityContext,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LangService $langService,
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
        $locale = $this->langService->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
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
            $attribute = AttributeQuery::create()->findOneById($atributeId);

            $attributes[$atributeId] = [
                'label' => $this->localizedTitle($attribute, $locale),
                'id' => $attribute->getId(),
            ];
        }

        foreach (array_unique($attributeAvailabilitiesId) as $attributeAvId) {
            $attributeAv = AttributeAvQuery::create()->findOneById($attributeAvId);
            $attributes[$attributeAv->getAttributeId()]['values'][] = [
                'id' => $attributeAv->getId(),
                'label' => $this->localizedTitle($attributeAv, $locale),
            ];
        }

        $event = $this->eventDispatcher->dispatch(new AttributeAvProductEvent($attributes));

        return $event->getAttributes();
    }

    /**
     * Falling back to the default language mirrors ResourceService::formatI18ns(): the back office
     * "If a translation is missing or incomplete" setting decides. This data access is exposed on
     * the front only, so the admin exclusion that applies there has no equivalent here.
     */
    private function localizedTitle(ActiveRecordInterface $record, string $locale): string
    {
        $title = $record->setLocale($locale)->getTitle();

        // Explicit emptiness test rather than ?: — "0" is a legitimate attribute value title
        // (a size, for instance) and must not count as missing.
        if (null !== $title && '' !== $title) {
            return $title;
        }

        $fallbackLocale = $this->fallbackLocale($locale);

        if (null !== $fallbackLocale) {
            $title = $record->setLocale($fallbackLocale)->getTitle();
        }

        return $title ?? '';
    }

    private function fallbackLocale(string $currentLocale): ?string
    {
        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== (int) ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return null;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return $defaultLocale === $currentLocale ? null : $defaultLocale;
    }
}
