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

namespace Tinymce\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class HookManager.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class HookManager extends BaseHook
{
    public function onJsWysiwyg(HookRenderEvent $event): void
    {
        $content = $this->render('tinymce_init.tpl');
        $event->add($content);
    }
}
