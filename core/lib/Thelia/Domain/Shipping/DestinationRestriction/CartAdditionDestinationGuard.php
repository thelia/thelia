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

namespace Thelia\Domain\Shipping\DestinationRestriction;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Refuses to add a product to a cart whose known destination the product cannot ship
 * to, so the buyer learns it before checkout. Without a known address (a visitor who
 * has not signed in, a customer without address) nothing is refused: the address step
 * and the placement of the order decide.
 *
 * Above Thelia\Action\Cart::addItem (128), so a refused product never reaches the cart.
 */
final readonly class CartAdditionDestinationGuard implements EventSubscriberInterface
{
    public function __construct(private DestinationRestrictionChecker $checker)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [TheliaEvents::CART_ADDITEM => ['refuseARestrictedProduct', 200]];
    }

    public function refuseARestrictedProduct(CartEvent $event): void
    {
        // A shop with no active group refuses nothing: no query.
        if (!$this->checker->hasActiveGroup()) {
            return;
        }

        // The product is the one of the sale element the cart writes the line for, as
        // Action\Cart::addItem reads it: the product id of the request is the caller's, and
        // a forged one (a product of the page, the sale element of another) would be judged
        // in place of the product that is shipped. A sale element that does not exist is left
        // to the addition to answer.
        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = null === $productSaleElementsId
            ? null
            : ProductSaleElementsQuery::create()->filterById($productSaleElementsId)->select('ProductId')->findOne();

        // The addresses of the cart are only resolved for a product a group holds: adding
        // anything else costs two queries (the product of the sale element, the groups that
        // hold it), and none in a shop without active group.
        if (null === $productId || !$this->checker->isRestricted((int) $productId)) {
            return;
        }

        $productId = (int) $productId;
        $cart = $event->getCart();
        $locale = Translator::getInstance()->getLocale() ?: Lang::getDefaultLanguage()->getLocale();

        $lines = $this->checker->violationsForProducts(
            [$productId],
            CartDestinations::delivery($cart, true),
            CartDestinations::billing($cart, true),
            $locale,
        );

        if ([] !== $lines) {
            throw new ProductRefusedByDestinationException(Translator::getInstance()->trans('%product cannot be shipped to your address.', ['%product' => $lines[0]->label()]));
        }
    }
}
