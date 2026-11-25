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

namespace Thelia\Domain\Quote\Acceptance;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevision;
use Thelia\Module\BaseModule;

/**
 * The cart a buyer pays a quote with: the lines of the revision they accepted, at the prices the
 * shop and the buyer agreed on, with the discount of the revision. It is a cart of the buyer like
 * any other, which the buyer takes through the checkout, and it says it comes from a quote.
 *
 * The prices are written on the lines and never worked out again from the catalog; the postage is
 * the one the revision carries, written by the listener of the cart of a quote.
 */
final readonly class QuoteCartBuilder
{
    public function __construct(
        private QuoteAccess $access,
        private CartAddressService $cartAddressService,
        private PostageHandler $postageHandler,
        private QuoteCartLines $cartLines = new QuoteCartLines(),
    ) {
    }

    /**
     * The cart of the buyer that already stands for this revision and was not ordered, or a new one.
     * A cart whose lines are no longer those of the revision is left alone and not given back.
     */
    public function cartFor(Quote $quote, QuoteRevision $revision, Customer $buyer, ConnectionInterface $connection): Cart
    {
        return $this->resumable($quote, $revision, $buyer, $connection) ?? $this->build($quote, $revision, $buyer, $connection);
    }

    private function resumable(Quote $quote, QuoteRevision $revision, Customer $buyer, ConnectionInterface $connection): ?Cart
    {
        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();

        $carts = CartQuery::create()
            ->filterByQuoteId($quote->getId())
            ->filterByQuoteRevision($revision->getNumber())
            ->filterByCustomerId($buyer->getId())
            ->orderById(Criteria::DESC)
            ->find($connection);

        foreach ($carts as $cart) {
            if (!$this->hasALiveOrder($cart, $connection) && $this->cartLines->holds($cart, $revision, $connection)) {
                return $cart;
            }
        }

        return null;
    }

    private function build(Quote $quote, QuoteRevision $revision, Customer $buyer, ConnectionInterface $connection): Cart
    {
        $cart = (new Cart())
            ->setToken(bin2hex(random_bytes(16)))
            ->setCustomerId($buyer->getId())
            ->setCurrencyId($quote->getCurrencyId())
            ->setQuoteId($quote->getId())
            ->setQuoteRevision($revision->getNumber())
            ->setDiscount($revision->getDiscount())
            ->setAddressInvoiceId($this->cartAddressOf($quote->getInvoiceAddressId(), $buyer))
            ->setAddressDeliveryId($this->cartAddressOf($quote->getDeliveryAddressId(), $buyer))
            ->setDeliveryModuleId($this->deliveryModuleIdOf($quote));
        $cart->save($connection);

        $endOfLife = time() + (int) ConfigQuery::read('cart.priceEOF', 60 * 60 * 24 * 30);

        foreach ($this->cartLines->linesOf($revision, $connection) as $quoteLine) {
            $price = number_format(round((float) $quoteLine->getPrice(), 6), 6, '.', '');

            (new CartItem())
                ->setCartId($cart->getId())
                ->setProductId($quoteLine->getProductId())
                ->setProductSaleElementsId($quoteLine->getProductSaleElementsId())
                ->setQuantity($quoteLine->getQuantity())
                ->setPrice($price)
                ->setPromoPrice($price)
                ->setPromo(0)
                ->setPriceEndOfLife($endOfLife)
                ->setQuoteItemId($quoteLine->getId())
                ->save($connection);
        }

        // The postage the revision carries is written on the cart by the listener of the cart of a quote.
        $this->postageHandler->handlePostageOnCart($cart);

        return $cart;
    }

    /**
     * The address of the quote, copied on the cart, when it still exists and the buyer may use it:
     * a buyer who may not, or who finds it gone, is asked again by the checkout.
     */
    private function cartAddressOf(?int $addressId, Customer $buyer): ?int
    {
        if (null === $addressId || !$this->access->canUseAddressId($buyer, $addressId)) {
            return null;
        }

        $address = AddressQuery::create()->findPk($addressId);

        return $address instanceof Address ? $this->cartAddressService->getOrCreateCartAddressFromAddress($address)->getId() : null;
    }

    private function deliveryModuleIdOf(Quote $quote): ?int
    {
        $moduleId = $quote->getDeliveryModuleId();

        if (null === $moduleId) {
            return null;
        }

        $module = ModuleQuery::create()->findPk($moduleId);

        return $module instanceof Module && BaseModule::DELIVERY_MODULE_TYPE === (int) $module->getType() && 0 !== (int) $module->getActivate() ? $moduleId : null;
    }

    private function hasALiveOrder(Cart $cart, ConnectionInterface $connection): bool
    {
        foreach (OrderQuery::create()->filterByCartId($cart->getId())->find($connection) as $order) {
            if (!$order->isCancelled()) {
                return true;
            }
        }

        return false;
    }
}
