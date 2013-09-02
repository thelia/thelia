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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;

/**
 * Class Format
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Format extends AbstractSmartyPlugin
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * return date in expected format
     *
     * ex : {format_date date=$dateTimeObject format="Y-m-d H:i:s"}
     *
     * @param array $params
     * @param null $template
     * @return string
     */
    public function formatDate($params, $template = null)
    {
        $date = $params["date"];

        if(!$date instanceof \DateTime) {
            return "";
        }

        $format = null;
        $output = array_key_exists("output", $params) ? $params["output"] : null;

        if (array_key_exists("format", $params)) {
            $format = $params["format"];
        } else {
            $session = $this->request->getSession();
            $lang = $session->getLang();

            if($lang) {
                switch ($output) {
                    case "date" :
                        $format = $lang->getDateFormat();
                        break;
                    case "time" :
                        $format = $lang->getTimeFormat();
                        break;
                    default:
                    case "datetime" :
                        $format = $lang->getDateTimeFormat();
                        break;
                }
            }
        }

        return $date->format($format);

    }

    public function formatNumber($params, $template = null)
    {

    }

    /**
     * @return an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("function", "format_date", $this, "formatDate"),
            new SmartyPluginDescriptor("function", "format_number", $this, "formatNumber")
        );
    }
}