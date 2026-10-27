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
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;

class ProductSaleElementsAccessService
{
    protected ?Request $request;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LangService $langService,
        private readonly ReservedSaleVisibility $reservedSaleVisibility,
        private readonly VisitorPriceReader $visitorPriceReader,
        private readonly ProductTaxedPriceResolver $productTaxedPriceResolver,
    ) {
        $this->request = $requestStack->getMainRequest();
    }

    public function psesByProduct($productId)
    {
        $result = [];

        if (!$productId) {
            return [];
        }

        $currency = $this->currentCurrency();

        // Reached by a product id, so the private drop rule has to be enforced here
        // too: the page of such a product is out of reach for this visitor, and its
        // prices must not be readable behind it.
        $query = ProductSaleElementsQuery::create()
            ->filterByVisible(true)
            ->filterByProductId($productId)
            ->orderByPosition();

        $this->reservedSaleVisibility->applyTo($query, ProductSaleElementsTableMap::COL_PRODUCT_ID);

        $pses = $query->find();
        $productSaleElementsIds = [];

        foreach ($pses as $pse) {
            $productSaleElementsIds[] = (int) $pse->getId();
        }

        // What the visitor pays, for the whole product at once: the price of their group,
        // rules and operations, then their discounts - the price their cart will charge.
        // A sale element the visitor has no price for in this currency is not offered.
        $resolvedPrices = $this->visitorPriceReader->resolve($productSaleElementsIds, $currency, true);

        foreach ($pses as $pse) {
            $resolvedPrice = $resolvedPrices[(int) $pse->getId()] ?? null;

            if (!$resolvedPrice instanceof ResolvedPrice) {
                continue;
            }

            $attributes = [];

            // The virtual columns the price getters read, so that the tax rule of the
            // product is applied to what is charged.
            $pse->setVirtualColumn('price_PRICE', $resolvedPrice->untaxedPrice);
            $pse->setVirtualColumn('price_PROMO_PRICE', $resolvedPrice->untaxedPromoPrice);

            // Left null while the catalog prices alone: the theme then reads its default.
            $displayInitialPrice = ResolvedPrice::SOURCE_BASE === $resolvedPrice->source ? null : $resolvedPrice->displayInitialPrice;

            foreach ($pse->getAttributeCombinations() as $attribute) {
                $attributes[$attribute->getAttributeId()] = $attribute->getAttributeAvId();
            }

            $this->eventDispatcher->dispatch(new PseByProductEvent($pse));

            $result[] = [
                'id' => $pse->getId(),
                'isDefault' => $pse->isDefault(),
                'isPromo' => $resolvedPrice->isPromo,
                'displayInitialPrice' => $displayInitialPrice,
                'isNew' => $pse->getNewness() ? true : false,
                'ref' => $pse->getRef(),
                'ean' => $pse->getEanCode(),
                'mpn' => $pse->getMpn(),
                'quantity' => $pse->getQuantity(),
                'weight' => $pse->getWeight(),
                // Taxed by the service the product cards of the API are, so that a card and
                // the page it opens print the same figure.
                'price' => $this->productTaxedPriceResolver->taxedPrice((int) $productId, $pse->getPrice()),
                'untaxedPrice' => $pse->getPrice(),
                'promoPrice' => $this->productTaxedPriceResolver->taxedPrice((int) $productId, $pse->getPromoPrice()),
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

    /**
     * The currency being browsed, which a command line has none of.
     *
     * The request is read from the stack rather than from the one the
     * constructor captured: this service is built before the request it is
     * asked about exists, and holds a null one ever after.
     */
    private function currentCurrency(): Currency
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();
        $session = $request?->hasSession() ? $request->getSession() : null;

        return $session instanceof Session ? $session->getCurrency() : Currency::getDefaultCurrency();
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
