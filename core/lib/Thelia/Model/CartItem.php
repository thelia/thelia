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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartItemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorResolverTrait;
use Thelia\Model\Base\CartItem as BaseCartItem;
use Thelia\Model\Map\CartItemTableMap;

class CartItem extends BaseCartItem
{
    use TaxCalculatorResolverTrait;

    protected ?EventDispatcherInterface $dispatcher = null;

    /**
     * The components a customer chose for a bundle line; null on a line that is not a bundle.
     *
     * @throws InvalidBundleCompositionException when the stored JSON is not a composition
     */
    public function getBundleCompositionValue(): ?BundleComposition
    {
        $json = $this->getBundleComposition();

        return null === $json || '' === $json ? null : BundleComposition::fromJson($json);
    }

    /**
     * The composition as a list the API reads and writes: `[{"bundleItemId": 12, "quantity": "2"}]`,
     * null on a line that is not a bundle.
     *
     * @return list<array{bundleItemId: int, quantity: string}>|null
     */
    public function getBundleCompositionLines(): ?array
    {
        return $this->getBundleCompositionValue()?->toArray();
    }

    /**
     * @param list<array{bundleItemId: int|string, quantity: int|float|string}>|null $lines
     *
     * @throws InvalidBundleCompositionException when the lines are not a composition
     */
    public function setBundleCompositionLines(?array $lines): static
    {
        $this->setBundleComposition(null === $lines ? null : BundleComposition::fromArray($lines)->toCanonicalJson());

        return $this;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * A line is a bundle line when it holds a composition, and a product that is a bundle is only
     * sold with one: a line that says otherwise, whoever writes it (the cart, the API, a module),
     * would be priced, stocked and ordered as something it is not. A line a promotion offers is
     * placed by the promotion, which has no composition to choose.
     *
     * @throws InvalidBundleCompositionException
     */
    protected function assertCompositionMatchesTheProduct(): void
    {
        if (1 === (int) $this->getIsOffered() || null === $this->getProductId()) {
            return;
        }

        $hasComposition = null !== $this->getBundleComposition();

        // A shop without a bundle needs no read of the product: none can be one. A line that
        // carries a composition is read anyway, the flag may lag behind the product it describes.
        $isBundle = ($hasComposition || (new BundleCatalogState())->hasBundles())
            && 1 === (int) ProductQuery::create()->findPk($this->getProductId())?->getIsBundle();

        if ($isBundle && !$hasComposition) {
            throw InvalidBundleCompositionException::malformed(\sprintf('product %d is a bundle and a cart line for it needs a composition', $this->getProductId()));
        }

        if (!$isBundle && $hasComposition) {
            throw InvalidBundleCompositionException::notABundle((int) $this->getProductId());
        }
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->assertCompositionMatchesTheProduct();

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch($cartItemEvent, TheliaEvents::CART_ITEM_CREATE_BEFORE);
        }

        return true;
    }

    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        parent::preUpdate($con);

