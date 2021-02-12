<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Cart;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 * managed cart.
 *
 * Trait CartTrait
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @deprecated CartTrait is deprecated, please use Session::getSessionCart method instead
 */
trait CartTrait
{
    /**
     * search if cart already exists in session. If not try to create a new one or duplicate an old one.
     *
     * @param EventDispatcherInterface                  $dispatcher the event dispatcher
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @deprecated use Session::getSessionCart method instead
     *
     * @return \Thelia\Model\Cart
     */
    public function getCart(EventDispatcherInterface $dispatcher, Request $request)
    {
        trigger_error(
            'CartTrait is deprecated, please use Session::getSessionCart method instead',
            E_USER_DEPRECATED
        );

        return $request->getSession()->getSessionCart($dispatcher);
    }
}
