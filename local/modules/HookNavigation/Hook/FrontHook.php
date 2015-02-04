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

namespace HookNavigation\Hook;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;


/**
 * Class FrontHook
 * @package HookCurrency\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FrontHook extends BaseHook {

    public function onMainFooterBottom(HookRenderEvent $event)
    {
        $content = $this->render("main-footer-bottom.html");
        $event->add($content);
    }

    public function onMainFooterBody(HookRenderBlockEvent $event)
    {
        $content = trim($this->render("main-footer-body.html"));
        if ("" != $content){
            $event->add(array(
                "id" => "navigation-footer-body",
                "class" => "links",
                "title" => $this->trans("Latest articles", array(), "hooknavigation"),
                "content" => $content
            ));
        }
    }

    public function onMainNavbarPrimary(HookRenderEvent $event)
    {
        $content = $this->render("main-navbar-primary.html");
        $event->add($content);
    }

} 