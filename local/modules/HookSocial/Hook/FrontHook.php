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

namespace HookSocial\Hook;

use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class FrontHook
 * @package HookCurrency\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FrontHook extends BaseHook {

    public function onMainFooterBody(HookRenderBlockEvent $event)
    {
        $content = trim($this->render("main-footer-body.html"));
        if ("" != $content){
            $event->add(array(
                "id" => "social-footer-body",
                "class" => "social",
                "title" => $this->trans("Follow us", array(), "hooksocial"),
                "content" => $content
            ));
        }
    }
} 