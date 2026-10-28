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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartItemQuantityAdjustedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Quantity\InvalidQuantityRuleException;
use Thelia\Domain\Catalog\Quantity\NotPositiveQuantityException;
use Thelia\Domain\Catalog\Quantity\QuantityDecision;
use Thelia\Domain\Catalog\Quantity\QuantityPolicy;
use Thelia\Domain\Catalog\Quantity\UnreadableQuantityException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ProductSaleElements;

/**
 * Brings the lines of a cart read back to the rules of their combination: a cart
 * restored from its cookie or duplicated at sign-in, a cart whose combinations were
 * given other rules since it was filled. Nobody is asking for a quantity here, so
 * nothing is refused: a line off the rules is brought down to the valid quantity below
 * it, or removed when even that is below the smallest quantity sold, and
 * CART_ITEM_QUANTITY_ADJUSTED tells which and why.
 *
 * A line a promotion offers is not sold: it is left to the promotion.
 */
final readonly class CartQuantityReconciler
{
    public function __construct(
        private QuantityPolicy $quantityPolicy = new QuantityPolicy(),
    ) {
    }

    /**
     * One query for the lines and their combinations, and a write only for a line
     * that changes.
     *
     * @return list<CartItemQuantityAdjustedEvent> the lines adjusted or removed, as dispatched
     */
    public function reconcile(Cart $cart, EventDispatcherInterface $dispatcher): array
    {
        if (null === $cart->getId()) {
            return [];
        }

        $adjusted = [];
        $lines = CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->joinWithProductSaleElements()
            ->find();

        foreach ($lines as $line) {
            if (1 === (int) $line->getIsOffered()) {
                continue;
            }

            $decision = $this->decide($line->getProductSaleElements(), (float) $line->getQuantity());

            if (null === $decision) {
                continue;
            }

            $this->apply($line, $decision);
            $event = new CartItemQuantityAdjustedEvent($line, $decision);
            $dispatcher->dispatch($event, TheliaEvents::CART_ITEM_QUANTITY_ADJUSTED);
            $adjusted[] = $event;
        }

        if ([] !== $adjusted) {
            $cart->clearCartItems();
        }

        return $adjusted;
    }

    /**
     * What the rules make of a quantity read back, null when they keep it as it is. A
     * quantity of zero or less, or rules that cannot decide (written around the
     * validation of the combination), are no business of the rules: the line is left.
     */
    public function decide(?ProductSaleElements $productSaleElements, float $quantity): ?QuantityDecision
    {
        if (null === $productSaleElements) {
            return null;
        }

        try {
            $decision = $this->quantityPolicy->normalize($quantity, $productSaleElements);
        } catch (NotPositiveQuantityException|UnreadableQuantityException|InvalidQuantityRuleException) {
            return null;
        }

        return $decision->isAdjusted() || $decision->removesTheLine() ? $decision : null;
    }

    /**
     * Saves the quantity the decision keeps, or deletes the line it removes.
     */
    public function apply(CartItem $line, QuantityDecision $decision): void
    {
        if (null === $decision->quantity) {
            $line->delete();

            return;
        }

        $line->setQuantity($decision->quantity)->save();
    }
}
