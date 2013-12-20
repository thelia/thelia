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

namespace Thelia\Controller\Admin;

use Thelia\Core\Security\AccessManager;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;

class HomeController extends BaseAdminController
{
    const RESOURCE_CODE = "admin.home";

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) return $response;

        // Render the edition template.
        return $this->render('home');
    }

    public function loadStatsAjaxAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) return $response;
        
        $data = new \stdClass();

        $data->title = "Stats on " . $this->getRequest()->query->get('month', date('m')) . "/" . $this->getRequest()->query->get('year', date('Y'));

        /* sales */
        $saleSeries = new \stdClass();
        $saleSeries->color = $this->getRequest()->query->get('sales_color', '#adadad');
        $saleSeries->data = OrderQuery::getMonthlySaleStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
        );

        /* new customers */
        $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = $this->getRequest()->query->get('customers_color', '#f39922');
        $newCustomerSeries->data = CustomerQuery::getMonthlyNewCustomersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
        );

        /* orders */
        $orderSeries = new \stdClass();
        $orderSeries->color = $this->getRequest()->query->get('orders_color', '#5cb85c');
        $orderSeries->data = OrderQuery::getMonthlyOrdersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
        );

        /* first order */
        $firstOrderSeries = new \stdClass();
        $firstOrderSeries->color = $this->getRequest()->query->get('first_orders_color', '#5bc0de');
        $firstOrderSeries->data = OrderQuery::getFirstOrdersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y'))
        );

        /* cancelled orders */
        $cancelledOrderSeries = new \stdClass();
        $cancelledOrderSeries->color = $this->getRequest()->query->get('cancelled_orders_color', '#d9534f');
        $cancelledOrderSeries->data = OrderQuery::getMonthlyOrdersStats(
            $this->getRequest()->query->get('month', date('m')),
            $this->getRequest()->query->get('year', date('Y')),
            array(5)
        );


        $data->series = array(
            $saleSeries,
            $newCustomerSeries,
            $orderSeries,
            $firstOrderSeries,
            $cancelledOrderSeries,
        );

        $json = json_encode($data);

        return $this->jsonResponse($json);
    }
}
