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

namespace Thelia\Domain\Shipping\ProductRule;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductExcludedDeliveryModuleQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;

/**
 * Reads the shipping rules of the products in a cart.
 *
 * The same cart is asked about once per carrier and once per postage computation: the
 * answer is kept for the request, keyed on the lines of the cart so that a line added
 * or removed in between is seen, and forgotten when a rule is written. The cart is
 * given, the session is never read.
 */
final class CartShippingRules implements ResetInterface, EventSubscriberInterface
{
    /** @var array<string, CartShippingRuleSet> */
    private array $known = [];

    public function for(Cart $cart): CartShippingRuleSet
    {
        $lines = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $lines[] = [(int) $cartItem->getId(), (int) $cartItem->getProductId(), (int) $cartItem->getQuantity()];
        }

        if ([] === $lines) {
            return CartShippingRuleSet::none();
        }

        $key = ($cart->getId() ?? spl_object_id($cart)).':'.md5(serialize($lines));

        return $this->known[$key] ??= $this->read($lines);
    }

    /**
     * Titles of the products a refusal names, in the given language, then in the default
     * language when the shop falls back on it for a missing translation, then the
     * reference of the product.
     *
     * @param list<int> $productIds
     *
     * @return array<int, string> product id => title
     */
    public function titlesOf(array $productIds, string $locale): array
    {
        $fallbackLocale = $this->fallbackLocale($locale);
        $locales = null !== $fallbackLocale ? [$locale, $fallbackLocale] : [$locale];

        // Read from the translation table: setLocale() on a shared product instance
        // would leave it in this language for whoever reads it next.
        /** @var array<string, array<int, string>> $titles locale => product id => title */
        $titles = [];

        foreach (ProductI18nQuery::create()
            ->filterById($productIds)
            ->filterByLocale($locales)
            ->select([ProductI18nTableMap::COL_ID, ProductI18nTableMap::COL_LOCALE, ProductI18nTableMap::COL_TITLE])
            ->find() as $row) {
            $titles[(string) $row[ProductI18nTableMap::COL_LOCALE]][(int) $row[ProductI18nTableMap::COL_ID]] = (string) $row[ProductI18nTableMap::COL_TITLE];
        }

        $named = [];

        foreach (ProductQuery::create()->filterById($productIds)->orderById()->select([ProductTableMap::COL_ID, ProductTableMap::COL_REF])->find() as $row) {
            $productId = (int) $row[ProductTableMap::COL_ID];
            $named[$productId] = (string) $row[ProductTableMap::COL_REF];

            foreach ($locales as $titleLocale) {
                $title = $titles[$titleLocale][$productId] ?? '';

                if ('' !== $title) {
                    $named[$productId] = $title;

                    break;
                }
            }
        }

        return $named;
    }

    public function reset(): void
    {
        $this->known = [];
    }

    /**
     * A rule written during the request is read back by whatever comes after it.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_UPDATE => ['reset', -128],
            TheliaEvents::PRODUCT_EXCLUDE_DELIVERY_MODULE => ['reset', -128],
            TheliaEvents::PRODUCT_ALLOW_DELIVERY_MODULE => ['reset', -128],
        ];
    }

    /**
     * The default language, when the shop shows it in place of a missing translation.
     */
    private function fallbackLocale(string $locale): ?string
    {
        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== (int) ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return null;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return $defaultLocale === $locale ? null : $defaultLocale;
    }

    /**
     * @param list<array{int, int, int}> $lines cart item id, product id, quantity
     */
    private function read(array $lines): CartShippingRuleSet
    {
        $productIds = array_values(array_unique(array_column($lines, 1)));

        /** @var array<int, array{virtual: int, surcharge: string, mode: int, free: int}> $products */
        $products = [];

        foreach (ProductQuery::create()
            ->filterById($productIds)
            ->select([ProductTableMap::COL_ID, ProductTableMap::COL_VIRTUAL, ProductTableMap::COL_SHIPPING_SURCHARGE, ProductTableMap::COL_SHIPPING_SURCHARGE_MODE, ProductTableMap::COL_FREE_SHIPPING])
            ->find() as $row) {
            $products[(int) $row[ProductTableMap::COL_ID]] = [
                'virtual' => (int) $row[ProductTableMap::COL_VIRTUAL],
                'surcharge' => (string) $row[ProductTableMap::COL_SHIPPING_SURCHARGE],
                'mode' => (int) $row[ProductTableMap::COL_SHIPPING_SURCHARGE_MODE],
                'free' => (int) $row[ProductTableMap::COL_FREE_SHIPPING],
            ];
        }

        $freeCartItemIds = [];
        $shippedLineCount = 0;
        $untaxedSurcharge = 0.0;

        foreach ($lines as [$cartItemId, $productId, $quantity]) {
            $product = $products[$productId] ?? null;

            // A download does not travel: it is neither offered nor surcharged.
            if (null === $product || 1 === $product['virtual']) {
                continue;
            }

            ++$shippedLineCount;

            // An offered product leaves the postage altogether, its surcharge with it.
            if (1 === $product['free']) {
                $freeCartItemIds[] = $cartItemId;

                continue;
            }

            $surcharge = (float) $product['surcharge'];

            if ($surcharge > 0) {
                $untaxedSurcharge += ShippingSurchargeMode::PerUnit->value === $product['mode'] ? $surcharge * $quantity : $surcharge;
            }
        }

        $productsExcludingModule = [];

        foreach (ProductExcludedDeliveryModuleQuery::create()
            ->filterByProductId($productIds)
            ->orderByProductId()
            ->find() as $exclusion) {
            $productsExcludingModule[(int) $exclusion->getModuleId()][] = (int) $exclusion->getProductId();
        }

        return new CartShippingRuleSet($productsExcludingModule, $freeCartItemIds, $shippedLineCount, $untaxedSurcharge);
    }
}
