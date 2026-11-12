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
use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Domain\Pricing\PriceVisibility\PriceTierPublisher;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductPrice as ProductPriceModel;
use Thelia\Model\ProductSaleElements;

/**
 * Serves what the caller pays for a sale element on the front read of it: the price of
 * the group they belong to, the catalog price rules and the reserved operations that
 * price it for them, then the discount of their group and their own - the same price the
 * cart charges.
 *
 * None of it is written in the catalog - no `promo` flag, no `promo_price` - because
 * those are what every visitor reads. It is therefore added here, to the caller's own
 * read: the customer is the one the request authenticated as (the token of an API call,
 * the session of the front office calling it in-process), and a visitor reads the public
 * prices. The page was warmed by {@see EffectivePriceCollectionWarmer} when the read is a
 * collection, so this costs nothing per member; an item read resolves its one sale
 * element.
 *
 * It runs after {@see ProductPriceCurrencyListener} has picked the price row of the
 * currency being browsed: the price is resolved in that same currency, so the two have
 * to be the same row. `price` is the price the promotion is shown against, `promoPrice`
 * what is charged while `promo` is true.
 *
 * A price the caller is not shown (the shop or the product shows its prices to identified
 * customers only, or on request) is null in every price of the sale element, taxed ones
 * included, with the mode it follows, `purchasable` false and no tier: a price that is
 * hidden is hidden from everything that carries it, the single read of a price included.
 * `purchasable` is also false for a sale element an exclusive price list does not price.
 *
 * Admin reads are left alone: the back office edits the catalog, which these prices
 * are deliberately not part of.
 */
class EffectivePriceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly VisitorPriceReader $visitorPriceReader,
        private readonly ProductPriceCurrencyListener $productPriceCurrencyListener,
        private readonly ProductTaxedPriceResolver $productTaxedPriceResolver,
        private readonly PriceVisibilityResolver $priceVisibilityResolver,
        private readonly PriceTierPublisher $priceTierPublisher,
    ) {
    }

    public function applyEffectivePrice(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if ($resource instanceof ProductPriceResource) {
            $this->hideTheSinglePriceOfAHiddenProduct($modelToResourceEvent, $resource);

            return;
        }

        if (!$resource instanceof ProductSaleElementsResource) {
            return;
        }

        $saleElementsId = $resource->getId();

        if (null === $saleElementsId || !self::isFrontRead($modelToResourceEvent->getContext())) {
            return;
        }

        $prices = $resource->getProductPrices();

        // The sale element itself, not the model the read started from: the event of a sale
        // element embedded in a product carries the product.
        $model = $resource->getPropelModel();
        $productId = $model instanceof ProductSaleElements ? (int) $model->getProductId() : null;
        $customer = $this->visitorPriceReader->currentCustomer();
        $mode = null === $productId ? PriceDisplayMode::Visible : $this->priceVisibilityResolver->modeOfProductId($productId);

        $resource->setPriceDisplayMode($mode->value);

        if (!$mode->showsPrice($customer)) {
            $resource->setPurchasable(false);
            $resource->setPriceTiers([]);
            // Nothing of the price is told, the promotion neither: a visitor must not learn that one exists.
            $resource->setPromo(false);
            $resource->setDisplayInitialPrice(null);
            $this->hide($prices);

            return;
        }

        $currency = $this->productPriceCurrencyListener->currentCurrency();
        // Serialized without its prices (the line of a cart), it is resolved only while a price list is
        // on: whether an exclusive one lets the caller buy it does not depend on them, and nothing else
        // needs the price. No list, no resolution: the cart is read as it was before the lists.
        $resolvedPrice = [] === $prices && !$this->priceVisibilityResolver->priceListsMayRefuseAPurchase()
            ? null
            : $this->visitorPriceReader->resolve([$saleElementsId], $currency)[$saleElementsId] ?? null;

        if ($resolvedPrice instanceof ResolvedPrice && [] !== $prices) {
            foreach ($prices as $price) {
                if ($price instanceof ProductPriceResource) {
                    $price
                        ->setPrice($resolvedPrice->untaxedPrice)
                        ->setPromoPrice($resolvedPrice->untaxedPromoPrice);
                }
            }

            // The flag the theme reads to strike the catalog price through says what the
            // visitor pays, whatever the catalog says. Whether the strike shows is the call of
            // the catalog while it prices alone (a null the theme reads as its default), and
            // of what priced the sale element otherwise.
            $resource->setPromo($resolvedPrice->isPromo);

            if (ResolvedPrice::SOURCE_BASE !== $resolvedPrice->source) {
                $resource->setDisplayInitialPrice($resolvedPrice->displayInitialPrice);
            }
        }

        // A sale element whose price no list gives and an exclusive list closes is shown, not sold.
        $resource->setPurchasable($mode->purchasable($customer) && (!$resolvedPrice instanceof ResolvedPrice || $resolvedPrice->purchasable));
        $resource->setPriceTiers($this->tiersOf($saleElementsId, $productId, $currency, $customer));

        if ([] !== $prices) {
            $this->publishTaxedPrices($model, $prices);
        }
    }

    /**
     * The read of one price on its own (`/front/product_prices/{sale element}/currencies/{currency}`):
     * the price rows embedded in a sale element are masked with it, after the price of the currency
     * browsed was picked. A row read at the top of its own request is judged here, on the product
     * of its sale element.
     */
    private function hideTheSinglePriceOfAHiddenProduct(ModelToResourceEvent $modelToResourceEvent, ProductPriceResource $resource): void
    {
        $context = $modelToResourceEvent->getContext();

        if (0 !== ($context['__depth'] ?? 0) || !self::isFrontRead($context) || !$this->priceVisibilityResolver->isActive()) {
            return;
        }

        $model = $modelToResourceEvent->getModel();

        if (!$model instanceof ProductPriceModel) {
            return;
        }

        $productId = (int) $model->getProductSaleElements()->getProductId();

        if (0 !== $productId && !$this->priceVisibilityResolver->showsPriceOf($productId, $this->visitorPriceReader->currentCustomer())) {
            $this->hide([$resource]);
        }
    }

    /**
     * @param array<mixed> $prices
     */
    private function hide(array $prices): void
    {
        foreach ($prices as $price) {
            if ($price instanceof ProductPriceResource) {
                $price
                    ->setPrice(null)
                    ->setPromoPrice(null)
                    ->setTaxedPrice(null)
                    ->setTaxedPromoPrice(null);
            }
        }
    }

    /**
     * The tiers of the price list that prices the sale element for the caller, with their taxes,
     * in the shape {@see PriceTierPublisher} publishes them everywhere.
     *
     * @return list<array{minQuantity: int, price: float, promoPrice: float, taxedPrice: float|null, taxedPromoPrice: float|null}>
     */
    private function tiersOf(int $saleElementsId, ?int $productId, Currency $currency, ?Customer $customer): array
    {
        return $this->priceTierPublisher->forProduct($productId, [$saleElementsId], $currency, $customer)[$saleElementsId] ?? [];
    }

    /**
     * The prices with their taxes, read once the price the visitor pays is settled: the card
     * of a product and its page show the same taxed figure whatever priced the sale element.
     * The products of a page were preloaded by {@see EffectivePriceCollectionWarmer}.
     *
     * @param array<mixed> $prices
     */
    private function publishTaxedPrices(?object $model, array $prices): void
    {
        if (!$model instanceof ProductSaleElements) {
            return;
        }

        $productId = (int) $model->getProductId();

        foreach ($prices as $price) {
            if ($price instanceof ProductPriceResource) {
                $price
                    ->setTaxedPrice($this->productTaxedPriceResolver->taxedPrice($productId, $price->getPrice()))
                    ->setTaxedPromoPrice($this->productTaxedPriceResolver->taxedPrice($productId, $price->getPromoPrice()));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['applyEffectivePrice', -10],
            ],
        ];
    }

    /**
     * A front read is one no admin group takes part in - the same rule
     * {@see ProductPriceCurrencyListener} applies, so a module resource embedding
     * sale elements is covered too.
     *
     * @param array<string, mixed> $context
     */
    public static function isFrontRead(array $context): bool
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
