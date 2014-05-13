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

namespace Thelia\Core\Template\Smarty\Plugins;

use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Thelia\Log\Tlog;

/**
 * Class Hook
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Hook extends AbstractSmartyPlugin
{

    private $dispatcher;

    public function __construct(ContainerAwareEventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Generates the content of the hook
     *
     * @param  array   $params
     * @param  unknown $smarty
     * @return string  no text is returned.
     */
    public function processHookFunction($params, &$smarty)
    {
        // The current order of the table
        $hookName = $this->getParam($params, 'name');

        Tlog::getInstance()->addDebug("_HOOK_ process hook : " . $hookName);

        $event = new HookRenderEvent($hookName);

        $event = $this->getDispatcher()->dispatch('hook.before.' . $hookName, $event);
        $event = $this->getDispatcher()->dispatch('hook.' . $hookName, $event);
        $event = $this->getDispatcher()->dispatch('hook.after.' . $hookName, $event);

        $content = "";
        foreach ($event->getFragments() as $fragment) {
            $content .= $fragment->getContent();
        }

        return $content;
    }

    /**
     * Generates the content of the hook
     *
     * @param  array   $params
     * @param  unknown $smarty
     * @return string  no text is returned.
     */
    public function processHookBlock($params, $content, $template, &$repeat)
    {
        //throw new NotImplementedException();
        return "";
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'hook', $this, 'processHookFunction'),
            new SmartyPluginDescriptor('block', 'hook', $this, 'processHookBlock')
        );
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

}
