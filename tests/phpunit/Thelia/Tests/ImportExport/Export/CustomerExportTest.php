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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\Type\CustomerExport;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryI18nQuery;
use Thelia\Model\Currency;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitleI18nQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Lang;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\OrderQuery;

/**
 * Class CustomerExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerExportTest extends \PHPUnit_Framework_TestCase
{
    public function testQuery()
    {
        new Translator(new Container());

        $handler = new CustomerExport(new Container());

        $lang = Lang::getDefaultLanguage();
        $data = $handler->buildData($lang);

        $keys = ["ref","title","last_name","first_name","email","label",
            "discount","is_registered_to_newsletter","sign_up_date",
            "total_orders","last_order_amount","last_order_date",
            "address_first_name","address_last_name","company","address1",
            "address2","address3","zipcode","city","country","phone",
            "cellphone","is_default_address","address_title"];

        sort($keys);

        $rawData = $data->getData();

        $max = CustomerQuery::create()->count();
        /**
         * 30 customers that has more than 1 addresses or enough
         */
        if (30  <  $max) {
            $max = 30;
        }

        for ($i = 0; $i < $max;) {
            $row = $rawData[$i];

            $rowKeys = array_keys($row);
            sort($rowKeys);

            $this->assertEquals($rowKeys, $keys);

            $customer = CustomerQuery::create()
                ->findOneByRef($row["ref"])
            ;

            $this->assertNotNull($customer);

            $this->assertEquals($customer->getFirstname(), $row["first_name"]);
            $this->assertEquals($customer->getLastname(), $row["last_name"]);
            $this->assertEquals($customer->getEmail(), $row["email"]);
            $this->assertEquals($customer->getCreatedAt()->format($lang->getDatetimeFormat()), $row["sign_up_date"]);
            $this->assertEquals($customer->getDiscount(), $row["discount"]);

            $title = CustomerTitleQuery::create()->findPk($customer->getTitleId());
            $this->assertEquals($title->getShort(), $row["title"]);

            $total = 0;
            foreach ($customer->getOrders() as $order) {
                $amount = $order->getTotalAmount($tax);

                if (0 < $rate = $order->getCurrencyRate()) {
                    $amount = round($amount / $rate, 2);
                }

                $total += $amount;
            }

            $defaultCurrencyCode = Currency::getDefaultCurrency()->getCode();
            $this->assertEquals($total . " " . $defaultCurrencyCode, $row["total_orders"]);

            $lastOrder = OrderQuery::create()
                ->filterByCustomer($customer)
                ->orderByCreatedAt(Criteria::DESC)
                ->orderById(Criteria::DESC)
                ->findOne()
            ;

            if (null !== $lastOrder) {
                $expectedPrice = $lastOrder->getTotalAmount($tax_) . " " . $lastOrder->getCurrency()->getCode();
                $expectedDate = $lastOrder->getCreatedAt()->format($lang->getDatetimeFormat());
            } else {
                $expectedPrice = "";
                $expectedDate = "";
            }

            $this->assertEquals(
                $expectedPrice,
                $row["last_order_amount"]
            );

            $this->assertEquals(
                $expectedDate,
                $row["last_order_date"]
            );

            $newsletter = NewsletterQuery::create()
                ->findOneByEmail($customer->getEmail())
            ;

            $this->assertEquals(
                $newsletter === null ? 0 : 1,
                $row["is_registered_to_newsletter"]
            );

            do {
                $address = AddressQuery::create()
                    ->filterByCustomer($customer)
                    ->filterByAddress1($rawData[$i]["address1"])
                    ->filterByAddress2($rawData[$i]["address2"])
                    ->filterByAddress3($rawData[$i]["address3"])
                    ->filterByFirstname($rawData[$i]["address_first_name"])
                    ->filterByLastname($rawData[$i]["address_last_name"])
                    ->filterByCountryId(
                        CountryI18nQuery::create()
                            ->filterByLocale($lang->getLocale())
                            ->findOneByTitle($rawData[$i]["country"])
                            ->getId()
                    )
                    ->filterByCompany($rawData[$i]["company"])
                        ->_if(empty($rawData[$i]["company"]))
                            ->_or()
                            ->filterByCompany(null, Criteria::ISNULL)
                        ->_endif()
                    ->filterByZipcode($rawData[$i]["zipcode"])
                    ->filterByCity($rawData[$i]["city"])
                    ->filterByIsDefault($rawData[$i]["is_default_address"])
                    ->filterByCellphone($rawData[$i]["cellphone"])
                        ->_if(empty($rawData[$i]["cellphone"]))
                            ->_or()
                            ->filterByCellphone(null, Criteria::ISNULL)
                        ->_endif()
                    ->filterByPhone($rawData[$i]["phone"])
                        ->_if(empty($rawData[$i]["phone"]))
                            ->_or()
                            ->filterByPhone(null, Criteria::ISNULL)
                        ->_endif()
                    ->filterByLabel($rawData[$i]["label"])
                        ->_if(empty($rawData[$i]["label"]))
                            ->_or()
                            ->filterByLabel(null, Criteria::ISNULL)
                        ->_endif()
                    ->filterByTitleId(
                        CustomerTitleI18nQuery::create()
                            ->filterByLocale($lang->getLocale())
                            ->findOneByShort($rawData[$i]["address_title"])
                            ->getId()
                    )
                    ->findOne()
                ;

                $this->assertNotNull($address);

                $rowKeys = array_keys($rawData[$i]);
                sort($rowKeys);

                $this->assertEquals($rowKeys, $keys);
            } while (
                isset($rawData[++$i]["ref"]) &&
                $rawData[$i-1]["ref"] === $rawData[$i]["ref"] &&
                ++$max
            );
        }
    }
}
