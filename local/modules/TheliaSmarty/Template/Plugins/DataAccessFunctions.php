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

namespace TheliaSmarty\Template\Plugins;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserContext;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Base\BrandQuery;
use Thelia\Model\Cart;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Tools\ModelCriteriaTools;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\DateTimeFormat;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Implementation of data access to main Thelia objects (users, cart, etc.)
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class DataAccessFunctions extends AbstractSmartyPlugin
{
    /** @var SecurityContext */
    private $securityContext;

    /** @var ParserContext */
    protected $parserContext;

    /** @var RequestStack */
    protected $requestStack;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var TaxEngine */
    protected $taxEngine;

    /** @var  CouponManager */
    protected $couponManager;

    private static $dataAccessCache = array();

    public function __construct(
        RequestStack $requestStack,
        SecurityContext $securityContext,
        TaxEngine $taxEngine,
        ParserContext $parserContext,
        EventDispatcherInterface $dispatcher,
        CouponManager $couponManager
    ) {
        $this->securityContext = $securityContext;
        $this->parserContext = $parserContext;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->taxEngine = $taxEngine;
        $this->couponManager = $couponManager;
    }

    /**
     * Provides access to the current logged administrator attributes using the accessors.
     *
     * @param  array   $params
     * @param  \Smarty $smarty
     * @return string  the value of the requested attribute
     */
    public function adminDataAccess($params, &$smarty)
    {
        return $this->dataAccess("Admin User", $params, $this->securityContext->getAdminUser());
    }

    /**
     * Provides access to the current logged customer attributes thought the accessor
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function customerDataAccess($params, &$smarty)
    {
        return $this->dataAccess("Customer User", $params, $this->securityContext->getCustomerUser());
    }

    /**
     * Provides access to an attribute of the current product
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function productDataAccess($params, &$smarty)
    {
        $productId = $this->getRequest()->get('product_id');

        if ($productId !== null) {
            return $this->dataAccessWithI18n(
                "Product",
                $params,
                ProductQuery::create()->filterByPrimaryKey($productId)
            );
        }

        return '';
    }

    /**
     * Provides access to an attribute of the current category
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function categoryDataAccess($params, &$smarty)
    {
        $categoryId = $this->getRequest()->get('category_id');

        if ($categoryId === null) {
            $productId = $this->getRequest()->get('product_id');

            if ($productId !== null) {
                if (null !== $product = ProductQuery::create()->findPk($productId)) {
                    $categoryId = $product->getDefaultCategoryId();
                }
            }
        }

        if ($categoryId !== null) {
            return $this->dataAccessWithI18n(
                "Category",
                $params,
                CategoryQuery::create()->filterByPrimaryKey($categoryId)
            );
        }

        return '';
    }

    /**
     * Provides access to an attribute of the current content
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function contentDataAccess($params, &$smarty)
    {
        $contentId = $this->getRequest()->get('content_id');

        if ($contentId !== null) {
            return $this->dataAccessWithI18n(
                "Content",
                $params,
                ContentQuery::create()->filterByPrimaryKey($contentId)
            );
        }

        return '';
    }

    /**
     * Provides access to an attribute of the current folder
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function folderDataAccess($params, &$smarty)
    {
        $folderId = $this->getRequest()->get('folder_id');

        if ($folderId === null) {
            $contentId = $this->getRequest()->get('content_id');

            if ($contentId !== null) {
                if (null !== $content = ContentQuery::create()->findPk($contentId)) {
                    $folderId = $content->getDefaultFolderId();
                }
            }
        }

        if ($folderId !== null) {
            return $this->dataAccessWithI18n(
                "Folder",
                $params,
                FolderQuery::create()->filterByPrimaryKey($folderId)
            );
        }

        return '';
    }

    /**
     * Provides access to an attribute of the current brand
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function brandDataAccess($params, &$smarty)
    {
        $brandId = $this->getRequest()->get('brand_id');

        if ($brandId === null) {
            $productId = $this->getRequest()->get('product_id');

            if ($productId !== null) {
                if (null !== $product = ProductQuery::create()->findPk($productId)) {
                    $brandId = $product->getBrandId();
                }
            }
        }

        if ($brandId !== null) {
            return $this->dataAccessWithI18n(
                "Brand",
                $params,
                BrandQuery::create()->filterByPrimaryKey($brandId)
            );
        }

        return '';
    }


    /**
     * Provides access to an attribute of the current currency
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function currencyDataAccess($params, $smarty)
    {
        $currency = $this->getSession()->getCurrency();

        if ($currency) {
            return $this->dataAccessWithI18n(
                "Currency",
                $params,
                CurrencyQuery::create()->filterByPrimaryKey($currency->getId()),
                array("NAME")
            );
        }

        return '';
    }

    /**
     * Provides access to an attribute of the default country
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function countryDataAccess($params, $smarty)
    {
        switch ($params["ask"]) {
            case "default":
                return $this->dataAccessWithI18n(
                    "defaultCountry",
                    $params,
                    CountryQuery::create()->filterByByDefault(1)->limit(1)
                );
        }

        return '';
    }

    /**
     * Provides access to an attribute of the cart
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function cartDataAccess($params, $smarty)
    {
        /** @var Country $taxCountry */
        if (array_key_exists('currentCountry', self::$dataAccessCache)) {
            $taxCountry = self::$dataAccessCache['currentCountry'];
        } else {
            $taxCountry = $this->taxEngine->getDeliveryCountry();
            self::$dataAccessCache['currentCountry'] = $taxCountry;
        }

        /** @var State $taxState */
        if (array_key_exists('currentState', self::$dataAccessCache)) {
            $taxState = self::$dataAccessCache['currentState'];
        } else {
            $taxState = $this->taxEngine->getDeliveryState();
            self::$dataAccessCache['currentState'] = $taxState;
        }

        /** @var Cart $cart */
        $cart = $this->getSession()->getSessionCart($this->dispatcher);

        $result = "";
        switch ($params["attr"]) {
            case "count_product":
            case "product_count":
                $result = $cart->getCartItems()->count();
                break;
            case "count_item":
            case "item_count":
                $count_allitem = 0;
                foreach ($cart->getCartItems() as $cartItem) {
                    $count_allitem += $cartItem->getQuantity();
                }
                $result = $count_allitem;
                break;
            case "total_price":
            case "total_price_with_discount":
                $result = $cart->getTotalAmount(true);
                break;
            case "total_price_without_discount":
                $result = $cart->getTotalAmount(false);
                break;
            case "total_taxed_price":
            case "total_taxed_price_with_discount":
                $result = $cart->getTaxedAmount($taxCountry, true, $taxState);
                break;
            case "total_taxed_price_without_discount":
                $result = $cart->getTaxedAmount($taxCountry, false, $taxState);
                break;
            case "is_virtual":
            case "contains_virtual_product":
                $result = $cart->isVirtual();
                break;
            case "total_vat":
            case 'total_tax_amount':
                $result = $cart->getTotalVAT($taxCountry);
                break;
            case "weight":
                $result = $cart->getWeight();
                break;
        }

        return $result;
    }

    public function couponDataAccess($params, &$smarty)
    {
        /** @var Order $order */
        $order = $this->getSession()->getOrder();
        $attribute = $this->getNormalizedParam($params, array('attribute', 'attrib', 'attr'));

        switch ($attribute) {
            case 'has_coupons':
                return count($this->couponManager->getCouponsKept()) > 0;
            case 'coupon_count':
                return count($this->couponManager->getCouponsKept());
            case 'coupon_list':
                $orderCoupons = [];
                /** @var CouponInterface $coupon */
                foreach($this->couponManager->getCouponsKept() as $coupon) {
                    $orderCoupons[] = $coupon->getCode();
                }
                return $orderCoupons;
            case 'is_delivery_free':
                return $this->couponManager->isCouponRemovingPostage($order);
        }

        throw new \InvalidArgumentException(sprintf("%s has no '%s' attribute", 'Order', $attribute));
    }

    /**
     * Provides access to an attribute of the current order
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */
    public function orderDataAccess($params, &$smarty)
    {
        /** @var Order $order */
        $order = $this->getSession()->getOrder();
        $attribute = $this->getNormalizedParam($params, array('attribute', 'attrib', 'attr'));
        switch ($attribute) {
            case 'untaxed_postage':
                return $order->getUntaxedPostage();
            case 'postage':
                return $order->getPostage();
            case 'postage_tax':
                return $order->getPostageTax();
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
            case 'has_virtual_product':
                return $order->hasVirtualProduct();

        }

        throw new \InvalidArgumentException(sprintf("%s has no '%s' attribute", 'Order', $attribute));
    }

    /**
     * Provides access to an attribute of the current language
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */

    public function langDataAccess($params, $smarty)
    {
        return $this->dataAccess("Lang", $params, $this->getSession()->getLang());
    }

    public function configDataAccess($params, $smarty)
    {
        $key = $this->getParam($params, 'key', false);

        if ($key === false) {
            return null;
        }

        $default = $this->getParam($params, 'default', '');

        return ConfigQuery::read($key, $default);
    }

    /**
     * Provides access to a module configuration value
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the configuration value
     */

    public function moduleConfigDataAccess($params, $smarty)
    {
        $key = $this->getParam($params, 'key', false);
        $moduleCode = $this->getParam($params, 'module', false);
        $locale = $this->getParam($params, 'locale');

        if (null === $locale) {
            $locale = $this->getSession()->getLang()->getLocale();
        }

        if ($key === false || $moduleCode === false) {
            return null;
        }

        $default = $this->getParam($params, 'default', '');

        if (null !== $module = ModuleQuery::create()->findOneByCode($moduleCode)) {
            return ModuleConfigQuery::create()
                ->getConfigValue(
                    $module->getId(),
                    $key,
                    $default,
                    $locale
                );
        } else {
            Tlog::getInstance()->addWarning(
                sprintf(
                    "Module code '%s' not found in module-config Smarty function",
                    $moduleCode
                )
            );

            $value = $default;
        }

        return $value;
    }

    /**
     * Provides access to sales statistics
     *
     * @param  array $params
     * @param  \Smarty $smarty
     * @return string the value of the requested attribute
     */

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
            $startDate->modify('first day of January last year');
            $startDate->setTime(0, 0, 0);
        } else {
            try {
                $startDate = new \DateTime($params['startDate']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(
                    sprintf("invalid startDate attribute '%s' in stats access function", $params['startDate'])
                );
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
            $endDate->modify('last day of December last year');
            $endDate->setTime(0, 0, 0);
        } else {
            try {
                $endDate = new \DateTime($params['endDate']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(
                    sprintf("invalid endDate attribute '%s' in stats access function", $params['endDate'])
                );
            }
        }

        switch ($params['key']) {
            case 'sales':
                return OrderQuery::getSaleStats($startDate, $endDate, $includeShipping);
                break;
            case 'orders':
                return OrderQuery::getOrderStats($startDate, $endDate, array(1,2,3,4));
                break;
        }

        throw new \InvalidArgumentException(
            sprintf("invalid key attribute '%s' in stats access function", $params['key'])
        );
    }

    /**
     * Retrieve meta data associated to an element
     *
     * params should contain at least key an id attributes. Thus it will return
     * an array of associated data.
     *
     * If meta argument is specified then it will return an unique value.
     *
     * @param array $params
     * @param \Smarty $smarty
     *
     * @throws \InvalidArgumentException
     *
     * @return string|array|null
     */
    public function metaAccess($params, $smarty)
    {
        $meta = $this->getParam($params, 'meta', null);
        $key = $this->getParam($params, 'key', null);
        $id = $this->getParam($params, 'id', null);

        $cacheKey = sprintf('meta_%s_%s_%s', $meta, $key, $id);

        $out = null;

        if (array_key_exists($cacheKey, self::$dataAccessCache)) {
            return self::$dataAccessCache[$cacheKey];
        }

        if ($key !== null && $id !== null) {
            if ($meta === null) {
                $out = MetaDataQuery::getAllVal($key, (int) $id);
            } else {
                $out = MetaDataQuery::getVal($meta, $key, (int) $id);
            }
        } else {
            throw new \InvalidArgumentException("key and id arguments are required in meta access function");
        }

        self::$dataAccessCache[$cacheKey] = $out;

        if (!empty($params['out'])) {
            $smarty->assign($params['out'], $out);

            return $out !== null ? true : false;
        } else {
            if (is_array($out)) {
                throw new \InvalidArgumentException('The argument "out" is required if the meta value is an array');
            }

            return $out;
        }
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
    protected function dataAccessWithI18n(
        $objectLabel,
        $params,
        ModelCriteria $search,
        $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
        $foreignTable = null,
        $foreignKey = 'ID'
    ) {
        if (array_key_exists('data_' . $objectLabel, self::$dataAccessCache)) {
            $data = self::$dataAccessCache['data_' . $objectLabel];
        } else {
            $lang = $this->getNormalizedParam($params, array('lang'));
            if ($lang === null) {
                $lang = $this->getSession()->getLang()->getId();
            }

            ModelCriteriaTools::getI18n(
                false,
                $lang,
                $search,
                $this->getSession()->getLang()->getLocale(),
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

        if (!empty($attribute)) {
            if (null != $data) {
                $keyAttribute = strtoupper($attribute);
                if (array_key_exists($keyAttribute, $noGetterData)) {
                    return $noGetterData[$keyAttribute];
                }

                $getter = sprintf("get%s", $this->underscoreToCamelcase($attribute));
                if (method_exists($data, $getter)) {
                    $return = $data->$getter();

                    if ($return instanceof \DateTime) {
                        if (array_key_exists("format", $params)) {
                            $format = $params["format"];
                        } else {
                            $format = DateTimeFormat::getInstance($this->getRequest())->getFormat(
                                array_key_exists("output", $params) ? $params["output"] : null
                            );
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
     * Provides access to the uploaded store-related images (such as logo or favicon)
     *
     * @param array $params
     * @param string $content
     * @param \Smarty_Internal_Template $template
     * @param boolean $repeat
     * @return string|null
     */
    public function storeMediaDataAccess($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        $type = $this->getParam($params, 'type', null);
        $allowedTypes = ['favicon', 'logo', 'banner'];


        if ($type !== null && in_array($type, $allowedTypes)) {
            switch ($type) {
                case 'favicon':
                    $configKey = 'favicon_file';
                    $defaultImageName = 'favicon.png';
                    break;
                case 'logo':
                    $configKey = 'logo_file';
                    $defaultImageName = 'logo.png';
                    break;
                case 'banner':
                    $configKey = 'banner_file';
                    $defaultImageName = 'banner.jpg';
                    break;
            }

            $uploadDir = ConfigQuery::read('images_library_path');

            if ($uploadDir === null) {
                $uploadDir = THELIA_LOCAL_DIR . 'media' . DS . 'images';
            } else {
                $uploadDir = THELIA_ROOT . $uploadDir;
            }

            $uploadDir .= DS . 'store';


            $imageFileName = ConfigQuery::read($configKey);

            $skipImageTransform = false;

            // If we couldn't find the image path in the config table or if it doesn't exist, we take the default image provided.
            if ($imageFileName == null) {
                $imageSourcePath = $uploadDir . DS . $defaultImageName;
            } else {
                $imageSourcePath = $uploadDir . DS . $imageFileName;

                if (!file_exists($imageSourcePath)) {
                    Tlog::getInstance()->error(sprintf('Source image file %s does not exists.', $imageSourcePath));
                    $imageSourcePath = $uploadDir . DS . $defaultImageName;
                }

                if ($type == 'favicon') {
                    $extension = pathinfo($imageSourcePath, PATHINFO_EXTENSION);
                    if ($extension == 'ico') {
                        $mime_type = 'image/x-icon';

                        // If the media is a .ico favicon file, we skip the image transformations,
                        //    as transformations on .ico file are not supported by Thelia.
                        $skipImageTransform = true;
                    } else {
                        $mime_type = 'image/png';
                    }

                    $template->assign('MEDIA_MIME_TYPE', $mime_type);
                }
            }

            $event = new ImageEvent();
            $event->setSourceFilepath($imageSourcePath)
                ->setCacheSubdirectory('store');


            if (!$skipImageTransform) {
                switch ($this->getParam($params, 'resize_mode', null)) {
                    case 'crop':
                        $resize_mode = \Thelia\Action\Image::EXACT_RATIO_WITH_CROP;
                        break;

                    case 'borders':
                        $resize_mode = \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS;
                        break;

                    case 'none':
                    default:
                        $resize_mode = \Thelia\Action\Image::KEEP_IMAGE_RATIO;
                }

                // Prepare transformations
                $width = $this->getParam($params, 'width', null);
                $height = $this->getParam($params, 'height', null);
                $rotation = $this->getParam($params, 'rotation', null);

                if (!is_null($width)) {
                    $event->setWidth($width);
                }
                if (!is_null($height)) {
                    $event->setHeight($height);
                }
                $event->setResizeMode($resize_mode);
                if (!is_null($rotation)) {
                    $event->setRotation($rotation);
                }
            }

            $this->dispatcher->dispatch(TheliaEvents::IMAGE_PROCESS, $event);

            $template->assign('MEDIA_URL', $event->getFileUrl());
        }

        if (isset($content)) {
            return $content;
        }

        return null;
    }




    /**
     * @inheritdoc
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
            new SmartyPluginDescriptor('function', 'meta', $this, 'metaAccess'),
            new SmartyPluginDescriptor('function', 'module_config', $this, 'moduleConfigDataAccess'),
            new SmartyPluginDescriptor('function', 'coupon', $this, 'couponDataAccess'),

            new SmartyPluginDescriptor('block', 'local_media', $this, 'storeMediaDataAccess'),
        );
    }

    /**
     * @return Request
     */
    protected function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * @return Session
     */
    protected function getSession()
    {
        return $this->getRequest()->getSession();
    }
}
