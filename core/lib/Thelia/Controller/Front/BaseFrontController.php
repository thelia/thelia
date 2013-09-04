<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
namespace Thelia\Controller\Front;

use Thelia\Controller\BaseController;

class BaseFrontController extends BaseController
{
    /**
     * Return the route path defined for the givent route ID
     *
     * @param string $routeId a route ID, as defines in Config/Resources/routing/front.xml
     *
     * @see \Thelia\Controller\BaseController::getRouteFromRouter()
     */
    protected function getRoute($routeId) {
        return $this->getRouteFromRouter('router.front', $routeId);
    }

    /**
     * Redirect to à route ID related URL
     *
     * @param unknown $routeId the route ID, as found in Config/Resources/routing/admin.xml
     * @param unknown $urlParameters the URL parametrs, as a var/value pair array
     */
    public function redirectToRoute($routeId, $urlParameters = array()) {
        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute($routeId), $urlParameters));
    }
}
