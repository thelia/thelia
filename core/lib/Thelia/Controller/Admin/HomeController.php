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
use Thelia\Core\Security\AccessManager;
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

        $month = (int) $this->getRequest()->query->get('month', date('m'));
        $year = (int) $this->getRequest()->query->get('year', date('Y'));

        if ($cacheExpire) {
            $context = "_" . $month . "_" . $year;

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
