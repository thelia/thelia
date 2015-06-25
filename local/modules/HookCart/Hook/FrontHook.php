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

namespace HookCart\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class FrontHook
 * @package HookCurrency\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FrontHook extends BaseHook
{

    public function onMainHeadBottom(HookRenderEvent $event)
    {
        $content = $this->addCSS('assets/css/styles.css');
        $event->add($content);
    }

    public function onMainNavbarSecondary(HookRenderEvent $event)
    {
        $content = $this->render("main-navbar-secondary.html");
        $event->add($content);
    }

    public function onMainNavbarPrimary(HookRenderEvent $event)
    {
        $content = $this->render("main-navbar-primary.html");
        $event->add($content);
    }

    public function onMiniCart(HookRenderEvent $event)
    {
        $content = $this->render('mini-cart.html');
        $event->add($content);
    }
}
