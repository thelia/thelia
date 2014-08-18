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

namespace HookTest\Hook;

use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Hook\Fragment;


/**
 * Class FrontHook
 * @package HookCurrency\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FrontHook extends BaseHook {

    protected $ldelim = "::";
    protected $rdelim = "::";


    public function onMainHeadTop(HookRenderEvent $event)
    {
        $event->add($this->mark("main.head-top test0"));
    }

    public function onMainHeadTopTest1(HookRenderEvent $event)
    {
        $event->add($this->mark("main.head-top test1"));
    }

    public function onMainHeadTopTest2(HookRenderEvent $event)
    {
        $event->add($this->mark("main.head-top test2"));
    }

    public function onMainHeadTopTest3(HookRenderEvent $event)
    {
        $event->add($this->mark("main.head-top test3"));
    }


    // == Hook Function =====================================================

    public function onMainBodyTop(HookRenderEvent $event){
        $event->add($this->mark("main.body-top 1-1"));
        $event->add($this->mark("main.body-top 1-2"));
    }

    public function onMainBodyTop2(HookRenderEvent $event)
    {
        $event->add($this->mark("main.body-top 2"));
    }


    // == ifhook / elsehook ================================================

    public function onMainNavbarSecondary(HookRenderEvent $event){
        $event->add($this->mark("main.navbar-secondary 1"));
    }

    /**
     * empty string should be considered as empty :) and should activate the elsehook
     *
     * @param HookRenderEvent $event
     */
    public function onMainNavbarPrimary(HookRenderEvent $event)
    {
        $event->add("");
        $event->add(" ");
    }

    public function onProductAdditional(HookRenderEvent $event)
    {
        // nothing added
    }


    // == hookblock / forhook ==============================================

    public function onMainFooterBody(HookRenderBlockEvent $event)
    {
        $event->addFragment(new Fragment(array(
            "id" => "id1",
            "class" => "class1",
            "content" => "content1"
        )));
        $event->add(array(
            "id" => "id2",
            "class" => "class2",
            "content" => "content2"
        ));
    }

    // == global objects ===================================================

    public function onMainContentTop(HookRenderEvent $event)
    {
        $event->add($this->mark("main.content-top"));
        $event->add($this->mark("view : " . $this->getView()));
        $event->add($this->mark("request : " . $this->getRequest()));
        $event->add($this->mark("session : " . $this->getSession()->getId()));
        $event->add($this->mark("cart : " . ($this->getCart() === null ? "null" : "not null")));
        $event->add($this->mark("order : " . ($this->getOrder() === null ? "null" : "not null")));
        $event->add($this->mark("currency : " . $this->getCurrency()->getId()));
        $event->add($this->mark("customer : " . $this->getCustomer()));
        $event->add($this->mark("lang : " . $this->getLang()->getId()));
    }

    public function onMainContentTopRender(HookRenderEvent $event)
    {
        $event->add($this->render("render.html"));
    }

    public function onMainContentTopDump(HookRenderEvent $event)
    {
        $event->add($this->dump("dump.txt"));
    }

    public function onMainContentTopAddCSS(HookRenderEvent $event)
    {
        $event->add($this->mark($this->addCSS("assets/css/styles.css")));
        $event->add($this->mark($this->addCSS("assets/css/print.css", array("media" => "print"))));
    }

    public function onMainContentTopAddJS(HookRenderEvent $event)
    {
        $event->add($this->mark($this->addJS("assets/js/script.js")));
    }

    public function onMainContentTopTrans(HookRenderEvent $event)
    {
        $event->add($this->mark($this->trans("Hodor Hodor", array(), "hooktest")));
        $event->add($this->mark($this->trans("Hello World", array(), "hooktest")));
        $event->add($this->mark($this->trans("Hello %name%", array("%name%" => "Hodor"))));
        $event->add($this->mark($this->trans("Hi %name%", array("%name%" => "Hodor"), "hooktest", 'fr_FR')));
    }

    // == template overriding ====================================================

    public function onMainContentTopOverriding(HookRenderEvent $event)
    {
        $event->add($this->render("override1.html"));
        // redefined in template hooktest in the module
        $event->add($this->render("override2.html"));
        // redefined in template hooktest
        $event->add($this->render("override3.html"));

        $event->add($this->render("override-assets.html"));

    }



    protected function mark($message, $endofline="\n")
    {
        return sprintf("%s %s %s%s", $this->ldelim, $message, $this->rdelim, $endofline);
    }

} 