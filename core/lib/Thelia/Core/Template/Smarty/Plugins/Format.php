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
use Thelia\Core\Template\Smarty\Exception\SmartyPluginException;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;

/**
 *
 * format_date and format_date smarty function.
 *
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
     * available parameters :
     *  date => DateTime objet (mandatory)
     *  format => expected format
     *  output => list of default system format. Values available :
     *      date => date format
     *      time => time format
     *      datetime => datetime format (default)
     *
     * ex :
     *  {format_date date=$dateTimeObject format="Y-m-d H:i:s"} will output the format with specific format
     *  {format_date date=$dateTimeObject output="date"} will output the date using the default date system format
     *  {format_date date=$dateTimeObject} will output with the default datetime system format
     *
     * @param array $params
     * @param null $template
     * @throws \Thelia\Core\Template\Smarty\Exception\SmartyPluginException
     * @return string
     */
    public function formatDate($params, $template = null)
    {

        if (array_key_exists("date", $params) === false) {
            throw new SmartyPluginException("date is a mandatory parameter in format_date function");
        }

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

    /**
     *
     * display numbers in expected format
     *
     * available parameters :
     *  number => int or float number
     *  decimals => how many decimals format expected
     *  dec_point => separator for the decimal point
     *  thousands_sep => thousands separator
     *
     *
     * @param $params
     * @param null $template
     * @throws \Thelia\Core\Template\Smarty\Exception\SmartyPluginException
     * @return string the expected number formatted
     */
    public function formatNumber($params, $template = null)
    {
        if (array_key_exists("number", $params) ===  false) {
            throw new SmartyPluginException("number is a mandatory parameter in format_number function");
        }

        $lang = $this->request->getSession()->getLang();

        $number = $params["number"];
        $decimals = array_key_exists("decimals", $params) ? $params["decimals"] : $lang->getDecimals();
        $decPoint = array_key_exists("dec_point", $params) ? $params["dec_point"] : $lang->getDecimalSeparator();
        $thousandsSep = array_key_exists("thousands_sep", $params) ? $params["thousands_sep"] : $lang->getThousandsSeparator();

        return number_format($number, $decimals, $decPoint, $thousandsSep);
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