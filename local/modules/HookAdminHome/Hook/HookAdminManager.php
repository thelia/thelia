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

namespace HookAdminHome\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class HookAdminManager extends BaseHook
{
    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('admin-home-config.html')
        );
    }
}
