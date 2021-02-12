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

namespace Carousel\Hook;

use Carousel\Carousel;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

/**
 * Class BackHook.
 *
 * @author Emmanuel Nurit <enurit@openstudio.fr>
 */
class BackHook extends BaseHook
{
    /**
     * Add a new entry in the admin tools menu.
     *
     * should add to event a fragment with fields : id,class,url,title
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event)
    {
        $event->add(
            [
                'id' => 'tools_menu_carousel',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl('/admin/module/Carousel'),
                'title' => $this->trans('Edit your carousel', [], Carousel::DOMAIN_NAME),
            ]
        );
    }
}
