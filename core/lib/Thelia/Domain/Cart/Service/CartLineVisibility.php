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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * What the cart may hold for the visitor who owns it: the products the catalog shows them,
 * and nothing else.
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
    ) {
    }

    /**
     * Whether the product of this sale element is hidden from the current visitor. A sale
     * element that does not exist is not hidden: the addition answers for it.
     *
     * Read from the sale element, never from the product id the caller names: the cart
     * writes the line for the sale element, whatever product the request said.
     */
    public function isSaleElementHidden(int $productSaleElementsId): bool
    {
        $clause = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null === $clause) {
            return false;
        }

        return ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsId)
            ->where(\sprintf('NOT (%s)', $clause))
            ->exists();
    }

    /**
     * Whether the product of the sale element a line holds is hidden from the current visitor:
     * the question of isSaleElementHidden(), asked of a line already in a cart.
     */
    public function isLineHidden(CartItem $line): bool
    {
        return $this->isSaleElementHidden((int) $line->getProductSaleElementsId());
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

        $clause = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null === $clause) {
            return [];
        }

        return array_values(CartItemQuery::create()
            ->joinProductSaleElements()
            ->filterByCartId($cart->getId())
            ->filterByIsOffered(0)
            ->where(\sprintf('NOT (%s)', $clause))
            ->orderById()
            ->find()
            ->getData());
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

        foreach ($hiddenLines as $line) {
            $productIds[(int) $line->getProductId()] = (int) $line->getProductId();
            $line->delete();
        }

        // The collection of the cart, if it was loaded, still holds the lines: the next
        // reader gets the cart as it now stands.
        $cart->clearCartItems();

        $this->tellTheVisitor(array_values($productIds));

        return array_values($productIds);
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
