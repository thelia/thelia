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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\ProductPrice as ProductPriceResource;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSalePriceCatalog;

/**
 * Serves the price a reserved operation gives the caller, on the front read of a
 * sale element.
 *
 * A reserved operation writes nothing in the catalog — no `promo` flag, no
 * `promo_price` — because those two are what every visitor reads. Its price is
 * therefore added here, to the caller's own read, and to nobody else's.
 *
 * It runs after {@see ProductPriceCurrencyListener} has picked the price row of
 * the currency being browsed: the reserved price is resolved in that same
 * currency, so the two have to be the same row.
 *
 * Admin reads are left alone: the back office edits the catalog, which a reserved
 * price is deliberately not part of.
 */
class ReservedSalePriceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ReservedSalePriceCatalog $reservedSalePriceCatalog,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
        private readonly ProductPriceCurrencyListener $productPriceCurrencyListener,
    ) {
    }

    public function applyReservedPrice(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ProductSaleElementsResource) {
            return;
        }

        $saleElementsId = $resource->getId();
        $prices = $resource->getProductPrices();

        if (null === $saleElementsId || [] === $prices || !$this->isFrontRead($modelToResourceEvent->getContext())) {
            return;
        }

        $customer = $this->currentCustomerProvider->getCurrentCustomer();

        if (null === $customer) {
            return;
        }

        $currency = $this->productPriceCurrencyListener->currentCurrency();
        $reservedPrice = $this->reservedSalePriceCatalog->priceFor($saleElementsId, $currency, $customer);

        if (null === $reservedPrice) {
            return;
        }

        foreach ($prices as $price) {
            if (!$price instanceof ProductPriceResource) {
                continue;
            }

            $price->setPromoPrice($reservedPrice->untaxedPromoPrice);
        }

        // The reserved price only ever reaches here when it beats the price the sale
        // element was on sale for, so the flag the theme reads to strike the catalog
        // price through is now true whatever the catalog says.
        $resource->setPromo(true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['applyReservedPrice', -10],
            ],
        ];
    }

    /**
     * A front read is one no admin group takes part in — the same rule
     * {@see ProductPriceCurrencyListener} applies, so a module resource embedding
     * sale elements is covered too.
     *
     * @param array<string, mixed> $context
     */
    private function isFrontRead(array $context): bool
    {
        $groups = $context['groups'] ?? [];

        if (!\is_array($groups)) {
            $groups = [$groups];
        }

        $isFrontRead = false;

        foreach ($groups as $group) {
            if (!\is_string($group)) {
                continue;
            }

            if (str_starts_with($group, 'admin:')) {
                return false;
            }

            $isFrontRead = $isFrontRead || str_starts_with($group, 'front:');
        }

        return $isFrontRead;
    }
}
