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
            return new MockFileSessionStorage($this->defaultSavePath);
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
}
