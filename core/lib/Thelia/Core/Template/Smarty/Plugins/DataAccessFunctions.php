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

namespace Thelia\Core\Template\Smarty\Plugins;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Model\Base\BrandQuery;
use Thelia\Model\Brand;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\OrderQuery;

use Thelia\Model\ProductQuery;
use Thelia\Model\Tools\ModelCriteriaTools;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\DateTimeFormat;
use Thelia\Cart\CartTrait;

/**
 * Implementation of data access to main Thelia objects (users, cart, etc.)
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class DataAccessFunctions extends AbstractSmartyPlugin
{
    use CartTrait;

    private $securityContext;
    protected $parserContext;
    protected $request;
    protected $dispatcher;
    protected $taxEngine;

    private static $dataAccessCache = array();

    public function __construct(Request $request, SecurityContext $securityContext, TaxEngine $taxEngine, ParserContext $parserContext, ContainerAwareEventDispatcher $dispatcher)
    {
        $this->securityContext = $securityContext;
        $this->parserContext = $parserContext;
        $this->request = $request;
        $this->dispatcher = $dispatcher;
        $this->taxEngine = $taxEngine;
    }

    /**
     * Provides access to the current logged administrator attributes using the accessors.
     *
     * @param  array   $params
     * @param  unknown $smarty
     * @return string  the value of the requested attribute
     */
    public function adminDataAccess($params, &$smarty)
    {
         return $this->dataAccess("Admin User", $params, $this->securityContext->getAdminUser());
    }

     /**
      * Provides access to the current logged customer attributes throught the accessor
      *
      * @param  array $params
      * @param  unknown $smarty
      * @return string the value of the requested attribute
      */
     public function customerDataAccess($params, &$smarty)
     {
         return $this->dataAccess("Customer User", $params, $this->securityContext->getCustomerUser());
     }

    public function productDataAccess($params, &$smarty)
    {
        $productId = $this->request->get('product_id');

        if ($productId !== null) {

            $search = ProductQuery::create()
                ->filterById($productId);

            return $this->dataAccessWithI18n("Product",  $params, $search);
        }
    }

    public function categoryDataAccess($params, &$smarty)
    {
        $categoryId = $this->request->get('category_id');

        if ($categoryId !== null) {

            $search = CategoryQuery::create()
                ->filterById($categoryId);

            return $this->dataAccessWithI18n("Category",  $params, $search);
        }
    }

    public function contentDataAccess($params, &$smarty)
    {
        $contentId = $this->request->get('content_id');

        if ($contentId !== null) {

            $search = ContentQuery::create()
                ->filterById($contentId);

            return $this->dataAccessWithI18n("Content",  $params, $search);
        }
    }

    public function folderDataAccess($params, &$smarty)
    {
        $folderId = $this->request->get('folder_id');

        if ($folderId !== null) {

            $search = FolderQuery::create()
                ->filterById($folderId);

            return $this->dataAccessWithI18n("Folder",  $params, $search);
        }
    }

    public function brandDataAccess($params, &$smarty)
    {
        $brandId = $this->request->get('brand_id');

        if ($brandId !== null) {

            $search = BrandQuery::create()
                ->filterById($brandId);

            return $this->dataAccessWithI18n("Brand",  $params, $search);
        }
    }

    /**
     * currency global data
     *
     * @param $params
     * @param $smarty
     */
    public function currencyDataAccess($params, $smarty)
    {
        $currency = $this->request->getSession()->getCurrency();

        if ($currency) {
            $currencyQuery = CurrencyQuery::create()
                ->filterById($currency->getId());

            return $this->dataAccessWithI18n("Currency", $params, $currencyQuery, array("NAME"));
        }
    }

    public function countryDataAccess($params, $smarty)
    {
        switch ($params["ask"]) {
            case "default":
                $defaultCountry = CountryQuery::create()->filterByByDefault(1)->limit(1);

                return $this->dataAccessWithI18n("defaultCountry", $params, $defaultCountry);
        }
    }

    public function cartDataAccess($params, $smarty)
    {
        if (array_key_exists('currentCountry', self::$dataAccessCache)) {
            $taxCountry = self::$dataAccessCache['currentCountry'];
        } else {
            $taxCountry = $this->taxEngine->getDeliveryCountry();
            self::$dataAccessCache['currentCountry'] = $taxCountry;
        }

        $cart = $this->getCart($this->getDispatcher(), $this->request);
        $result = "";
        switch ($params["attr"]) {
            case "count_product":
                $result = $cart->getCartItems()->count();
                break;
            case "count_item":
                $count_allitem = 0;
                foreach ($cart->getCartItems() as $cartItem) {
                  $count_allitem += $cartItem->getQuantity();
                }
                $result = $count_allitem;
                break;
            case "total_price":
                $result = $cart->getTotalAmount();
                break;
            case "total_taxed_price":
                $result = $cart->getTaxedAmount($taxCountry);
                break;
            case "total_taxed_price_without_discount":
                $result = $cart->getTaxedAmount($taxCountry, false);
                break;
            case "total_vat":
                $result = $cart->getTotalVAT($taxCountry);
                break;                        
        }

        return $result;
    }

    public function orderDataAccess($params, &$smarty)
    {
        $order = $this->request->getSession()->getOrder();
        $attribute = $this->getNormalizedParam($params, array('attribute', 'attrib', 'attr'));
        switch ($attribute) {
            case 'untaxed_postage':
                return $order->getUntaxedPostage();
            case 'postage':
                return $order->getPostage();
            case 'discount':
                return $order->getDiscount();
            case 'delivery_address':
                return $order->getChoosenDeliveryAddress();
            case 'invoice_address':
                return $order->getChoosenInvoiceAddress();
            case 'delivery_module':
                return $order->getDeliveryModuleId();
            case 'payment_module':
                return $order->getPaymentModuleId();
        }

        throw new \InvalidArgumentException(sprintf("%s has no '%s' attribute", 'Order', $attribute));
     }

    /**
     * Lang global data
     *
     * @param $params
     * @param $smarty
     *
     * @return string
     */
    public function langDataAccess($params, $smarty)
    {
        return $this->dataAccess("Lang", $params, $this->request->getSession()->getLang());
    }

    public function configDataAccess($params, $smarty)
    {
        $key = $this->getParam($params, 'key', false);

        if ($key === false) return null;

        $default = $this->getParam($params, 'default', '');

        return ConfigQuery::read($key, $default);
    }

    public function statsAccess($params, $smarty)
    {
        if (false === array_key_exists("key", $params)) {
            throw new \InvalidArgumentException(sprintf("missing key attribute in stats access function"));
        }
        if (false === array_key_exists("startDate", $params) || $params['startDate'] === '') {
            throw new \InvalidArgumentException(sprintf("missing startDate attribute in stats access function"));
        }
        if (false === array_key_exists("endDate", $params) || $params['endDate'] === '') {
            throw new \InvalidArgumentException(sprintf("missing endDate attribute in stats access function"));
        }

        if (false !== array_key_exists("includeShipping", $params) && $params['includeShipping'] == 'false') {
            $includeShipping = false;
        } else {
            $includeShipping = true;
        }

        if ($params['startDate'] == 'today') {
            $startDate = new \DateTime();
            $startDate->setTime(0, 0, 0);
        } elseif ($params['startDate'] == 'yesterday') {
            $startDate = new \DateTime();
            $startDate->setTime(0, 0, 0);
            $startDate->modify('-1 day');
        } elseif ($params['startDate'] == 'this_month') {
            $startDate = new \DateTime();
            $startDate->modify('first day of this month');
            $startDate->setTime(0, 0, 0);
        } elseif ($params['startDate'] == 'last_month') {
            $startDate = new \DateTime();
            $startDate->modify('first day of last month');
            $startDate->setTime(0, 0, 0);
        } elseif ($params['startDate'] == 'this_year') {
            $startDate = new \DateTime();
            $startDate->modify('first day of January this year');
            $startDate->setTime(0, 0, 0);
        } elseif ($params['startDate'] == 'last_year') {
            $startDate = new \DateTime();
            $startDate->modify('first day of December last year');
            $startDate->setTime(0, 0, 0);
        } else {
            try {
                $startDate = new \DateTime($params['startDate']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf("invalid startDate attribute '%s' in stats access function", $params['startDate']));
            }
        }

        if ($params['endDate'] == 'today') {
            $endDate = new \DateTime();
            $endDate->setTime(0, 0, 0);
        } elseif ($params['endDate'] == 'yesterday') {
            $endDate = new \DateTime();
            $endDate->setTime(0, 0, 0);
            $endDate->modify('-1 day');
        } elseif ($params['endDate'] == 'this_month') {
            $endDate = new \DateTime();
            $endDate->modify('last day of this month');
            $endDate->setTime(0, 0, 0);
        } elseif ($params['endDate'] == 'last_month') {
            $endDate = new \DateTime();
            $endDate->modify('last day of last month');
            $endDate->setTime(0, 0, 0);
        } elseif ($params['endDate'] == 'this_year') {
            $endDate = new \DateTime();
            $endDate->modify('last day of December this year');
            $endDate->setTime(0, 0, 0);
        } elseif ($params['endDate'] == 'last_year') {
            $endDate = new \DateTime();
            $endDate->modify('last day of January last year');
            $endDate->setTime(0, 0, 0);
        } else {
            try {
                $endDate = new \DateTime($params['endDate']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf("invalid endDate attribute '%s' in stats access function", $params['endDate']));
            }
        }

        switch ($params['key']) {
            case 'sales' :
                return OrderQuery::getSaleStats($startDate, $endDate, $includeShipping);
            case 'orders' :
                return OrderQuery::getOrderStats($startDate, $endDate, array(1,2,3,4));

        }

        throw new \InvalidArgumentException(sprintf("invalid key attribute '%s' in stats access function", $params['key']));
    }

    /**
     * @param               $objectLabel
     * @param               $params
     * @param ModelCriteria $search
     * @param array         $columns
     * @param null          $foreignTable
     * @param string        $foreignKey
     *
     * @return string
     */
    protected function dataAccessWithI18n($objectLabel, $params, ModelCriteria $search, $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'), $foreignTable = null, $foreignKey = 'ID')
    {
        if (array_key_exists('data_' . $objectLabel, self::$dataAccessCache)) {
            $data = self::$dataAccessCache['data_' . $objectLabel];
        } else {
            $lang = $this->getNormalizedParam($params, array('lang'));
            if ($lang === null) {
                $lang = $this->request->getSession()->getLang()->getId();
            }

            ModelCriteriaTools::getI18n(
                false,
                $lang,
                $search,
                $this->request->getSession()->getLang()->getLocale(),
                $columns,
                $foreignTable,
                $foreignKey,
                true
            );

            $data = $search->findOne();

            self::$dataAccessCache['data_' . $objectLabel] = $data;
        }

        if ($data !== null) {
            $noGetterData = array();

            foreach ($columns as $column) {
                $noGetterData[$column] = $data->getVirtualColumn('i18n_' . $column);
            }

            return $this->dataAccess($objectLabel, $params, $data, $noGetterData);
        } else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @param       $objectLabel
     * @param       $params
     * @param       $data
     * @param array $noGetterData
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function dataAccess($objectLabel, $params, $data, $noGetterData = array())
    {
        $attribute = $this->getNormalizedParam($params, array('attribute', 'attrib', 'attr'));

        if (! empty($attribute)) {

            if (null != $data) {

                $keyAttribute = strtoupper($attribute);
                if (array_key_exists($keyAttribute, $noGetterData)) {
                    return $noGetterData[$keyAttribute];
                }

                $getter = sprintf("get%s", $this->underscoreToCamelcase($attribute));
                if (method_exists($data, $getter)) {
                    $return =  $data->$getter();

                    if ($return instanceof \DateTime) {
                        if (array_key_exists("format", $params)) {
                            $format = $params["format"];
                        } else {
                            $format = DateTimeFormat::getInstance($this->request)->getFormat(array_key_exists("output", $params) ? $params["output"] : null);
                        }

                        $return = $return->format($format);
                    }

                    return $return;
                }

                throw new \InvalidArgumentException(sprintf("%s has no '%s' attribute", $objectLabel, $attribute));

            }
        }

        return '';
    }

    /**
     * Transcode an underscored string into a camel-cased string, eg. default_folder into DefaultFolder
     *
     * @param string $str the string to convert from underscore to camel-case
     *
     * @return string the camel cased string.
     */
    private function underscoreToCamelcase($str)
    {
        // Split string in words.
        $words = explode('_', strtolower($str));

        $return = '';

        foreach ($words as $word) {
            $return .= ucfirst(trim($word));
        }

        return $return;
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'admin', $this, 'adminDataAccess'),
            new SmartyPluginDescriptor('function', 'customer', $this, 'customerDataAccess'),
            new SmartyPluginDescriptor('function', 'product', $this, 'productDataAccess'),
            new SmartyPluginDescriptor('function', 'category', $this, 'categoryDataAccess'),
            new SmartyPluginDescriptor('function', 'content', $this, 'contentDataAccess'),
            new SmartyPluginDescriptor('function', 'folder', $this, 'folderDataAccess'),
            new SmartyPluginDescriptor('function', 'brand', $this, 'brandDataAccess'),
            new SmartyPluginDescriptor('function', 'currency', $this, 'currencyDataAccess'),
            new SmartyPluginDescriptor('function', 'country', $this, 'countryDataAccess'),
            new SmartyPluginDescriptor('function', 'lang', $this, 'langDataAccess'),
            new SmartyPluginDescriptor('function', 'cart', $this, 'cartDataAccess'),
            new SmartyPluginDescriptor('function', 'order', $this, 'orderDataAccess'),
            new SmartyPluginDescriptor('function', 'config', $this, 'configDataAccess'),
            new SmartyPluginDescriptor('function', 'stats', $this, 'statsAccess'),
        );
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }
}
