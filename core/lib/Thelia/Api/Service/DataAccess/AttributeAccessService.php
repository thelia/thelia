<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Service\DataAccess;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Base\BrandQuery;
use Thelia\Model\Cart;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\State;
use Thelia\Model\Tools\ModelCriteriaTools;
use Thelia\Tools\DateTimeFormat;

class AttributeAccessService
{
    private static array $dataAccessCache = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SecurityContext $securityContext,
        private readonly TaxEngine $taxEngine,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CouponManager $couponManager, private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function attributeAdmin(string $attributeName): mixed
    {
        return $this->dataAccess('Admin User', $attributeName, $this->securityContext->getAdminUser());
    }

    public function attributeCustomer(string $attributeName): mixed
    {
        return $this->dataAccess('Customer User', $attributeName, $this->securityContext->getCustomerUser());
    }

    public function attributeProduct(string $attributeName): mixed
    {
        if (null === $productId = $this->getRequestParam('product_id')) {
            return '';
        }
        $search = ProductQuery::create();

        return $this->dataAccessWithI18n(
            objectLabel: 'Product',
            attributeName: $attributeName,
            search: $search->filterByPrimaryKey($productId),
        );
    }

    public function attributeCategory(string $attributeName): mixed
    {
        $categoryId = $this->getRequestParam('category_id');

        if ($categoryId === null) {
            $productId = $this->getRequestParam('product_id');
            if ($productId !== null) {
                $product = ProductQuery::create()->findPk($productId);
                if ($product !== null) {
                    $categoryId = $product->getDefaultCategoryId();
                }
            }
        }

        if ($categoryId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Category',
            $attributeName,
            CategoryQuery::create()->filterByPrimaryKey($categoryId)
        );
    }

    public function attributeContent(string $attributeName): mixed
    {
        $contentId = $this->getRequestParam('content_id');

        if ($contentId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Content',
            $attributeName,
            ContentQuery::create()->filterByPrimaryKey($contentId)
        );
    }

    public function attributeFolder(string $attributeName): mixed
    {
        $folderId = $this->getRequestParam('folder_id');

        if ($folderId === null) {
            $contentId = $this->getRequestParam('content_id');

            if ($contentId !== null) {
                $content = ContentQuery::create()->findPk($contentId);
                if ($content !== null) {
                    $folderId = $content->getDefaultFolderId();
                }
            }
        }

        if ($folderId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Folder',
            $attributeName,
            FolderQuery::create()->filterByPrimaryKey($folderId)
        );
    }

    public function attributeBrand(string $attributeName): mixed
    {
        $brandId = $this->getRequestParam('brand_id');

        if ($brandId === null) {
            $productId = $this->getRequestParam('product_id');

            if ($productId !== null) {
                $product = ProductQuery::create()->findPk($productId);
                if ($product !== null) {
                    $brandId = $product->getBrandId();
                }
            }
        }

        if ($brandId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Brand',
            $attributeName,
            BrandQuery::create()->filterByPrimaryKey($brandId)
        );
    }

    public function attributeCurrency(string $attributeName): mixed
    {
        $currency = $this->getSession()->getCurrency();

        if ($currency) {
            return $this->dataAccessWithI18n(
                'Currency',
                $attributeName,
                CurrencyQuery::create()->filterByPrimaryKey($currency->getId()),
                ['NAME']
            );
        }

        return '';
    }

    public function attributeCountry(string $attributeName): mixed
    {
        if ($attributeName !== 'default') {
            return '';
        }

        return $this->dataAccessWithI18n(
            'defaultCountry',
            $attributeName,
            CountryQuery::create()->filterByByDefault(1)->limit(1)
        );
    }

    /**
     * @throws PropelException
     */
    public function attributeCart(string $attributeName): mixed
    {
        if (!\array_key_exists('currentCountry', self::$dataAccessCache)) {
            self::$dataAccessCache['currentCountry'] = $this->taxEngine->getDeliveryCountry();
        }
        /* @var Country $taxCountry */
        $taxCountry = self::$dataAccessCache['currentCountry'];

        if (!\array_key_exists('currentState', self::$dataAccessCache)) {
            self::$dataAccessCache['currentState'] = $this->taxEngine->getDeliveryState();
        }
        /* @var State $taxState */
        $taxState = self::$dataAccessCache['currentState'];

        /** @var Cart $cart */
        $cart = $this->getSession()->getSessionCart($this->dispatcher);

        $result = '';
        switch ($attributeName) {
            case 'count_product':
            case 'product_count':
                $result = $cart->getCartItems()->count();
                break;
            case 'count_item':
            case 'item_count':
                $countAllItem = 0;
                foreach ($cart->getCartItems() as $cartItem) {
                    $countAllItem += $cartItem->getQuantity();
                }
                $result = $countAllItem;
                break;
            case 'taxed_postage':
                $result = $cart->getTaxedPostage();
                break;
            case 'postage':
                $result = $cart->getPostage();
                break;
            case 'total_price':
            case 'total_price_with_discount':
                $result = $cart->getTotalAmount(true, $taxCountry, $taxState, true);
                break;
            case 'total_price_without_discount':
                $result = $cart->getTotalAmount(false, $taxCountry, $taxState, true);
                break;
            case 'total_price_without_postage':
                $result = $cart->getTotalAmount(true, $taxCountry, $taxState);
                break;
            case 'raw_total_price':
                $result = $cart->getTotalAmount(false, $taxCountry, $taxState);
                break;
            case 'total_taxed_price':
            case 'total_taxed_price_with_discount':
                $result = $cart->getTaxedAmount($taxCountry, true, $taxState, true);
                break;
            case 'total_taxed_price_without_discount':
                $result = $cart->getTaxedAmount($taxCountry, false, $taxState, true);
                break;
            case 'total_taxed_price_without_postage':
                $result = $cart->getTaxedAmount($taxCountry, true, $taxState);
                break;
            case 'raw_taxed_total_price':
                $result = $cart->getTaxedAmount($taxCountry, false, $taxState);
                break;
            case 'is_virtual':
            case 'contains_virtual_product':
                $result = $cart->isVirtual();
                break;
            case 'total_vat':
            case 'total_tax_amount':
                $result = $cart->getTotalVAT($taxCountry, $taxState, true, true);
                break;
            case 'total_tax_amount_without_discount':
                $result = $cart->getTotalVAT($taxCountry, $taxState, false, true);
                break;
            case 'raw_total_tax_amount':
                $result = $cart->getTotalVAT($taxCountry, $taxState, false, false);
                break;
            case 'taxed_discount':
                $result = $cart->getDiscount(true, $taxCountry, $taxState);
                break;
            case 'discount':
                $result = $cart->getDiscount(false, $taxCountry, $taxState);
                break;
            case 'discount_tax_amount':
                $result = $cart->getDiscountVAT($taxCountry, $taxState);
                break;
            case 'weight':
                $result = $cart->getWeight();
                break;
        }

        return $result;
    }

