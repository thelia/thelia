<?php
namespace HookDashboard\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class AdminHook
 * @package HookDashboard\Hook
 * @author Julien Vigouroux <jvigouroux@openstudio.fr>
 */
class AdminHook extends BaseHook
{
    public function onHomeTop(HookRenderEvent $event)
    {
        $event->add($this->render('dashboard.html'));
    }
    public function onHomeDashboard(HookRenderEvent $event)
    {
        $event->add($this->render('dashboard.html'));
    }
    public function onHomeJs(HookRenderEvent $event)
    {
        $event->add($this->render('home-js.tpl'));
    }
}