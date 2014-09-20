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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Plugin for smarty defining blocks allowing to get flash message
 * A flash message is a variable, array, object stored in session under the flashMessage key
 * ex $SESSION['flashMessage']['myKey']
 *
 * blocks :
 *  - {flashMessage key="myKey"} ... {/flashMessage}
 *
 * Class Form
 *
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class FlashMessage extends AbstractSmartyPlugin
{
    /** @var Request Request service */
    protected $request;

    /**
     * Constructor
     *
     * @param Request $request Request service
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get FlashMessage
     * And clean session from this key
     *
     * @param array                     $params   Block parameters
     * @param mixed                     $content  Block content
     * @param \Smarty_Internal_Template $template Template
     * @param bool                      $repeat   Control how many times
     *                                            the block is displayed
     *
     * @return mixed
     */
    public function getFlashMessage($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        if ($repeat) {
            if (false !== $key = $this->getParam($params, 'key', false)) {
                $flashBag = $this->request->getSession()->get('flashMessage');

                $template->assign('value', $flashBag[$key]);

                // Reset flash message (can be read once)
                unset($flashBag[$key]);

                $this->request->getSession()->set('flashMessage', $flashBag);
            }
        } else {
            return $content;
        }
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("block", "flashMessage", $this, "getFlashMessage")
        );
    }
}
