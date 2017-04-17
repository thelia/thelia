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

namespace HookAdminHome\Controller;

use HookAdminHome\HookAdminHome;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;

/**
 * Class HomeController
 * @package HookAdminHome\Controller
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class HomeController extends BaseAdminController
{
    /**
     * Key prefix for stats cache
     */
    const STATS_CACHE_KEY = "stats";

    const RESOURCE_CODE = "admin.home";

    public function loadStatsAjaxAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $cacheExpire = ConfigQuery::getAdminCacheHomeStatsTTL();

        /** @var AdapterInterface $cacheAdapter */
        $cacheAdapter = $this->container->get('thelia.cache');

        $month = (int) $this->getRequest()->query->get('month', date('m'));
        $year = (int) $this->getRequest()->query->get('year', date('Y'));

        $cacheKey = self::STATS_CACHE_KEY . "_" . $month . "_" . $year;

        $cacheItem = $cacheAdapter->getItem($cacheKey);

        // force flush
        if ($this->getRequest()->query->get('flush', "0")) {
            $cacheAdapter->deleteItem($cacheItem);
        }

        if (!$cacheItem->isHit()) {
            $data = $this->getStatus($month, $year);

            $cacheItem->set(json_encode($data));
            $cacheItem->expiresAfter($cacheExpire);

            if ($cacheExpire) {
                $cacheAdapter->save($cacheItem);
            }
        }

        return $this->jsonResponse($cacheItem->get());
    }
    
    public function blockMonthSalesStatistics($month, $year)
    {
        $baseDate = sprintf("%04d-%02d", $year, $month);

        $startDate = "$baseDate-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $prevMonthStartDate = date('Y-m-01', strtotime("$baseDate -1 month"));
        $prevMonthEndDate = date("Y-m-t", strtotime($prevMonthStartDate));
        
        return $this->render('block-month-sales-statistics', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'prevMonthStartDate' => $prevMonthStartDate,
            'prevMonthEndDate' => $prevMonthEndDate,
        ]);
    }

    /**
     * @param int $month
     * @param int $year
     * @return \stdClass
     */
    protected function getStatus($month, $year)
    {
        $data = new \stdClass();

        $data->title = $this->getTranslator()->trans(
            "Stats on %month/%year",
            ['%month' => $month, '%year' => $year],
            HookAdminHome::DOMAIN_NAME
        );

        $data->series = [];

        /* sales */
        $data->series[] = $saleSeries = new \stdClass();
        $saleSeries->color = self::testHexColor('sales_color', '#adadad');
        $saleSeries->data = OrderQuery::getMonthlySaleStats($month, $year);
        $saleSeries->valueFormat = "%1.2f " . Currency::getDefaultCurrency()->getSymbol();

        /* new customers */
        $data->series[] = $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = self::testHexColor('customers_color', '#f39922');
        $newCustomerSeries->data = CustomerQuery::getMonthlyNewCustomersStats($month, $year);
        $newCustomerSeries->valueFormat = "%d";

        /* orders */
        $data->series[] = $orderSeries = new \stdClass();
        $orderSeries->color = self::testHexColor('orders_color', '#5cb85c');
        $orderSeries->data = OrderQuery::getMonthlyOrdersStats($month, $year);
        $orderSeries->valueFormat = "%d";

        /* first order */
        $data->series[] = $firstOrderSeries = new \stdClass();
        $firstOrderSeries->color = self::testHexColor('first_orders_color', '#5bc0de');
        $firstOrderSeries->data = OrderQuery::getFirstOrdersStats($month, $year);
        $firstOrderSeries->valueFormat = "%d";

        /* cancelled orders */
        $data->series[] = $cancelledOrderSeries = new \stdClass();
        $cancelledOrderSeries->color = self::testHexColor('cancelled_orders_color', '#d9534f');
        $cancelledOrderSeries->data = OrderQuery::getMonthlyOrdersStats($month, $year, array(5));
        $cancelledOrderSeries->valueFormat = "%d";

        return $data;
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
