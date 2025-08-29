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
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Shipping\Service\PostageHandler;

readonly class CartItemService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CartEventBuilder $cartEventBuilder,
        private PostageHandler $postageHandler,
    ) {
    }

    public function addItem(CartItemAddDTO $cartAddDTO): void
    {
        $cartEvent = $this->cartEventBuilder->buildEvent($cartAddDTO);
        $this->eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);
        $this->postageHandler->handlePostageOnCart($cartAddDTO->getCart());
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
    public function updateQuantityItem(CartItemUpdateQuantityDTO $cartItemUpdateQuantityDTO): void
    {
        $cartEvent = $this->cartEventBuilder->buildEvent($cartItemUpdateQuantityDTO);
        $this->eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_UPDATEITEM);
        $this->postageHandler->handlePostageOnCart($cartItemUpdateQuantityDTO->getCart());
    }
}
