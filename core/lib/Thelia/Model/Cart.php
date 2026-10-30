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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartDuplicationEvent;
use Thelia\Core\Event\Cart\CartItemDuplicationItem;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleLineContents;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Domain\Taxation\TaxEngine\ExemptTaxCalculator;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorResolverTrait;
use Thelia\Model\Base\Cart as BaseCart;
use Thelia\Model\Map\CartTableMap;

class Cart extends BaseCart
{
    use TaxCalculatorResolverTrait;

    /**
     * A delivery day is a promise of the carrier it was picked for: it falls with that carrier,
     * whichever way the carrier changes — the buyer picking another one, a theme resetting it
     * after an address change, the postage failing and taking it off. Done here rather than
     * in each of those paths so that none of them, nor one written later, can leave a day
     * behind that the new carrier never offered.
     */
    public function preSave(?ConnectionInterface $con = null): bool
    {
        // A day written in the same save as its carrier is the new choice, not a leftover.
        if (!$this->isNew()
            && $this->isColumnModified(CartTableMap::COL_DELIVERY_MODULE_ID)
            && !$this->isColumnModified(CartTableMap::COL_DELIVERY_DATE)) {
            $this->setDeliveryDate(null);
            $this->setDeliverySlotId(null);
        }

        return parent::preSave($con);
    }

    /**
     * The delivery day picked, as Y-m-d: a calendar day of the shop, never an instant.
     */
    public function getDeliveryDay(): ?string
    {
        $day = $this->getDeliveryDate('Y-m-d');

        return \is_string($day) ? $day : null;
    }

    /**
     * Named apart from the column getter so the API reads it without being able to write
     * it: the day and the slot are only set through the checkout, which judges them.
     */
    public function getChosenDeliverySlotId(): ?int
    {
        return null === $this->getDeliverySlotId() ? null : (int) $this->getDeliverySlotId();
    }

    /**
     * Duplicate the current existing cart. Only the token is changed.
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function duplicate(
        ?string $token,
        ?Customer $customer = null,
        ?Currency $currency = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?SaleUnitStockResolver $saleUnitStockResolver = null,
        ?CartLineAvailability $cartLineAvailability = null,
    ): self|bool {
        if (!$dispatcher instanceof EventDispatcherInterface) {
            return false;
        }

        $cartItems = $this->getCartItems();

        $cart = new self();
        $cart->setAddressDeliveryId($this->getAddressDeliveryId());
        $cart->setAddressInvoiceId($this->getAddressInvoiceId());
        $cart->setToken($token);
        // The copy belongs to the sale unit of the cart it copies: moving to another
        // unit is the job of the session, which checks the copy again if needed.
        $cart->setSaleUnitId($this->getSaleUnitId());

        $discount = 0.0;

        if (!$currency instanceof Currency) {
            $currencyQuery = CurrencyQuery::create();
            $currency = $currencyQuery->findPk($this->getCurrencyId()) ?: $currencyQuery->findOneByByDefault(1);
        }

        $cart->setCurrency($currency);

        if ($customer instanceof Customer) {
            $cart->setCustomer($customer);

            if ($customer->getDiscount() > 0) {
                // getDiscount() maps a DECIMAL column and returns a string.
                $discount = (float) $customer->getDiscount();
            }
        }

        $cart->save();

        $saleUnitQuantities = $this->saleUnitQuantitiesOf($cartItems, $saleUnitStockResolver);

        foreach ($cartItems as $cartItem) {
            if (1 === (int) $cartItem->getIsOffered()) {
                // An offered line belongs to the promotion that placed it, not to the
                // customer: copying it would sell the gift as a regular line. The next
                // discount evaluation reconciles the offered lines on the new cart.
                continue;
            }

            $product = $cartItem->getProduct();
            $productSaleElements = $cartItem->getProductSaleElements();

            if ($product
                && $productSaleElements
                && 1 === (int) $product->getVisible()
                && $this->holdsTheShapeOfItsProduct($cartItem, $product)
                && $this->isStillSupplied($cartItem, $productSaleElements, $saleUnitQuantities, $cartLineAvailability)
            ) {
                $item = new CartItem();
                $item->setCart($cart);
                $item->setProductId($cartItem->getProductId());
                $item->setQuantity($cartItem->getQuantity());
                $item->setProductSaleElements($productSaleElements);
                // The composition the customer chose is part of the line.
                $item->setBundleComposition($cartItem->getBundleComposition());
                $prices = $productSaleElements->getPricesByCurrency($currency, $discount);
                $item
                    ->setPrice((string) $prices->getPrice())
                    ->setPromoPrice((string) $prices->getPromoPrice())
                    ->setPromo($productSaleElements->getPromo());

                $item->save();
                $dispatcher->dispatch(new CartItemDuplicationItem($item, $cartItem), TheliaEvents::CART_ITEM_DUPLICATE);
            }
        }

        // Dispatche the duplication event before delting the cart from the database,
        $dispatcher->dispatch(new CartDuplicationEvent($cart, $this), TheliaEvents::CART_DUPLICATED);

        try {
            $this->delete();
        } catch (\Exception) {
            // just fail silently in some cases
        }

        return $cart;
    }

    /**
     * Whether a line is still what its product sells: a bundle line carries a composition and
     * a line of any other product carries none. A product turned into a bundle (or back) under
     * a cart, or a composition that no longer reads, leaves a line CartItem refuses to write:
     * the copy of a cart skips it, it never fails on it.
     */
    private function holdsTheShapeOfItsProduct(CartItem $cartItem, Product $product): bool
    {
        try {
            $composition = $cartItem->getBundleCompositionValue();
        } catch (InvalidBundleCompositionException) {
            return false;
        }

        return (1 === (int) $product->getIsBundle()) === (null !== $composition);
    }

