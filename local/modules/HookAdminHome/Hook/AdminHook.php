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

namespace HookAdminHome\Hook;

use HookAdminHome\HookAdminHome;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class AdminHook
 * @package HookAdminHome\Hook
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class AdminHook extends BaseHook
{
    public function blockStatistics(HookRenderEvent $event)
    {
        $event->add($this->render('block-statistics.html'));
    }

    public function blockStatisticsJs(HookRenderEvent $event)
    {
        $event->add($this->render('block-statistics-js.html'));
    }

    public function blockSalesStatistics(HookRenderBlockEvent $event)
    {
        $content = trim($this->render("block-sales-statistics.html"));
        if (!empty($content)) {
            $event->add([
                "id" => "block-sales-statistics",
                "title" => $this->trans("Sales statistics", [], HookAdminHome::DOMAIN_NAME),
                "content" => $content
            ]);
        }
    }

    public function blockNews(HookRenderBlockEvent $event)
    {
        $content = trim($this->render("block-news.html"));
        if (!empty($content)) {
            $event->add([
                "id" => "block-news",
                "content" => $content
            ]);
        }
    }

    public function blockTheliaInformation(HookRenderBlockEvent $event)
    {
        $content = trim($this->render("block-thelia-information.html"));
        if (!empty($content)) {
            $event->add([
                "id" => "block-thelia-information",
                "title" => $this->trans("Thelia informations", [], HookAdminHome::DOMAIN_NAME),
                "content" => $content
            ]);
        }
    }
}
