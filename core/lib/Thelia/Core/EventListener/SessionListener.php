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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Model\ConfigQuery;

/**
 * Class SessionListener
 * @package Thelia\Core\EventListener
 * @author manuel raynaud <manu@thelia.net>
 */
class SessionListener implements EventSubscriberInterface
{
    public function prodSession(SessionEvent $event)
    {
        $storage = new NativeSessionStorage(
            [ 'cookie_lifetime' => ConfigQuery::read('session_config.lifetime', 0) ]
        );
        $storage->setSaveHandler(
            new NativeFileSessionHandler(
                ConfigQuery::read("session_config.save_path", THELIA_ROOT . '/local/session/')
            )
        );
        $event->setSession($this->getSession($storage));
    }

    public function testSession(SessionEvent $event)
    {
        if ($event->getEnv() == 'test') {
            $storage = new MockFileSessionStorage($event->getCacheDir() . DS . 'sessions');
            $event->setSession($this->getSession($storage));
            $event->stopPropagation();
        }
    }

    public function getSession(SessionStorageInterface $storage)
    {
        return new Session($storage);
    }
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaKernelEvents::SESSION =>[
                ['prodSession', 0],
                ['testSession', 128]
            ]
        ];
    }
}
