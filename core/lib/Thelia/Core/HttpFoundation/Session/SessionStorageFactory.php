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

namespace Thelia\Core\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Thelia\Model\ConfigQuery;

final readonly class SessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private string $defaultSavePath,
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $env = \is_string($_SERVER['APP_ENV'] ?? null) ? $_SERVER['APP_ENV'] : 'prod';

        if ('test' === $env || headers_sent()) {
            return $this->createMockStorage($request);
        }

        $lifetime = (int) ConfigQuery::read('session_config.lifetime', 0);
        $customSavePath = ConfigQuery::read('session_config.save_path', $this->defaultSavePath);

        $options = [];

        if ($lifetime > 0) {
            $options['gc_maxlifetime'] = $lifetime;
            $options['cookie_lifetime'] = $lifetime;
        }
        if ($customSavePath) {
            $options['save_path'] = $customSavePath;
        }
        $handler = new NativeFileSessionHandler($customSavePath ?: $this->defaultSavePath);

        return new NativeSessionStorage($options, $handler);
    }

    /**
     * The native storage reads the session cookie itself, through php. The mock one never
     * does, so it has to be handed the id the client sends back: without it every request
     * of a same client opens a new session, and nothing a page stores for the next request
     * — a form token, a cart, a flash message — survives the round trip.
     *
     * The id names the file the storage reads, so only the character set php gives session
     * ids is accepted.
     */
    private function createMockStorage(?Request $request): MockFileSessionStorage
    {
        $storage = new MockFileSessionStorage($this->defaultSavePath);
        $sessionId = $request?->cookies->get($storage->getName());

        if (\is_string($sessionId) && 1 === preg_match('/^[a-zA-Z0-9,-]{1,128}$/', $sessionId)) {
            $storage->setId($sessionId);
        }

        return $storage;
    }
}
