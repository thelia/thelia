<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\ConfigQuery;

/**
 * Class ResponseListener
 * @package Thelia\Core\EventListener
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ResponseListener implements EventSubscriberInterface
{
    public function beforeResponse(FilterResponseEvent $event)
    {
        $session = $event->getRequest()->getSession();

        if (null !== $id = $session->get("cart_use_cookie")) {

            $response = $event->getResponse();
            $cookieName = ConfigQuery::read("cart.cookie_name", 'thelia_cart');

            if (empty($id)) {
                $response->headers->clearCookie($cookieName, '/');
            } else {
                $response->headers->setCookie(
                    new Cookie(
                        ConfigQuery::read("cart.cookie_name", 'thelia_cart'),
                        $id,
                        time()+ConfigQuery::read("cart.cookie_lifetime", 60*60*24*365),
                        '/'
                    )
                );
            }

            $session->set("cart_use_cookie", null);
        }
    }

    /**
     * {@inheritdoc}
     * api
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['beforeResponse', 128]
        ];
    }
}
