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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Model\ConfigQuery;

/**
 * Class SessionListener.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class SessionListener implements EventSubscriberInterface
{
    protected $sessionSavePath;

    public function __construct($sessionSavePath)
    {
        $this->sessionSavePath = $sessionSavePath;
    }

    public function prodSession(SessionEvent $event): void
    {
        $storage = new NativeSessionStorage(
            ['cookie_lifetime' => ConfigQuery::read('session_config.lifetime', 0)]
        );

        $sessionSavePath = ConfigQuery::read('session_config.save_path');
        if (null == $sessionSavePath) {
            $sessionSavePath = $this->sessionSavePath;
        }

        $storage->setSaveHandler(
            new NativeFileSessionHandler(
                $sessionSavePath
            )
        );
        $event->setSession($this->getSession($storage));
    }

    public function testSession(SessionEvent $event): void
    {
        if ($event->getEnv() == 'test') {
            $storage = new MockFileSessionStorage($event->getCacheDir().DS.'sessions');
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
     * api.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TheliaKernelEvents::SESSION => [
                ['prodSession', 0],
                ['testSession', 128],
            ],
        ];
    }
}
