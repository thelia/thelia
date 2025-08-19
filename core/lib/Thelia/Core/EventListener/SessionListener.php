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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
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
class SessionListener
{
    public function __construct(
        protected string $sessionSavePath,
    ) {
    }

    #[AsEventListener(event: TheliaKernelEvents::SESSION, priority: 255)]
    public function prodSession(SessionEvent $event): void
    {
        if (!$this->canSetSession($event)) {
            return;
        }

        if (\PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        $lifetime = (int) ConfigQuery::read('session_config.lifetime', 0);
        $customSavePath = ConfigQuery::read('session_config.save_path', $this->sessionSavePath);

        if ($lifetime > 0) {
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            ini_set('session.cookie_lifetime', (string) $lifetime);
        }

        if ($customSavePath && session_save_path() !== $customSavePath) {
            ini_set('session.save_path', $customSavePath);
        }

        $handler = new NativeFileSessionHandler($customSavePath);
        $options = [
            'gc_maxlifetime' => $lifetime,
            'cookie_lifetime' => $lifetime,
            'save_path' => $customSavePath,
        ];

        $storage = new NativeSessionStorage($options, $handler);

        $event->setSession($this->getSession($storage));
    }

    #[AsEventListener(event: TheliaKernelEvents::SESSION, priority: 128)]
    public function testSession(SessionEvent $event): void
    {
        if ('test' === $event->getEnv()) {
            $storage = new MockFileSessionStorage($event->getCacheDir().DS.'sessions');
            $event->setSession($this->getSession($storage));
            $event->stopPropagation();
        }
    }

    public function getSession(SessionStorageInterface $storage): Session
    {
        return new Session($storage);
    }

    private function canSetSession(SessionEvent $event): bool
    {
        $request = $event->getRequest();
        if ($request->get('isApiRoute', false) || $request->hasSession(true)) {
            return false;
        }
        if (null !== $request->attributes->get('_live_action')) {
            return false;
        }

        return !headers_sent();
    }
}
