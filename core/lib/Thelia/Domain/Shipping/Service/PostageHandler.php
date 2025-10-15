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

namespace Thelia\Domain\Shipping\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;

class PostageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handlePostageOnCart(Cart $cart): void
    {
        try {
            $this->eventDispatcher->dispatch(new CartCheckoutEvent($cart), TheliaEvents::CART_SET_POSTAGE);
        } catch (\Exception $e) {
            $this->clearCartPostage($cart);

            Tlog::getInstance()->error(\sprintf('Failed to set postage : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to set postage');
        }
    }

    public function clearCartPostage(Cart $cart): void
    {
        $cart
            ->setPostage(null)
            ->setPostageTax(0.0)
            ->setPostageTaxRuleTitle(null)
            ->setDeliveryModuleId(null)
            ->save();
    }
}
