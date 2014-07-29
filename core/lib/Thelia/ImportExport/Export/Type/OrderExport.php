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

namespace Thelia\ImportExport\Export\Type;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\FileFormat\FormatType;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Lang;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderStatusI18nTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;

/**
 * Class OrderExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class OrderExport extends ExportHandler
{
    /**
     * @return string|array
     *
     * Define all the type of formatters that this can handle
     * return a string if it handle a single type ( specific exports ),
     * or an array if multiple.
     *
     * Thelia types are defined in \Thelia\Core\FileFormat\FormatType
     *
     * example:
     * return array(
     *     FormatType::TABLE,
     *     FormatType::UNBOUNDED,
     * );
     */
    public function getHandledTypes()
    {
        return array(
            FormatType::TABLE,
            FormatType::UNBOUNDED,
        );
    }

    /**
     * @param  Lang $lang
     * @return ModelCriteria|array|BaseLoop
     */
    public function buildDataSet(Lang $lang)
    {
        $query = OrderQuery::create()
            ->useOrderAddressRelatedByDeliveryOrderAddressIdQuery()
                ->addAsColumn("delivery_address_TITLE", OrderAddressTableMap::TI)
                ->addAsColumn("delivery_address_COMPANY", OrderAddressTableMap::COMPANY)
                ->addAsColumn("delivery_address_FIRSTNAME", OrderAddressTableMap::FIRSTNAME)
                ->addAsColumn("delivery_address_LASTNAME", OrderAddressTableMap::LASTNAME)
                ->addAsColumn("delivery_address_ADDRESS1", OrderAddressTableMap::ADDRESS1)
                ->addAsColumn("delivery_address_ADDRESS2", OrderAddressTableMap::ADDRESS2)
                ->addAsColumn("delivery_address_ADDRESS3", OrderAddressTableMap::ADDRESS3)
                ->addAsColumn("delivery_address_ZIPCODE", OrderAddressTableMap::ZIPCODE)
                ->addAsColumn("delivery_address_CITY", OrderAddressTableMap::CITY)
                ->addAsColumn("delivery_address_country_TITLE", OrderAddressTableMap::TITLE)
                ->addAsColumn("delivery_address_PHONE", OrderAddressTableMap::PHONE)
            ->endUse()
            ->useOrderStatusQuery()
                ->useOrderStatusI18nQuery()
                    ->addAsColumn("order_status_TITLE", OrderStatusI18nTableMap::TITLE)
                ->endUse()
            ->endUse()
            ->select([
                OrderTableMap::REF,
                "customer_REF",
                "product_TITLE",
                "product_PRICE",
                "tax_TITLE",
                // PRODUCT_TTC_PRICE
                "product_QUANTITY",
                // ORDER_TOTAL_TTC
                OrderTableMap::DISCOUNT,
                "coupon_COUPONS",
                // TOTAL_WITH_DISCOUNT
                OrderTableMap::POSTAGE,
                // total ttc +postage
                "payment_module_TITLE",
                OrderTableMap::INVOICE_REF,
                "delivery_module_TITLE",
                "delivery_address_TITLE",
                "delivery_address_COMPANY",
                "delivery_address_FIRSTNAME",
                "delivery_address_LASTNAME",
                "delivery_address_ADDRESS1",
                "delivery_address_ADDRESS2",
                "delivery_address_ADDRESS3",
                "delivery_address_ZIPCODE",
                "delivery_address_CITY",
                "delivery_address_country_TITLE",
                "delivery_address_PHONE",
                "invoice_address_TITLE",
                "invoice_address_COMPANY",
                "invoice_address_FIRSTNAME",
                "invoice_address_LASTNAME",
                "invoice_address_ADDRESS1",
                "invoice_address_ADDRESS2",
                "invoice_address_ADDRESS3",
                "invoice_address_ZIPCODE",
                "invoice_address_CITY",
                "invoice_address_country_TITLE",
                "invoice_address_PHONE",
                "order_status_TITLE"
            ])
        ;
    }

} 