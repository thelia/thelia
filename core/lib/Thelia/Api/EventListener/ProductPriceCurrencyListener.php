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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Currency as CurrencyResource;
use Thelia\Api\Resource\ProductPrice as ProductPriceResource;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Prices a sale element in the currency the shop is being browsed in.
 *
 * product_price holds one row per currency, and a front read returned them
 * all, in whatever order the database answered: a consumer reading the first
 * one priced the catalogue in a currency nobody asked for. The product loop
 * never had that problem, it selects the price in SQL — the row of the
 * requested currency, the default currency row converted at the currency rate
 * when that row is missing or flagged from_default_currency. The same rule is
 * applied here, to the rows the transformer has already loaded, so a front
 * read answers a single price: the one the shop charges.
 *
 * Admin reads keep every row: the back office edits one price per currency.
 */
class ProductPriceCurrencyListener implements EventSubscriberInterface
{
    private ?Currency $currentCurrency = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ApiResourcePropelTransformerService $transformerService,
        private readonly CurrencyConverter $currencyConverter,
    ) {
    }

    public function selectPriceOfCurrentCurrency(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ProductSaleElementsResource) {
            return;
        }

        $prices = $resource->getProductPrices();
        $context = $modelToResourceEvent->getContext();

        if ([] === $prices || !EffectivePriceListener::isFrontRead($context)) {
            return;
        }

        $price = $this->priceOfCurrentCurrency($prices, $context);

        if ($price instanceof ProductPriceResource) {
            $resource->setProductPrices([$price]);
        }
    }

    /**
     * The currency lives in the session, which the front sets from its
     * "currency" parameter. An api route is stateless — it never starts a
     * session — so the same parameter is read from the request there.
     */
    public function currentCurrency(): Currency
    {
        if ($this->currentCurrency instanceof Currency) {
            return $this->currentCurrency;
        }

        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();
        $requestedCode = $request?->query->get('currency');

        if (\is_string($requestedCode) && '' !== $requestedCode) {
            $requestedCurrency = CurrencyQuery::create()
                ->filterByVisible(true)
                ->findOneByCode($requestedCode);

            if ($requestedCurrency instanceof Currency) {
                return $this->currentCurrency = $requestedCurrency;
            }
        }

        $session = $request?->hasSession() ? $request->getSession() : null;

        if ($session instanceof Session) {
            return $this->currentCurrency = $session->getCurrency();
        }

        return $this->currentCurrency = Currency::getDefaultCurrency();
    }

    /**
     * The currency is resolved once per request: the listener runs for every
     * sale element of a collection, and reading it again there would buy a
     * query per row.
     */
    public function forgetCurrentCurrency(): void
    {
        $this->currentCurrency = null;
    }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if ($requestEvent->isMainRequest()) {
            $this->forgetCurrentCurrency();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['selectPriceOfCurrentCurrency', 0],
            ],
            KernelEvents::REQUEST => [
                ['onKernelRequest', 4096],
            ],
            ConsoleEvents::COMMAND => [
                ['forgetCurrentCurrency', 4096],
            ],
        ];
    }

    /**
     * @param array<ProductPriceResource> $prices
     * @param array<string, mixed>        $context
     */
    private function priceOfCurrentCurrency(array $prices, array $context): ?ProductPriceResource
    {
        $currency = $this->currentCurrency();
        $defaultCurrency = Currency::getDefaultCurrency();

        $ownPrice = null;
        $defaultCurrencyPrice = null;

        foreach ($prices as $price) {
            if (!$price instanceof ProductPriceResource) {
                continue;
            }

            $priceCurrencyId = $price->getCurrency()->getId();

            if ($priceCurrencyId === $currency->getId()) {
                $ownPrice = $price;
            }

            if ($priceCurrencyId === $defaultCurrency->getId()) {
                $defaultCurrencyPrice = $price;
            }
        }

        // A price of its own is what the shop charges, unless it is flagged as
        // derived from the default currency one — the back office then stores
        // a zero and expects the conversion to be made at read time.
        if ($ownPrice instanceof ProductPriceResource && true !== $ownPrice->getFromDefaultCurrency()) {
            return $ownPrice;
        }

        if (!$defaultCurrencyPrice instanceof ProductPriceResource) {
            return $ownPrice;
        }

        $convertedPrice = $this->currencyConverter->fromDefaultCurrency($defaultCurrencyPrice->getPrice(), $currency);
        $convertedPromoPrice = $this->currencyConverter->fromDefaultCurrency($defaultCurrencyPrice->getPromoPrice(), $currency);

        // A default currency without a usable rate converts nothing: the sale element
        // has no price in this currency.
        if (null === $convertedPrice || null === $convertedPromoPrice) {
            return null;
        }

        // Cloning keeps everything the row of the default currency carries and
        // only re-quotes it: the sale element it belongs to, its dates, the
        // addons a module attached to it.
        $price = $ownPrice ?? clone $defaultCurrencyPrice;
        $price
            ->setPrice($convertedPrice)
            ->setPromoPrice($convertedPromoPrice);

        if (!$ownPrice instanceof ProductPriceResource) {
            $price->setCurrency($this->currencyResource($currency, $context));
        }

        return $price;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function currencyResource(Currency $currency, array $context): CurrencyResource
    {
        /** @var CurrencyResource $currencyResource */
        $currencyResource = $this->transformerService->modelToResource(
            resourceClass: CurrencyResource::class,
            propelModel: $currency,
            context: $context,
        );

        return $currencyResource;
    }
}
