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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

final readonly class SessionFactory implements SessionFactoryInterface
{
    public const ATTRIBUTE_SESSION_STORAGE = '_thelia_session_storage';

    public function __construct(
        private SessionStorageFactoryInterface $storageFactory,
        private RequestStack $requestStack,
    ) {
    }

    public function createSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        $storage = $this->storageFactory->createStorage($request);

        return new Session($storage);
    }
}
