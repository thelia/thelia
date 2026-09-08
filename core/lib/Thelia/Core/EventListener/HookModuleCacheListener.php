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
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\Event\ModuleEvent;

/**
 * Keeps the module a hook belongs to resolved once per process.
 *
 * The lookup {@see BaseHook::__construct()} memoizes is read on every hook
 * class built for a page and almost never written, so a plain static is the
 * right cache. What it must not do is answer for a module row that has since
 * been written or deleted: installing, upgrading or removing a module all
 * happen inside a request that has already built hooks.
 */
readonly class HookModuleCacheListener
{
    #[AsEventListener(event: ModuleEvent::POST_SAVE)]
    #[AsEventListener(event: ModuleEvent::POST_DELETE)]
    public function onModuleWrite(): void
    {
        BaseHook::resetModuleClassNameCache();
    }
}
