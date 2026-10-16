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
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Model\Lang;

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
        if (!$this->checker->hasActiveGroup() || null === $productId = $event->getProductId()) {
            return;
        }

        $cart = $event->getCart();
        $locale = Translator::getInstance()->getLocale() ?: Lang::getDefaultLanguage()->getLocale();

        $lines = $this->checker->violationsForProducts(
            [(int) $productId],
            CartDestinations::delivery($cart, true),
            CartDestinations::billing($cart, true),
            $locale,
        );

        if ([] !== $lines) {
            throw new InvalidCartException(Translator::getInstance()->trans('%product cannot be shipped to your address.', ['%product' => $lines[0]->label()]));
        }
    }
}
