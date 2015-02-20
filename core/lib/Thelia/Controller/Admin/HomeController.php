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

use Doctrine\Common\Cache\FilesystemCache;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Thelia;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;

class HomeController extends BaseAdminController
{
    /**
     * Folder name for stats cache
     */
    const STATS_CACHE_DIR = "stats";

    /**
     * Key prefix for stats cache
     */
    const STATS_CACHE_KEY = "stats";

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

        $cacheExpire = ConfigQuery::getAdminCacheHomeStatsTTL();

        $cacheContent = false;

        if ($cacheExpire) {
            $context = "_" . $this->getRequest()->query->get('month', date('m')) . "_" . $this->getRequest()->query->get('year', date('Y'));

            $cacheKey = self::STATS_CACHE_KEY . $context;

            $cacheDriver = new FilesystemCache($this->getCacheDir());

            if (!$this->getRequest()->query->get('flush', "0")) {
                $cacheContent = $cacheDriver->fetch($cacheKey);
            } else {
                $cacheDriver->delete($cacheKey);
            }
        }

        if ($cacheContent === false) {
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

            $cacheContent = json_encode($data);

            if ($cacheExpire) {
                $cacheDriver->save($cacheKey, $cacheContent, $cacheExpire);
            }
        }

        return $this->jsonResponse($cacheContent);
    }

    /**
     * get the cache directory for sitemap
     *
     * @return mixed|string
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->container->getParameter("kernel.cache_dir");
        $cacheDir = rtrim($cacheDir, '/');
        $cacheDir .= DIRECTORY_SEPARATOR . self::STATS_CACHE_DIR . DIRECTORY_SEPARATOR;

        return $cacheDir;
    }
}
