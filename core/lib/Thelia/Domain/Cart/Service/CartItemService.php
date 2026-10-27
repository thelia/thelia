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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\EventBuilder\CartEventBuilder;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\CartItem;

readonly class CartItemService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CartEventBuilder $cartEventBuilder,
        private PostageHandler $postageHandler,
    ) {
    }

    /**
     * @throws InvalidCartException when the cart wrote no line: nothing prices the sale
     *                              element, so there is no price to put on a line
     */
    public function addItem(CartItemAddDTO $cartAddDTO): CartItem
    {
        $cartEvent = $this->cartEventBuilder->buildEvent($cartAddDTO);
        $this->eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);

        $cartItem = $cartEvent->getCartItem();

        if (!$cartItem instanceof CartItem) {
            throw new InvalidCartException('This product has no price: it cannot be added to the cart.');
        }

        $this->postageHandler->handlePostageOnCart($cartAddDTO->getCart());

        return $cartItem;
    }

    public function deleteItem(CartItemDeleteDTO $cartDeleteItemDTO): void
    {
        $cartEvent = $this->cartEventBuilder->buildEvent($cartDeleteItemDTO);
        $this->eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_DELETEITEM);
        $this->postageHandler->handlePostageOnCart($cartDeleteItemDTO->getCart());
    }

    /**
     * @throws NotEnoughStockException
     */
    public function updateQuantityItem(CartItemUpdateQuantityDTO $cartItemUpdateQuantityDTO): CartItem
    {
        $cartEvent = $this->cartEventBuilder->buildEvent($cartItemUpdateQuantityDTO);
        $this->eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_UPDATEITEM);
        $this->postageHandler->handlePostageOnCart($cartItemUpdateQuantityDTO->getCart());

        return $cartEvent->getCartItem();
    }
}
