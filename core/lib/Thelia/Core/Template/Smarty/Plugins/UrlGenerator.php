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

use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Tools\URL;
use Thelia\Core\HttpFoundation\Request;

class UrlGenerator extends AbstractSmartyPlugin
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

	/**
     * Process url generator function
     *
     * @param  array $params
     * @param  unknown $smarty
     * @return string no text is returned.
     */
    public function generateUrlFunction($params, &$smarty)
    {
    	// the path to process
   		$path = trim($params['path']);

   		return URL::absoluteUrl($path, $this->getArgsFromParam($params));
     }

     /**
      * Process view url generator function
      *
      * @param  array $params
      * @param  unknown $smarty
      * @return string no text is returned.
      */
     public function generateViewUrlFunction($params, &$smarty)
     {
     	// the view name (without .html)
     	$view = trim($params['view']);

      	// the related action (optionale)
     	$action = trim($params['action']);

     	$args = $this->getArgsFromParam($params);

     	if (! empty($action)) $args['action'] = $action;

     	return URL::viewUrl($view, $args);
     }

     /**
      * Get URL parameters array from a comma separated list or arguments in the
      * parameters.
      *
      * @param array $params Smarty function params
      * @return array the parameters array (either emply, of valued)
      */
     private function getArgsFromParam($params) {

     	if (isset($params['args']))
     		return explode($params['args'], ',');

     	return array();
     }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'url', $this, 'generateUrlFunction'),
            new SmartyPluginDescriptor('function', 'viewurl', $this, 'generateViewUrlFunction')
        );
    }
}