        // A line moved to another product or sale element, or given another composition (or none),
        // must fit its product as a new line does.
        if ($this->isColumnModified(CartItemTableMap::COL_PRODUCT_ID)
            || $this->isColumnModified(CartItemTableMap::COL_PRODUCT_SALE_ELEMENTS_ID)
            || $this->isColumnModified(CartItemTableMap::COL_BUNDLE_COMPOSITION)) {
            $this->assertCompositionMatchesTheProduct();
        }

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch($cartItemEvent, TheliaEvents::CART_ITEM_UPDATE_BEFORE);
        }

        return true;
    }

    /**
     * @throws PropelException
     */
    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch($cartEvent, TheliaEvents::AFTER_CARTADDITEM);
        }
    }

    /**
     * @throws PropelException
     */
    public function postUpdate(?ConnectionInterface $con = null): void
    {
        parent::postUpdate($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch($cartEvent, TheliaEvents::AFTER_CARTUPDATEITEM);
        }
    }

    /**
     * @param float|null $availableQuantity the stock to check against when it is not the
     *                                      national counter of the sale element (the stock
     *                                      of the sale unit the visitor buys from). A bundle line
     *                                      is supplied by its components, so the caller says how
     *                                      many it can supply (CartLineAvailability): the stock of
     *                                      the sale element standing for the bundle is never read,
     *                                      and without an answer nothing is checked
     *
     * @return $this
     *
     * @throws PropelException
     * @throws NotEnoughStockException
     */
    public function updateQuantity($value, ?float $availableQuantity = null)
    {
        $currentQuantity = $this->getQuantity();

        if ($value <= 0) {
            $value = $currentQuantity;
        }

        if (ConfigQuery::checkAvailableStock()) {
            $productSaleElements = $this->getProductSaleElements();
            $product = $productSaleElements->getProduct();
            $available = null === $this->getBundleComposition()
                ? ($availableQuantity ?? $productSaleElements->getQuantity())
                : ($availableQuantity ?? \INF);

            if (0 === $product->getVirtual() && $available < $value) {
                $this->setQuantity($currentQuantity);
                throw new NotEnoughStockException(Translator::getInstance()->trans('Not enough stock for product '.$product->getRef()));
            }
        }

        $this->setQuantity($value);

        return $this;
    }

    /**
     * @param float|null $availableQuantity the stock to check against when it is not the
     *                                      national counter of the sale element (the stock
     *                                      of the sale unit the visitor buys from)
     *
     * @return $this
     *
     * @throws PropelException
     */
    public function addQuantity($value, ?float $availableQuantity = null)
    {
        if ($value <= 0) {
            return $this;
        }

        $currentQuantity = $this->getQuantity();
        $newQuantity = $currentQuantity + $value;

        if (ConfigQuery::checkAvailableStock()) {
            $productSaleElements = $this->getProductSaleElements();
            $product = $productSaleElements->getProduct();
            $available = null === $this->getBundleComposition()
                ? ($availableQuantity ?? $productSaleElements->getQuantity())
                : ($availableQuantity ?? \INF);

            if (0 === $product->getVirtual() && $available < $newQuantity) {
                $newQuantity = $currentQuantity;
            }
        }

        $this->setQuantity($newQuantity);

        return $this;
    }

    public function getRealPrice(): float
    {
        return (float) (1 === (int) $this->getPromo() ? $this->getPromoPrice() : $this->getPrice());
    }

    /**
     * @throws PropelException
     */
    public function getProduct(?ConnectionInterface $con = null, $locale = null): Product
    {
        $product = parent::getProduct($con);
        if (null === $locale) {
            /** @var string $locale */
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $translation = $product->getTranslation($locale);

        if ($translation->isNew() && Lang::REPLACE_BY_DEFAULT_LANGUAGE === ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $product->setLocale($locale);

        return $product;
    }

    /**
     * @throws PropelException
     */
    public function getRealTaxedPrice(Country $country, ?State $state = null, ?TaxCalculatorInterface $cartTaxCalculator = null): float
    {
        return 1 === (int) $this->getPromo() ? $this->getTaxedPromoPrice($country, $state, $cartTaxCalculator) : $this->getTaxedPrice($country, $state, $cartTaxCalculator);
    }

    /**
     * The calculator for this line, which is the calculator for its cart: the
     * VAT a line carries depends on who is buying, not only on what is bought.
     *
     * A line with no cart yet - one being built - falls back to the plain
     * calculator rather than guessing.
     *
     * @throws PropelException
     */
    private function createLineCartTaxCalculator(?TaxCalculatorInterface $cartTaxCalculator): TaxCalculatorInterface
    {
        if ($cartTaxCalculator instanceof TaxCalculatorInterface) {
            return $cartTaxCalculator;
        }

        $cart = $this->getCart();

        if (!$cart instanceof Cart) {
            return $this->createTaxCalculator();
        }

        return $this->createCartTaxCalculator($cart);
    }

    /**
     * @throws PropelException
     */
    public function getTaxedPrice(Country $country, ?State $state = null, ?TaxCalculatorInterface $cartTaxCalculator = null): float
    {
        return $this->createLineCartTaxCalculator($cartTaxCalculator)->load($this->getProduct(), $country, $state)->getTaxedPrice((float) $this->getPrice());
    }

    /**
     * @throws PropelException
     */
    public function getTaxedPromoPrice(Country $country, ?State $state = null, ?TaxCalculatorInterface $cartTaxCalculator = null): float
    {
        return $this->createLineCartTaxCalculator($cartTaxCalculator)->load($this->getProduct(), $country, $state)->getTaxedPrice((float) $this->getPromoPrice());
    }

    /**
     * @throws PropelException
     */
    public function getTotalRealTaxedPrice(Country $country, ?State $state = null, ?TaxCalculatorInterface $cartTaxCalculator = null): float
    {
        return 1 === (int) $this->getPromo() ? $this->getTotalTaxedPromoPrice($country, $state, $cartTaxCalculator) : $this->getTotalTaxedPrice($country, $state, $cartTaxCalculator);
    }

    /**
     * @throws PropelException
     */
    public function getTotalTaxedPrice(Country $country, ?State $state = null, ?TaxCalculatorInterface $cartTaxCalculator = null): float
    {
        return $this->lineTotal($this->getTaxedPrice($country, $state, $cartTaxCalculator));
    }

    /**
     * @throws PropelException
     */
    public function getTotalTaxedPromoPrice(Country $country, ?State $state = null, ?TaxCalculatorInterface $cartTaxCalculator = null)
    {
        return $this->lineTotal($this->getTaxedPromoPrice($country, $state, $cartTaxCalculator));
    }

    public function getTotalPrice(): float
    {
        return $this->lineTotal((float) $this->getPrice());
    }

    public function getTotalPromoPrice(): float
    {
        return $this->lineTotal((float) $this->getPromoPrice());
    }

    public function getTotalRealPrice(): float
    {
        return $this->lineTotal($this->getRealPrice());
    }

    /**
     * Turns a unit price into a line total, rounding where the shop's
     * order_rounding_mode says to round.
     *
     * Order::getTotalAmount() has to reach the same figure from the persisted
     * order lines, so any change here belongs there too.
     */
    private function lineTotal(float $unitPrice): float
    {
        if (ConfigQuery::isRoundingModeRoundingOfSums()) {
            return round($unitPrice * $this->getQuantity(), 2);
        }

        return round($unitPrice, 2) * $this->getQuantity();
    }
}
