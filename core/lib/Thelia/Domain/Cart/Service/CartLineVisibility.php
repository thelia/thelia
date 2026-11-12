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

namespace Thelia\Domain\Cart\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * What the cart may hold for the visitor who owns it: the products the catalog shows them,
 * and that they may buy, and nothing else.
 *
 * A product the catalog shows but whose price the visitor is not shown (the shop or the product
 * shows its prices to identified customers only, or on request), and a sale element an exclusive
 * price list does not price, are held back like a hidden product: "hidden" here means hidden or
 * not for sale to this visitor, and the same refusal answers both.
 *
 * The catalog reads are narrowed by ProductVisibility (the categories a customer group is
 * restricted to, the rules of the modules, the private drops of the reserved operations); a
 * cart is written by paths that never read the catalog (the live components of a theme, a
 * module dispatching CART_ADDITEM, a cart filled by a visitor who then signs in as somebody
 * who sees less). This class asks the same rule of the cart, in one place, so that an
 * addition, a sign-in and the checkout all agree with the catalog.
 *
 * A shop where nothing is hidden pays nothing: no rule, no query. Otherwise a whole cart is
 * judged in one query, never one per line. The lines a promotion offered (is_offered) are
 * the shop's gift, not a choice of the visitor: they are never judged here.
 */