    public function attributeCoupon(string $attributeName): mixed
    {
        /** @var Cart $cart */
        $cart = $this->getSession()->getSessionCart($this->eventDispatcher);

        switch ($attributeName) {
            case 'has_coupons':
                return \count($this->couponManager->getCouponsKept()) > 0;
            case 'coupon_count':
                return \count($this->couponManager->getCouponsKept());
            case 'coupon_list':
                $orderCoupons = [];
                /** @var CouponInterface $coupon */
                foreach ($this->couponManager->getCouponsKept() as $coupon) {
                    $orderCoupons[] = $coupon->getCode();
                }

                return $orderCoupons;
            case 'is_delivery_free':
                return $this->couponManager->isCouponRemovingPostage($cart);
        }
        throw new \InvalidArgumentException(\sprintf("%s has no '%s' attribute", 'Order', $attributeName));
    }

    public function orderDataAccess(string $attributeName): mixed
    {
        $order = $this->getSession()->getOrder();
        switch ($attributeName) {
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

        throw new \InvalidArgumentException(\sprintf("%s has no '%s' attribute", 'Order', $attributeName));
    }

    public function attributeLang(string $attributeName): mixed
    {
        return $this->dataAccess('Lang', $attributeName, $this->getSession()->getLang());
    }

    public function attributeConfig(string $attributeName)
    {
        return ConfigQuery::read($attributeName);
    }

    protected function dataAccessWithI18n(
        string $objectLabel,
        string $attributeName,
        ModelCriteria $search,
        array $columns = ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
        ?string $foreignTable = null,
        string $foreignKey = 'ID',
    ): mixed {
        $cacheKey = 'data_'.$objectLabel;

        $data = self::$dataAccessCache[$cacheKey] ?? null;

        if ($data === null) {
            $lang = $this->getSession()->getLang()?->getId();

            ModelCriteriaTools::getI18n(
                false,
                $lang,
                $search,
                $this->getSession()->getLang()?->getLocale(),
                $columns,
                $foreignTable,
                $foreignKey,
                true
            );

            $data = $search->findOne();
            self::$dataAccessCache[$cacheKey] = $data;
        }

        if ($data === null) {
            throw new NotFoundHttpException();
        }
        $noGetterData = array_map(static fn ($column) => $data->getVirtualColumn('i18n_'.$column), $columns);

        return $this->dataAccess($objectLabel, $attributeName, $data, $noGetterData);
    }

    protected function dataAccess(
        string $objectLabel,
        string $attributeName,
        ?object $data,
        array $noGetterData = [],
    ): string|int|null {
        if (empty($attributeName) || $data === null) {
            return '';
        }
        $keyAttribute = strtoupper($attributeName);

        if (\array_key_exists($keyAttribute, $noGetterData)) {
            return $noGetterData[$keyAttribute];
        }

        $getter = \sprintf('get%s', $this->underscoreToCamelcase($attributeName));

        if (method_exists($data, $getter)) {
            $return = $data->$getter();

            if ($return instanceof \DateTime) {
                $format = DateTimeFormat::getInstance($this->getRequest())->getFormat();

                return $return->format((string) $format);
            }

            return $return;
        }
        throw new \InvalidArgumentException(\sprintf("%s has no '%s' attribute", $objectLabel, $attributeName));
    }

    private function underscoreToCamelcase(string $str): mixed
    {
        $words = explode('_', strtolower($str));

        $return = '';

        foreach ($words as $word) {
            $return .= ucfirst(trim($word));
        }

        return $return;
    }

    public function getRequest(): Request
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            throw new \RuntimeException('No request available');
        }

        return $request;
    }

    public function getSession(): Session
    {
        $request = $this->getRequest();
        /** @var Session $session */
        $session = $request->getSession();

        return $session;
    }

    public function getRequestParam(string $key): mixed
    {
        return $this->getRequest()->get($key);
    }
}