    /**
     * Whether the shop can still supply a line it is asked to copy: the stock of the sale
     * element (of its sale unit when the cart has one), and for a bundle line what its
     * components can supply - the stock of the sale element standing for the bundle is never read.
     *
     * @param array<int, float> $saleUnitQuantities
     */
    private function isStillSupplied(CartItem $cartItem, ProductSaleElements $productSaleElements, array $saleUnitQuantities, ?CartLineAvailability $cartLineAvailability): bool
    {
        if (!ConfigQuery::checkAvailableStock()) {
            return true;
        }

        $composition = $cartItem->getBundleCompositionValue();

        if (null !== $composition) {
            return null === $cartLineAvailability || $cartLineAvailability->forLine($productSaleElements, $composition) >= $cartItem->getQuantity();
        }

        return ($saleUnitQuantities[(int) $productSaleElements->getId()] ?? (float) $productSaleElements->getQuantity()) >= $cartItem->getQuantity()
            || 1 === $productSaleElements->getProduct()->getVirtual();
    }

    /**
     * The stock the cart's sale unit keeps of its own for the lines, in one query;
     * empty for a national cart, without a query. A line absent from the answer is
     * copied against the national counter.
     *
     * @param iterable<CartItem> $cartItems
     *
     * @return array<int, float> keyed by sale element id
     */
    private function saleUnitQuantitiesOf(iterable $cartItems, ?SaleUnitStockResolver $saleUnitStockResolver): array
    {
        if (null === $saleUnitStockResolver || null === $this->getSaleUnitId()) {
            return [];
        }

        $productSaleElementsIds = [];

        foreach ($cartItems as $cartItem) {
            $productSaleElementsIds[] = (int) $cartItem->getProductSaleElementsId();
        }

        return $saleUnitStockResolver->ownQuantities((int) $this->getSaleUnitId(), $productSaleElementsIds);
    }

    /**
     * Retrieve the last item added in the cart.
     */
    public function getLastCartItemAdded(): CartItem
    {
        return CartItemQuery::create()
            ->filterByCartId($this->getId())
            ->orderByCreatedAt(Criteria::DESC)
            ->findOne();
    }

    /**
     * Retrieve the total taxed amount.
     *
     * By default, the total include the discount
     *
     * /!\ The postage amount is not available so it's the total with or without discount an without postage
     *
     * @throws PropelException
     */
    public function getTaxedAmount(
        Country $country,
        bool $withDiscount = true,
        ?State $state = null,
        bool $withPostage = false,
        bool $withGiftWrapping = false,
    ): float {
        $total = 0;
        $cartTaxCalculator = $this->createCartTaxCalculator($this);

        foreach ($this->getCartItems() as $cartItem) {
            $total += $cartItem->getTotalRealTaxedPrice($country, $state, $cartTaxCalculator);
        }

        if ($withDiscount) {
            $total -= $this->getDiscount();

            if ($total < 0) {
                $total = 0;
            }
        }

        if ($withPostage) {
            $total += $this->taxedPostageUnder($cartTaxCalculator);
        }

        // Off by default, and asked for only by the callers that want the grand total the
        // buyer pays. A delivery module reading the cart to decide whether the order has
        // reached free shipping is not one of them: the wrapping is a service, not goods,
        // and paying for one must not carry an order over that threshold.
        if ($withGiftWrapping) {
            $total += $this->getTaxedGiftWrapping($country, $state);
        }

        return round($total, 2);
    }

