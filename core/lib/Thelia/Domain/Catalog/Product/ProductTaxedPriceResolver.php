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

namespace Thelia\Domain\Catalog\Product;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Country;
use Thelia\Model\Event\AddressEvent;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\State;
use Thelia\Model\TaxRule;

/**
 * What a price of a product is once taxed, for the country the visitor is delivered to: the
 * one reading of it for everything the front publishes, the product cards of the API
 * (`ProductPrice::taxedPrice`), the sale elements of a product page and the product loop's
 * taxation, so that a card and the page it opens print the same figure.
 *
 * A page is read at once: {@see preload()} takes the products of a page in one statement,
 * tax rule included, and the rates of a rule are read once per rule and destination by the
 * tax rule query, so the cost of a listing does not grow with the number of its products.
 * An id nobody preloaded is read on its own, once.
 *
 * The destination is read once and kept, not once per price (it looks at the cart, the customer
 * and the shop each time it is asked), so whatever moves it inside a request forgets it: the
 * delivery address picked, the sign-in or sign-out, an address written or made the default one, a
 * cart opened or emptied. It is forgotten after the writers have run (last priority), and read
 * again by the next price.
 *
 * Deliberately not readonly: the products it holds are the point of this service. Symfony
 * resets it between two requests of a worker.
 */
final class ProductTaxedPriceResolver implements ResetInterface, EventSubscriberInterface
{
    /** @var array<int, Product|null> */
    private array $productById = [];

    private ?Country $country = null;

    private ?State $state = null;

    private bool $destinationRead = false;

    public function __construct(
        private readonly TaxEngine $taxEngine,
        private readonly TaxCalculatorFactoryInterface $taxCalculatorFactory,
    ) {
    }

    /**
     * @param iterable<int|string|null> $productIds
     */
    public function preload(iterable $productIds): void
    {
        $missing = [];

        foreach ($productIds as $productId) {
            $productId = (int) $productId;

            if (0 < $productId && !\array_key_exists($productId, $this->productById)) {
                $missing[$productId] = $productId;
            }
        }

        if ([] === $missing) {
            return;
        }

        // Seeded first, so that a product that no longer exists is not asked for again.
        foreach ($missing as $productId) {
            $this->productById[$productId] = null;
        }

        // The tax rule is what the calculator reads: leaving it unhydrated costs one lazy load
        // per product. LEFT, as `product.tax_rule_id` is nullable and an inner join would drop
        // those products instead of letting them read untaxed.
        $products = ProductQuery::create()
            ->joinWithTaxRule(Criteria::LEFT_JOIN)
            ->filterById(array_values($missing), Criteria::IN)
            ->find();

        foreach ($products as $product) {
            $this->productById[$product->getId()] = $product;
        }
    }

    /**
     * The price with its taxes, as the product loop's TAXED_PRICE reads it. A product with no
     * tax rule is not taxed, and its taxed price is the price itself. Null when there is no
     * price to tax, no product, or the tax engine cannot price it.
     */
    public function taxedPrice(int $productId, ?float $untaxedPrice): ?float
    {
        if (null === $untaxedPrice) {
            return null;
        }

        $product = $this->product($productId);

        if (!$product instanceof Product) {
            return null;
        }

        if (!$product->getTaxRule() instanceof TaxRule) {
            return $untaxedPrice;
        }

        $this->readDestination();

        try {
            return (float) $this->taxCalculatorFactory
                ->createTaxCalculator()
                ->load($product, $this->country, $this->state)
                ->getTaxedPrice($untaxedPrice);
        } catch (TaxEngineException) {
            return null;
        }
    }

    /**
     * Where the visitor is delivered, as what tells two taxed prices apart: the country and the
     * state the tax is calculated for. A key for a cache that serves a taxed price.
     */
    public function destinationKey(): string
    {
        $this->readDestination();

        return \sprintf('%d-%d', $this->country?->getId() ?? 0, $this->state?->getId() ?? 0);
    }

    public static function getSubscribedEvents(): array
    {
        return array_fill_keys([
            TheliaEvents::CART_SET_DELIVERY_ADDRESS,
            TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL,
            TheliaEvents::CART_CREATE_NEW,
            TheliaEvents::CART_RESTORE_CURRENT,
            TheliaEvents::CART_CLEAR,
            TheliaEvents::CUSTOMER_LOGIN,
            TheliaEvents::CUSTOMER_LOGOUT,
            TheliaEvents::CUSTOMER_UPDATEACCOUNT,
            TheliaEvents::ADDRESS_CREATE,
            TheliaEvents::ADDRESS_UPDATE,
            TheliaEvents::ADDRESS_DELETE,
            TheliaEvents::ADDRESS_DEFAULT,
            AddressEvent::POST_SAVE,
            AddressEvent::POST_DELETE,
        ], ['forgetDestination', -255]);
    }

    /**
     * The destination of the next price is read again: where the visitor is delivered moved.
     */
    public function forgetDestination(): void
    {
        $this->country = null;
        $this->state = null;
        $this->destinationRead = false;
    }

    public function reset(): void
    {
        $this->productById = [];
        $this->country = null;
        $this->state = null;
        $this->destinationRead = false;
    }

    private function product(int $productId): ?Product
    {
        if (!\array_key_exists($productId, $this->productById)) {
            $this->preload([$productId]);
        }

        return $this->productById[$productId] ?? null;
    }

    /**
     * The destination the visitor is delivered to is read once for the request, not once per
     * price: it looks at the cart, the customer and the shop each time it is asked.
     */
    private function readDestination(): void
    {
        if ($this->destinationRead) {
            return;
        }

        $this->country = $this->taxEngine->getDeliveryCountry();
        $this->state = $this->taxEngine->getDeliveryState();
        $this->destinationRead = true;
    }
}
