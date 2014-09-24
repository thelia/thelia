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

namespace Thelia\Tests\ImportExport\Export;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\Type\OrderExport;
use Thelia\Model\Lang;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;

/**
 * Class OrderExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class OrderExportTest extends \PHPUnit_Framework_TestCase
{
    public function testQuery()
    {
        $container = new Container();
        new Translator($container);

        $handler = new OrderExport($container);

        $lang = Lang::getDefaultLanguage();
        $locale = $lang->getLocale();

        $data = $handler->buildData($lang)->getData();

        $ordersProductQuery = OrderProductQuery::create();
        $orders = OrderQuery::create()->find();

        $count = $ordersProductQuery->count();
        $this->assertEquals(count($data), $count);

        /**
         * For the rest of the test, 50 orders are much enough
         */
        if ($count > 50) {
            $count = 50;
        }

        $current = 0;

        for ($i = 0; $i < $count; ++$current) {
            $row = $data[$i];
            /** @var \Thelia\Model\Order $order */
            $order = $orders->get($current);

            $this->assertEquals(
                $ref = $order->getRef(),
                $row["ref"]
            );

            $this->assertEquals(
                $order->getCustomer()->getRef(),
                $row["customer_ref"]
            );

            $coupons = OrderCouponQuery::create()
                ->filterByOrder($order)
                ->select(OrderCouponTableMap::TITLE)
                ->find()
                ->toArray()
            ;
            $coupons = implode(",", $coupons);

            $this->assertTrue(
                empty($coupons) ? empty($row["coupons"]): $coupons === $row["coupons"]
            );

            $this->assertEquals(
                $order->getCreatedAt()->format($lang->getDatetimeFormat()),
                $row["date"]
            );

            $this->assertEquals($order->getCurrency()->getCode(), $row["currency"]);
            $this->assertEquals($order->getCustomer()->getRef(), $row["customer_ref"]);
            $this->assertEquals($order->getOrderStatus()->setLocale($locale)->getTitle(), $row["status"]);

            $this->assertEquals($order->getDeliveryRef(), $row["delivery_ref"]);
            $this->assertEquals($order->getModuleRelatedByDeliveryModuleId()->getCode(), $row["delivery_module"]);
            $this->assertEquals($order->getInvoiceRef(), $row["invoice_ref"]);
            $this->assertEquals($order->getModuleRelatedByPaymentModuleId()->getCode(), $row["payment_module"]);

            $this->assertEquals($order->getTotalAmount($tax, false, false), $row["total_including_taxes"]);
            $this->assertEquals($order->getTotalAmount($tax, false, true), $row["total_with_discount"]);
            $this->assertEquals($order->getTotalAmount($tax, true, true), $row["total_discount_and_postage"]);

            $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
            $deliveryAddress = $order->getOrderAddressRelatedByDeliveryOrderAddressId();

            $addresses = [
                "delivery" => $deliveryAddress,
                "invoice" => $invoiceAddress
            ];

            /** @var \Thelia\Model\OrderAddress $address */
            foreach ($addresses as $prefix => $address) {
                $this->assertEquals($address->getCustomerTitle()->setLocale($locale)->getShort(), $row[$prefix."_title"]);
                $this->assertEquals($address->getAddress1(), $row[$prefix."_address1"]);
                $this->assertEquals($address->getAddress2(), $row[$prefix."_address2"]);
                $this->assertEquals($address->getAddress3(), $row[$prefix."_address3"]);
                $this->assertEquals($address->getCity(), $row[$prefix."_city"]);
                $this->assertEquals($address->getZipcode(), $row[$prefix."_zip_code"]);
                $this->assertEquals($address->getCompany(), $row[$prefix."_company"]);
                $this->assertEquals($address->getFirstname(), $row[$prefix."_first_name"]);
                $this->assertEquals($address->getLastname(), $row[$prefix."_last_name"]);
                $this->assertEquals($address->getCountry()->setLocale($locale)->getTitle(), $row[$prefix."_country"]);
                $this->assertEquals($address->getPhone(), $row[$prefix."_phone"]);
            }

            while ($data[$i]["ref"] === $ref) {
                /** @var \Thelia\Model\OrderProduct $product */
                $product = OrderProductQuery::create()
                    ->filterByTitle($data[$i]["product_title"])
                    ->filterByTaxRuleTitle($data[$i]["tax_title"])
                    ->filterByWasInPromo($data[$i]["was_in_promo"])
                    ->_if((bool) ((int) $data[$i]["was_in_promo"]))
                        ->filterByPromoPrice($data[$i]["price"])
                    ->_else()
                        ->filterByPrice($data[$i]["price"])
                    ->_endif()
                    ->filterByQuantity($data[$i]["quantity"])
                    ->findOne()
                ;
                $this->assertNotNull($product);

                $sum = 0;
                foreach ($product->getOrderProductTaxes() as $tax) {
                    $sum += $product->getWasInPromo() ? $tax->getPromoAmount() : $tax->getAmount();
                }

                $this->assertEquals($sum, $data[$i++]["tax_amount"]);
            }
        }
    }
}
