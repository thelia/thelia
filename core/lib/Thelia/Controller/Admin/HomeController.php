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

    /**
     * Get the latest available Thelia version from the Thelia web site.
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function getLatestTheliaVersion()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $context = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Thelia version ".Thelia::THELIA_VERSION."\r\n".
                    "Referer: ".$this->getRequest()->getSchemeAndHttpHost()."\r\n".
                    "Accept-Language: ".$this->getRequest()->getSession()->getLang()->getCode()."\r\n"
            ]
        ];

        // get the latest version
        $version = @file_get_contents(
            "http://thelia.net/version.php",
            false,
            stream_context_create($context)
        );

        if ($version === false) {
            $version = $this->getTranslator()->trans("Not found");
        } elseif (! preg_match("/^[0-9.]*$/", $version)) {
            $version = $this->getTranslator()->trans("Unavailable");
        }

        return Response::create($version);
    }

    public function loadStatsAjaxAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $data = new \stdClass();

        $data->title = $this->getTranslator()->trans("Stats on %month/%year", array('%month' => $this->getRequest()->query->get('month', date('m')), '%year' => $this->getRequest()->query->get('year', date('Y'))));

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