final readonly class CartLineVisibility
{
    /**
     * The flash bag key the sentence telling the visitor which lines were taken out is
     * written under: the one the checkout pages of the theme print.
     */
    public const FLASH_KEY = 'error';

    public function __construct(
        private ProductVisibility $productVisibility,
        private RequestStack $requestStack,
        private CartShippingRules $cartShippingRules,
        private ?PriceVisibilityResolver $priceVisibilityResolver = null,
        private ?CurrentCustomerProvider $currentCustomerProvider = null,
        private ?StockReservationService $stockReservation = null,
    ) {
    }

    /**
     * Whether the product of this sale element is hidden from the current visitor. A sale
     * element that does not exist is not hidden: the addition answers for it.
     *
     * Read from the sale element, never from the product id the caller names: the cart
     * writes the line for the sale element, whatever product the request said.
     */
    public function isSaleElementHidden(int $productSaleElementsId, ?Currency $currency = null, float $quantity = 1.0): bool
    {
        $clause = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null !== $clause && ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsId)
            ->where(\sprintf('NOT (%s)', $clause))
            ->exists()) {
            return true;
        }

        return [] !== $this->unpurchasableAmong([$productSaleElementsId => $quantity], $currency);
    }

    /**
     * Whether the product of the sale element a line holds is hidden from the current visitor:
     * the question of isSaleElementHidden(), asked of a line already in a cart.
     */
    public function isLineHidden(CartItem $line): bool
    {
        return $this->isSaleElementHidden((int) $line->getProductSaleElementsId(), $line->getCart()->getCurrency(), (float) $line->getQuantity());
    }

    /**
     * The lines of the cart whose product is hidden from the current visitor, in one query.
     *
     * Judged on the product of the sale element the line holds, as isSaleElementHidden() does,
     * and not on the product id the line carries: the sale element is what the cart sells.
     *
     * @return list<CartItem>
     */
    public function hiddenLinesOf(Cart $cart): array
    {
        if (null === $cart->getId()) {
            return [];
        }

        $lines = [];
        $clause = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null !== $clause) {
            foreach (CartItemQuery::create()
                ->joinProductSaleElements()
                ->filterByCartId($cart->getId())
                ->filterByIsOffered(0)
                ->where(\sprintf('NOT (%s)', $clause))
                ->orderById()
                ->find() as $line) {
                $lines[(int) $line->getId()] = $line;
            }
        }

        foreach ($this->unpurchasableLinesOf($cart, array_keys($lines)) as $line) {
            $lines[(int) $line->getId()] = $line;
        }

        ksort($lines);

        return array_values($lines);
    }

    /**
     * Takes the lines the current visitor may not see out of the cart, and leaves them a
     * sentence in the flash bag of the session, when there is one to leave it in: a command
     * line, a worker or a stateless API request has none, and none is started for it.
     *
     * @return list<int> the ids of the products that were taken out
     */
    public function removeHiddenLines(Cart $cart): array
    {
        $hiddenLines = $this->hiddenLinesOf($cart);

        if ([] === $hiddenLines) {
            return [];
        }

        $productIds = [];
        $cartId = (int) $cart->getId();
        // A line that held stock goes without a word of its own: the ones taken out are told as released.
        $held = $this->stockReservation?->heldLinesOf($cartId) ?? [];
        $removedIds = [];

        foreach ($hiddenLines as $line) {
            $productIds[(int) $line->getProductId()] = (int) $line->getProductId();
            $removedIds[(int) $line->getId()] = true;
            $line->delete();
        }

        $this->stockReservation?->announceReleased(
            $cartId,
            array_values(array_filter($held, static fn (StockReservationLine $heldLine): bool => isset($removedIds[$heldLine->cartItemId]))),
        );

        // The collection of the cart, if it was loaded, still holds the lines: the next
        // reader gets the cart as it now stands.
        $cart->clearCartItems();

        $this->tellTheVisitor(array_values($productIds));

        return array_values($productIds);
    }

    /**
     * The lines of the cart the visitor is not shown the price of, or that an exclusive price
     * list does not price, among those not already judged hidden.
     *
     * @param list<int> $alreadyHiddenLineIds
     *
     * @return list<CartItem>
     */
    private function unpurchasableLinesOf(Cart $cart, array $alreadyHiddenLineIds): array
    {
        if (null === $this->priceVisibilityResolver || !$this->priceVisibilityResolver->mayRefuseAPurchase()) {
            return [];
        }

        $query = CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByIsOffered(0)
            ->orderById();

        if ([] !== $alreadyHiddenLineIds) {
            $query->filterById($alreadyHiddenLineIds, Criteria::NOT_IN);
        }

        /** @var list<CartItem> $candidates */
        $candidates = array_values($query->find()->getData());
        $quantities = [];

        foreach ($candidates as $line) {
            $quantities[(int) $line->getProductSaleElementsId()] = ($quantities[(int) $line->getProductSaleElementsId()] ?? 0.0) + (float) $line->getQuantity();
        }

        $refused = $this->unpurchasableAmong($quantities, $cart->getCurrency());

        return array_values(array_filter(
            $candidates,
            static fn (CartItem $line): bool => \in_array((int) $line->getProductSaleElementsId(), $refused, true),
        ));
    }

    /**
     * @param array<int, float> $quantities the quantity wanted of each sale element, by sale element id
     *
     * @return list<int> the sale elements the current visitor may not buy
     */
    private function unpurchasableAmong(array $quantities, ?Currency $currency): array
    {
        if (null === $this->priceVisibilityResolver || [] === $quantities || !$this->priceVisibilityResolver->mayRefuseAPurchase()) {
            return [];
        }

        return $this->priceVisibilityResolver->unpurchasableSaleElements(
            array_keys($quantities),
            $this->currentCustomerProvider?->getCurrentCustomer(),
            $currency ?? Currency::getDefaultCurrency(),
            array_map(static fn (float $quantity): int => max(1, (int) floor($quantity)), $quantities),
        );
    }

    /**
     * @param list<int> $productIds
     */
    private function tellTheVisitor(array $productIds): void
    {
        $request = $this->requestStack->getMainRequest();

        // hasSession(true): a session that only exists on paper is not started for a sentence.
        if (null === $request || !$request->hasSession(true)) {
            return;
        }

        $session = $request->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $titles = $this->cartShippingRules->titlesOf($productIds, Translator::getInstance()->getLocale() ?: Lang::getDefaultLanguage()->getLocale());

        $session->getFlashBag()->add(self::FLASH_KEY, Translator::getInstance()->trans(
            'These products are not available to your account and were taken out of your cart: %products.',
            ['%products' => implode(', ', $titles)],
        ));
    }
}
