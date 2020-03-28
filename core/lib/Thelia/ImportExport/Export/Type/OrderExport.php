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

use PDO;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderStatusI18nTableMap;
use Thelia\Model\Map\OrderTableMap;

/**
 * Class OrderExport
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class OrderExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'order';
    const USE_RANGE_DATE = true;

    protected $orderAndAliases = [
        OrderTableMap::COL_REF => 'ref',
        OrderTableMap::COL_CREATED_AT => 'date',
        CustomerTableMap::COL_REF => 'customer_ref',
        'order_coupon_code' => 'coupons',
        'order_total_price' => 'total_excluding_taxes',
        'order_total_tax' => 'total_taxes',
        'order_total_taxed_price' => 'total_taxed_price',
        'order_discount' => 'discount',
        'order_postage' => 'shipping_cost',
        'order_total_with_taxes_shipping_discount' => 'total_including_taxes',
        'delivery_module.title' => 'delivery_module',
        OrderTableMap::COL_DELIVERY_REF => 'delivery_ref',
        'payment_module.title' => 'payment_module',
        OrderTableMap::COL_INVOICE_REF => 'invoice_ref',
        OrderStatusI18nTableMap::COL_TITLE => 'status',
        'delivery_address_customer_title.long' => 'delivery_title',
        'delivery_address.company' => 'delivery_company',
        'delivery_address.firstname' => 'delivery_first_name',
        'delivery_address.lastname' => 'delivery_last_name',
        'delivery_address.address1' => 'delivery_address_1',
        'delivery_address.address2' => 'delivery_address_2',
        'delivery_address.address3' => 'delivery_address_3',
        'delivery_address.zipcode' => 'delivery_zip_code',
        'delivery_address.city' => 'delivery_city',
        'delivery_country_i18n.title' => 'invoice_country',
        'delivery_address.phone' => 'delivery_phone',
        'invoice_address_customer_title.long' => 'invoice_title',
        'invoice_address.company' => 'invoice_company',
        'invoice_address.firstname' => 'invoice_first_name',
        'invoice_address.lastname' => 'invoice_last_name',
        'invoice_address.address1' => 'invoice_address_1',
        'invoice_address.address2' => 'invoice_address_2',
        'invoice_address.address3' => 'invoice_address_3',
        'invoice_address.zipcode' => 'invoice_zip_code',
        'invoice_address.city' => 'invoice_city',
        'invoice_country_i18n.title' => 'invoice_country',
        'invoice_address.phone' => 'invoice_phone',
        CurrencyTableMap::COL_CODE => 'currency',
        OrderTableMap::COL_POSTAGE_TAX => 'shipping_tax',
        OrderTableMap::COL_POSTAGE_TAX_RULE_TITLE => 'shipping_tax_rule_title',
        OrderProductTaxTableMap::COL_TITLE => 'tax',
    ];

    protected function getData()
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();

        $query = '
            SELECT
                *,
                (order_total_taxed_price - order_total_price) as order_total_tax,
                (order_total_taxed_price + order_postage - order_discount) as order_total_with_taxes_shipping_discount
            FROM (
                SELECT 
                    `order`.ref as "order.ref", 
                    `order`.created_at as "order.created_at",
                    customer.ref as "customer.ref",
                    ROUND(`order`.discount, 2) as order_discount,
                    order_coupon.code as order_coupon_code,
                    ROUND(`order`.postage, 2) as order_postage,
                    `order`.postage_tax as "order.postage_tax",
                    ROUND(`order`.postage_tax_rule_title,2) as "order.postage_tax_rule_title",
                    SUM(ROUND(order_product.quantity * IF(order_product.was_in_promo = 1, order_product.promo_price, order_product.price), 2) ) as order_total_price, 
                    SUM(
                        ROUND(
                            order_product.quantity * (
                                IF(order_product.was_in_promo = 1, order_product.promo_price, order_product.price)
                                +
                                (
                                    SELECT 
                                        COALESCE(SUM(IF(order_product.was_in_promo, order_product_tax.promo_amount, order_product_tax.amount)), 0)
                                    FROM    
                                        order_product_tax
                                    WHERE 
                                        order_product_tax.order_product_id = order_product.id
                                )
                            ), 2
                        )
                    ) as order_total_taxed_price,
                    delivery_module.title as "delivery_module.title",
                    `order`.delivery_ref as "order.delivery_ref",
                    payment_module.title as "payment_module.title",
                    `order`.invoice_ref as "order.invoice_ref",
                    order_status_i18n.title as "order_status_i18n.title",
                    delivery_address_customer_title.long as "delivery_address_customer_title.long",
                    delivery_address.company as "delivery_address.company",
                    delivery_address.firstname as "delivery_address.firstname",
                    delivery_address.lastname as "delivery_address.lastname",
                    delivery_address.address1 as "delivery_address.address1",
                    delivery_address.address2 as "delivery_address.address2",
                    delivery_address.address3 as "delivery_address.address3",
                    delivery_address.zipcode as "delivery_address.zipcode",
                    delivery_address.city as "delivery_address.city",
                    delivery_country_i18n.title as "delivery_country_i18n.title",
                    delivery_address.phone as "delivery_address.phone",
                    invoice_address_customer_title.long as "invoice_address_customer_title.long",
                    invoice_address.company as "invoice_address.company",
                    invoice_address.firstname as "invoice_address.firstname",
                    invoice_address.lastname as "invoice_address.lastname",
                    invoice_address.address1 as "invoice_address.address1",
                    invoice_address.address2 as "invoice_address.address2",
                    invoice_address.address3 as "invoice_address.address3",
                    invoice_address.zipcode as "invoice_address.zipcode",
                    invoice_address.city as "invoice_address.city",
                    invoice_country_i18n.title as "invoice_country_i18n.title",
                    invoice_address.phone as "invoice_address.phone",
                    currency.code as "currency.code",
                    order_product_tax.title as "order_product_tax.title"
                FROM `order`
                LEFT JOIN customer ON customer.id = `order`.customer_id
                LEFT JOIN order_product ON order_product.order_id = `order`.id
                LEFT JOIN order_product_tax ON order_product_tax.order_product_id = order_product.id
                LEFT JOIN order_coupon ON order_coupon.order_id = `order`.id
                LEFT JOIN `module_i18n` as delivery_module ON delivery_module.id = `order`.delivery_module_id AND delivery_module.locale = :locale
                LEFT JOIN `module_i18n` as payment_module ON payment_module.id = `order`.payment_module_id AND payment_module.locale = :locale
                LEFT JOIN order_status_i18n ON order_status_i18n.id = `order`.status_id AND order_status_i18n.locale = :locale
                LEFT JOIN order_address as delivery_address ON delivery_address.id = `order`.delivery_order_address_id
                LEFT JOIN order_address as invoice_address ON invoice_address.id = `order`.invoice_order_address_id
                LEFT JOIN customer_title_i18n as delivery_address_customer_title ON delivery_address_customer_title.id = delivery_address.customer_title_id AND delivery_address_customer_title.locale = :locale
                LEFT JOIN customer_title_i18n as invoice_address_customer_title ON invoice_address_customer_title.id = invoice_address.customer_title_id AND invoice_address_customer_title.locale = :locale
                LEFT JOIN country_i18n as delivery_country_i18n ON delivery_country_i18n.id = delivery_address.country_id AND delivery_country_i18n.locale = :locale
                LEFT JOIN country_i18n as invoice_country_i18n ON invoice_country_i18n.id = invoice_address.country_id AND invoice_country_i18n.locale = :locale
                LEFT JOIN currency ON currency.id = order.currency_id
                WHERE `order`.created_at >= :start AND `order`.created_at <= :end
                GROUP BY `order`.id
                ORDER BY `order`.created_at DESC
            ) as tmp
        ';

        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->bindValue('start', $this->rangeDate['start']->format('Y-m-d'));
        $stmt->bindValue('end', $this->rangeDate['end']->format('Y-m-d'));
        $stmt->execute();

        $filename = THELIA_CACHE_DIR . '/export/' . 'order.json';

        if (file_exists($filename)) {
            unlink($filename);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row) . "\r\n", FILE_APPEND);
        }

        return $filename;
    }
}
