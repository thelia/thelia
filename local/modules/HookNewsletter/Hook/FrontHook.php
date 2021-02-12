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

namespace HookNewsletter\Hook;

use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class FrontHook.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FrontHook extends BaseHook
{
    public function onMainFooterBody(HookRenderBlockEvent $event): void
    {
        $content = trim($this->render('main-footer-body.html'));
        if ('' != $content) {
            $event->add([
                'id' => 'newsletter-footer-body',
                'class' => 'newsletter',
                'title' => $this->trans('Newsletter', [], 'hooknewsletter'),
                'content' => $content,
            ]);
        }
    }
}