    /**
     * @throws PropelException
     *
     * @see getTaxedAmount same as this method but the amount is without taxes
     */
    public function getTotalAmount(
        bool $withDiscount = true,
        ?Country $country = null,
        ?State $state = null,
        bool $withPostage = false,
        bool $withGiftWrapping = false,
    ): float {
        $total = 0;

        foreach ($this->getCartItems() as $cartItem) {
            $total += $cartItem->getTotalRealPrice();
        }

        if ($withDiscount) {
            $total -= $this->getCalculatedDiscount(false, $country, $state);

            if ($total < 0) {
                $total = 0;
            }
        }

        if ($withPostage) {
            $total += $this->getUntaxedPostage();
        }

        if ($withGiftWrapping) {
            $total += $this->getUntaxedGiftWrapping();
        }

        return round($total, 2);
    }

    /**
     * Return the VAT of all items.
     *
     * @throws PropelException
     */
    public function getTotalVAT($taxCountry, $taxState = null, $withDiscount = true, $withPostage = false, bool $withGiftWrapping = false): float|int|string
    {
        return $this->getTaxedAmount($taxCountry, $withDiscount, $taxState, $withPostage, $withGiftWrapping)
            - $this->getTotalAmount($withDiscount, $taxCountry, $taxState, $withPostage, $withGiftWrapping);
    }

    /**
     * @throws PropelException
     */
    public function getDiscountVAT($taxCountry, $taxState = null): float
    {
        return $this->getCalculatedDiscount(true, $taxCountry, $taxState) - $this->getCalculatedDiscount(false, $taxCountry, $taxState);
    }

    /**
     * Retrieve the total weight for all products in cart.
     *
     * A bundle weighs what its own sale element says when it says something, and what its
     * retained components weigh otherwise.
     *
     * @throws PropelException
     */
    public function getWeight(): float
    {
        $weight = 0;
        $bundleWeights = $this->bundleLineContents(true);

        foreach ($this->getCartItems() as $cartItem) {
            $itemWeight = $cartItem->getProductSaleElements()->getWeight();

            if ($itemWeight <= 0 && isset($bundleWeights[(int) $cartItem->getId()])) {
                $itemWeight = $bundleWeights[(int) $cartItem->getId()]['weight'];
            }

            $itemWeight *= $cartItem->getQuantity();

            $weight += $itemWeight;
        }

        return $weight;
    }

    /**
     * Tell if the cart contains only virtual products.
     *
     * A bundle is virtual when its own sale element weighs nothing and every component it
     * retained is a virtual product.
     *
     * @throws PropelException
     */
    public function isVirtual(): bool
    {
        $bundleLines = $this->bundleLineContents(false);

        foreach ($this->getCartItems() as $cartItem) {
            if (0 < $cartItem->getProductSaleElements()->getWeight()) {
                return false;
            }

            if (isset($bundleLines[(int) $cartItem->getId()])) {
                if (!$bundleLines[(int) $cartItem->getId()]['virtual'] || 0.0 < $bundleLines[(int) $cartItem->getId()]['weight']) {
                    return false;
                }

                continue;
            }

            $product = $cartItem->getProductSaleElements()->getProduct();

            if (!$product->getVirtual()) {
                return false;
            }
        }

        // An empty cart is not virtual.
        return $this->getCartItems()->count() > 0;
    }

