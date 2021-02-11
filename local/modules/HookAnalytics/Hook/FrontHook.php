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

namespace HookAnalytics\Hook;

use HookAnalytics\HookAnalytics;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class FrontHook
 * @package HookCurrency\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FrontHook extends BaseHook {
    public function onMainHeadBottom(HookRenderEvent $event)
    {
        $lang = $this->getRequest()->getSession()->get("thelia.current.lang");
        $value = trim(HookAnalytics::getConfigValue("hookanalytics_trackingcode", "", $lang->getLocale()));
        if ("" != $value){
            $event->add($value);
        }
    }
}
