<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Colissimo\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class ColissimoHook
 *
 * @package Colissimo\Hook
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class ColissimoHook extends BaseHook {

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $event->add(
            $this->render("module_configuration.html")
        );
    }

    public function onModuleConfigurationJS(HookRenderEvent $event)
    {
        $event->add($this->addJS("assets/js/module-configuration.js"));
    }
}