    /**
     * What the bundle lines of the cart are made of, read for all of them at once.
     *
     * @param bool $onlyWeightless only the lines whose own sale element weighs nothing, the ones that weigh what their components weigh
     *
     * @return array<int, array{weight: float, virtual: bool, components: list<array{bundleItemId: int, productSaleElementsId: int, productId: int, quantity: string}>}> by line id
     */
    private function bundleLineContents(bool $onlyWeightless): array
    {
        $lines = [];

        foreach ($this->getCartItems() as $cartItem) {
            $composition = $cartItem->getBundleCompositionValue();

            // A bundle a promotion offers has no composition: it weighs what its default one does.
            if (null === $composition && !(new BundleCatalogState())->hasBundles()) {
                continue;
            }

            // Only a line a promotion offers can be a bundle with no composition (CartItem refuses any
            // other), so the product is read for those alone: a line sold as it is asks nothing.
            if (null === $composition && (1 !== (int) $cartItem->getIsOffered() || 1 !== (int) $cartItem->getProduct()->getIsBundle())) {
                continue;
            }

            if ($onlyWeightless && $cartItem->getProductSaleElements()->getWeight() > 0) {
                continue;
            }

            $lines[(int) $cartItem->getId()] = [(int) $cartItem->getProductId(), $composition];
        }

        return BundleLineContents::of($lines);
    }

    public function setDiscount(?string $discount = null): static
    {
        return parent::setDiscount($discount !== null ? (string) round((float) $discount, 2) : null);
    }

    /**
     * @throws PropelException
     */
    public function getCalculatedDiscount(bool $withTaxes = true, ?Country $country = null, ?State $state = null): float
    {
        if ($withTaxes || !$country instanceof Country) {
            return (float) $this->getDiscount();
        }

        return round($this->createCartTaxCalculator($this)->computeUntaxedCartDiscount($this, $country, $state), 2);
    }

    /**
     * @throws PropelException
     */
    public function isVatExempted(): bool
    {
        return $this->createCartTaxCalculator($this) instanceof ExemptTaxCalculator;
    }

    /**
     * @throws PropelException
     */
    public function getTaxedPostage(): float
    {
        return $this->taxedPostageUnder($this->createCartTaxCalculator($this));
    }

    public function getUntaxedPostage(): float
    {
        return 0 < (float) $this->getPostageTax()
            ? (float) $this->getPostage() - (float) $this->getPostageTax()
            : (float) $this->getPostage();
    }

    /**
     * The price of the gift wrapping the buyer picked, tax excluded, and zero when they
     * picked none or picked one the shop offers.
     *
     * Read off the wrapping row rather than off a column of the cart: nothing the browser
     * sends carries an amount, and a merchant who reprices the service before the order is
     * placed charges the new price. What is frozen is the order, not the cart.
     */
    public function getUntaxedGiftWrapping(): float
    {
        return round((float) $this->getGiftWrapping()?->getPrice(), 2);
    }

    /**
     * The same price with the tax the wrapping's own rule puts on it, for the country the
     * order is delivered to.
     *
     * The same computation GiftWrappingProvider::taxedPrice() makes, written twice on
     * purpose: a Propel model receives nothing by autowiring, so it reaches the tax
     * engine through the dispatcher carried by its connection, the way every other
     * amount on this class does. The service is what a theme and the checkout call.
     *
     * @throws PropelException
     */
    public function getTaxedGiftWrapping(?Country $country = null, ?State $state = null): float
    {
        $giftWrapping = $this->getGiftWrapping();

        if (!$giftWrapping instanceof GiftWrapping) {
            return 0.0;
        }

        $untaxedPrice = (float) $giftWrapping->getPrice();
        $taxRule = $giftWrapping->getTaxRule();

        // No country to tax against — a cart with no delivery address yet — and the service
        // reads at its bare price. The order is what the tax is finally computed on, and it
        // always has an address.
        if (!$country instanceof Country || !$taxRule instanceof TaxRule) {
            return round($untaxedPrice, 2);
        }

        return round(
            $this->createCartTaxCalculator($this)
                ->loadTaxRuleWithoutProduct($taxRule, $country, $state)
                ->getTaxedPrice($untaxedPrice),
            2,
        );
    }

    /**
     * @throws PropelException
     */
    public function getPostageTaxAmount(): float
    {
        return $this->isVatExempted() ? 0.0 : (float) $this->getPostageTax();
    }

    private function taxedPostageUnder(TaxCalculatorInterface $cartTaxCalculator): float
    {
        return $cartTaxCalculator instanceof ExemptTaxCalculator
            ? $this->getUntaxedPostage()
            : (float) $this->getPostage();
    }
}
