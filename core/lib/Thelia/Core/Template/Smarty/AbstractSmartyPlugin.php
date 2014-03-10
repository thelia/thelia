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

namespace Thelia\Core\Template\Smarty;

/**
 *
 * The class all Smarty Thelia plugin shoud extend
 *
 * Class AbstractSmartyPlugin
 * @package Thelia\Core\Template\Smarty
 */
abstract class AbstractSmartyPlugin
{
    /**
     * Explode a comma separated list in a array, trimming all array elements
     *
     * @param  mixed  $commaSeparatedValues
     * @return mixed:
     */
    protected function explode($commaSeparatedValues)
    {
        if (null === $commaSeparatedValues) {
            return array();
        }

        $array = explode(',', $commaSeparatedValues);

        if (array_walk($array, function (&$item) {
            $item = strtoupper(trim($item));
        })) {
            return $array;
        }

        return array();
    }

    /**
     * Get a function or block parameter value, and normalize it, trimming balnks and
     * making it lowercase
     *
     * @param  array $params  the parameters array
     * @param  mixed $name    as single parameter name, or an array of names. In this case, the first defined parameter is returned. Use this for aliases (context, ctx, c)
     * @param  mixed $default the defaut value if parameter is missing (default to null)
     * @return mixed the parameter value, or the default value if it is not found.
     */
    public function getNormalizedParam($params, $name, $default = null)
    {
        $value = $this->getParam($params, $name, $default);

        if (is_string($value)) $value = strtolower(trim($value));
        return $value;
    }

    /**
     * Get a function or block parameter value
     *
     * @param  array $params  the parameters array
     * @param  mixed $name    as single parameter name, or an array of names. In this case, the first defined parameter is returned. Use this for aliases (context, ctx, c)
     * @param  mixed $default the defaut value if parameter is missing (default to null)
     * @return mixed the parameter value, or the default value if it is not found.
     */
    public function getParam($params, $name, $default = null)
    {
        if (is_array($name)) {
            foreach ($name as $test) {
                if (isset($params[$test])) {
                    return $params[$test];
                }
            }
        } elseif (isset($params[$name])) {
            return $params[$name];
        }

        return $default;
    }

    /**
     * @return an array of SmartyPluginDescriptor
     */
    abstract public function getPluginDescriptors();
}
