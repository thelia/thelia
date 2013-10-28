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

use Symfony\Component\Routing\Router;
use Thelia\Controller\BaseController;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Tools\URL;

class BaseFrontController extends BaseController
{
    /**
     * Return the route path defined for the givent route ID
     *
     * @param string $routeId a route ID, as defines in Config/Resources/routing/front.xml
     *
     * @see \Thelia\Controller\BaseController::getRouteFromRouter()
     */
    protected function getRoute($routeId, $parameters = array(), $referenceType = Router::ABSOLUTE_PATH)
    {
        return $this->getRouteFromRouter('router.front', $routeId, $parameters, $referenceType);
    }

    /**
     * Redirect to à route ID related URL
     *
     * @param unknown $routeId       the route ID, as found in Config/Resources/routing/admin.xml
     * @param unknown $urlParameters the URL parametrs, as a var/value pair array
     */
    public function redirectToRoute($routeId, $urlParameters = array(), $referenceType = Router::ABSOLUTE_PATH)
    {
        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute($routeId, array(), $referenceType), $urlParameters));
    }

    public function checkAuth()
    {
        if ($this->getSecurityContext()->hasCustomerUser() === false) {
            $this->redirectToRoute('customer.login.process');
        }
    }

    protected function checkCartNotEmpty()
    {
        $cart = $this->getSession()->getCart();
        if ($cart===null || $cart->countCartItems() == 0) {
            $this->redirectToRoute('cart.view');
        }
    }

    protected function checkValidDelivery()
    {
        $order = $this->getSession()->getOrder();
        if (null === $order || null === $order->chosenDeliveryAddress || null === $order->getDeliveryModuleId() || null === AddressQuery::create()->findPk($order->chosenDeliveryAddress) || null === ModuleQuery::create()->findPk($order->getDeliveryModuleId())) {
            $this->redirectToRoute("order.delivery");
        }
    }

    protected function checkValidInvoice()
    {
        $order = $this->getSession()->getOrder();
        if (null === $order || null === $order->chosenInvoiceAddress || null === $order->getPaymentModuleId() || null === AddressQuery::create()->findPk($order->chosenInvoiceAddress) || null === ModuleQuery::create()->findPk($order->getPaymentModuleId())) {
            $this->redirectToRoute("order.invoice");
        }
    }
}
