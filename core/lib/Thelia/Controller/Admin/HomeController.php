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

namespace Thelia\Controller\Admin;

use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Thelia;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;

class HomeController extends BaseAdminController
{
    const RESOURCE_CODE = "admin.home";

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        // Render the edition template.
        return $this->render('home');
    }

    public function loadStatsAjaxAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $data = new \stdClass();

        $month = (int) $this->getRequest()->query->get('month', date('m'));
        $year = (int) $this->getRequest()->query->get('year', date('Y'));

        $data->title = $this->getTranslator()->trans(
            "Stats on %month/%year",
            ['%month' => $month, '%year' => $year]
        );

        /* sales */
        $saleSeries = new \stdClass();
        $saleSeries->color = self::testHexColor('sales_color', '#adadad');
        $saleSeries->data = OrderQuery::getMonthlySaleStats($month, $year);

        /* new customers */
        $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = self::testHexColor('customers_color', '#f39922');
        $newCustomerSeries->data = CustomerQuery::getMonthlyNewCustomersStats($month, $year);

        /* orders */
        $orderSeries = new \stdClass();
        $orderSeries->color = self::testHexColor('orders_color', '#5cb85c');
        $orderSeries->data = OrderQuery::getMonthlyOrdersStats($month, $year);

        /* first order */
        $firstOrderSeries = new \stdClass();
        $firstOrderSeries->color = self::testHexColor('first_orders_color', '#5bc0de');
        $firstOrderSeries->data = OrderQuery::getFirstOrdersStats($month, $year);

        /* cancelled orders */
        $cancelledOrderSeries = new \stdClass();
        $cancelledOrderSeries->color = self::testHexColor('cancelled_orders_color', '#d9534f');
        $cancelledOrderSeries->data = OrderQuery::getMonthlyOrdersStats($month, $year, array(5));


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

    /**
     * @param string $key
     * @param string $default
     * @return string hexadecimal color or default argument
     */
    protected function testHexColor($key, $default)
    {
        $hexColor = $this->getRequest()->query->get($key, $default);

        return preg_match('/^#[a-f0-9]{6}$/i', $hexColor) ? $hexColor : $default;
    }
}
