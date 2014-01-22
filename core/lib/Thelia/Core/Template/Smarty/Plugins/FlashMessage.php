<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
