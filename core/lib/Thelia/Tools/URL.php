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

namespace Thelia\Tools;

use Thelia\Model\ConfigQuery;

class URL
{
	/**
	 * Returns the Absolute URL for a given path relative to web root
     *
     * @param string         $path         the relative path
     * @param mixed          $parameters    An array of parameters
     *
     * @return string The generated URL
     */
    public static function absoluteUrl($path, array $parameters = array())
    {
    	// Already absolute ?
    	if (substr($path, 0, 4) != 'http')
    		$base = ConfigQuery::read('base_url', '/') . ltrim($path, '/');
    	else
    		$base = $path;

    	$queryString = '';

    	foreach($parameters as $name => $value) {
    		$queryString = sprintf("%s=%s&", urlencode($name), urlencode($value));
    	}

    	if ('' !== $queryString = rtrim($queryString, "&")) $queryString = '?' . $queryString;

    	return $base . $queryString;
    }

	/**
	 * Returns the Absolute URL to a view
     *
     * @param string         $viewName      the view name (e.g. login for login.html)
     * @param mixed          $parameters    An array of parameters
     *
     * @return string The generated URL
     */
     public static function viewUrl($viewName, array $parameters = array()) {
     	$path = sprintf("%s?view=%s", ConfigQuery::read('base_url', '/'), $viewName);

     	return self::absoluteUrl($path, $parameters);
     }
}
