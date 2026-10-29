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

namespace Thelia\Domain\Cart\Bundle;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Judges the addition of a bundle before the cart sees it: the composition proposed is
 * revalidated against the bundle (whoever sent it: the form, the API, a module), a
 * composition proposed for a product that is not a bundle is refused, and the quantity the
 * line would hold is refused out loud when the components cannot supply it.
 *
 * A shop without a bundle judges nothing and asks nothing of the database, but a composition
 * proposed anyway. Without composition the default one is taken. What the cart receives is the resolved
 * composition, whole, so that two ways of naming the same choice make the same line.
 *
 * The quantity is the one of the line the addition joins, if any, plus the quantity added.
 * What other lines of the cart already ask of the same components (another bundle, the
 * component sold alone) is not counted here: the placement of the order judges the stock
 * of the whole cart.
 *
 * Above Thelia\Action\Cart::addItem (128), under the destination guard (200).
 */
final readonly class BundleCartAdditionGuard implements EventSubscriberInterface
{
    public function __construct(
        private BundleCatalogState $bundleCatalogState,
        private CartCompositionResolver $cartCompositionResolver,
        private CartLineAvailability $cartLineAvailability,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [TheliaEvents::CART_ADDITEM => ['judgeABundleAddition', 150]];
    }

    /**
     * @throws InvalidBundleCompositionException the composition cannot be held by the bundle, or the product is not one
     * @throws NotEnoughStockException           the components cannot supply the quantity
     */
    public function judgeABundleAddition(CartEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $proposed = $event->getBundleComposition();

        if (null === $proposed && !$this->bundleCatalogState->hasBundles()) {
            return;
        }

        if (null === $event->getProductSaleElementsId()
            || null === $saleElements = ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementsId())) {
            return;
        }

        $productId = (int) $saleElements->getProductId();

        if (null === $composition = $this->cartCompositionResolver->forAddition($productId, $proposed)) {
            return;
        }

        $event->setBundleComposition($composition);

        $quantity = $event->getQuantity();

        if (null === $quantity || $quantity <= 0 || !ConfigQuery::checkAvailableStock()) {
            return;
        }

        // Asked once: the cart takes the answer from the event.
        $available = $this->cartLineAvailability->forLine($saleElements, $composition);
        $event->setBundleAvailability($available);

        if ($quantity + $this->quantityOfTheLineJoined($event, $productId, $dispatcher) > $available) {
            throw new NotEnoughStockException(\sprintf('Not enough stock for product %s', $saleElements->getProduct()->getRef()));
        }
    }

    /**
     * Asked the way the cart asks it (CART_FINDITEM), so that a module picking the line is heard.
     */
    private function quantityOfTheLineJoined(CartEvent $event, int $productId, EventDispatcherInterface $dispatcher): float
    {
        if (!$event->getAppend()) {
            return 0.0;
        }

        $lookup = clone $event;
        $lookup->setProductId($productId)->clearCartItem();
        $dispatcher->dispatch($lookup, TheliaEvents::CART_FINDITEM);

        return (float) ($lookup->getCartItem()?->getQuantity() ?? 0.0);
    }
}
