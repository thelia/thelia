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
 * @author manuel raynaud <manu@raynaud.io>
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
     * {@inheritdoc}
     * api
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
