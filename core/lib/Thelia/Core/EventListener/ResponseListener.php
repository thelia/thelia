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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\ConfigQuery;

/**
 * Class ResponseListener.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ResponseListener implements EventSubscriberInterface
{
    public function beforeResponse(ResponseEvent $event): void
    {
        if (!$event->getRequest()->hasSession(true) || !$event->getRequest()->getSession()->isStarted()) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (null !== $id = $session->get('cart_use_cookie')) {
            $response = $event->getResponse();
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');

            if (empty($id)) {
                $response->headers->clearCookie($cookieName, '/');
            } else {
                $response->headers->setCookie(
                    new Cookie(
                        ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
                        $id,
                        time() + ConfigQuery::read('cart.cookie_lifetime', 60 * 60 * 24 * 365),
                        '/'
                    )
                );
            }

            $session->set('cart_use_cookie', null);
        }
    }

    /**
     * {@inheritdoc}
     * api.
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['beforeResponse', 128],
        ];
    }
}
