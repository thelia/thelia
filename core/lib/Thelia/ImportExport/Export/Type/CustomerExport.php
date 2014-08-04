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
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Currency;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CountryI18nTableMap;
use Thelia\Model\Map\CountryTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\CustomerTitleI18nTableMap;
use Thelia\Model\Map\NewsletterTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Tools\I18n;

/**
 * Class CustomerExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerExport extends ExportHandler
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
     * @param  Lang                                            $lang
     * @return array|\Propel\Runtime\ActiveQuery\ModelCriteria
     *
     * The tax engine of Thelia is in PHP, so we can't compute orders for each customers
     * directly in SQL, we need two SQL queries, and some computing to get the last order amount and total amount.
     */
    public function buildDataSet(Lang $lang)
    {
        $locale = $lang->getLocale();
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();
        /**
         * This first query get each customer info and addresses.
         */
        $newsletterJoin = new Join(CustomerTableMap::EMAIL, NewsletterTableMap::EMAIL, Criteria::LEFT_JOIN);

        $query = CustomerQuery::create()
            ->useCustomerTitleQuery("customer_title_")
                ->useCustomerTitleI18nQuery("customer_title_i18n_")
                    ->addAsColumn("title_TITLE", "customer_title_i18n_.SHORT")
                ->endUse()
            ->endUse()
            ->useAddressQuery()
                ->useCountryQuery()
                    ->useCountryI18nQuery()
                        ->addAsColumn("address_COUNTRY", CountryI18nTableMap::TITLE)
                    ->endUse()
                ->endUse()
                ->useCustomerTitleQuery("address_title")
                    ->useCustomerTitleI18nQuery("address_title_i18n")
                        ->addAsColumn("address_TITLE", "address_title_i18n.SHORT")
                    ->endUse()
                ->endUse()
                ->addAsColumn("address_LABEL", AddressTableMap::LABEL)
                ->addAsColumn("address_FIRST_NAME", AddressTableMap::FIRSTNAME)
                ->addAsColumn("address_LAST_NAME", AddressTableMap::LASTNAME)
                ->addAsColumn("address_COMPANY", AddressTableMap::COMPANY)
                ->addAsColumn("address_ADDRESS1", AddressTableMap::ADDRESS1)
                ->addAsColumn("address_ADDRESS2", AddressTableMap::ADDRESS2)
                ->addAsColumn("address_ADDRESS3", AddressTableMap::ADDRESS3)
                ->addAsColumn("address_ZIPCODE", AddressTableMap::ZIPCODE)
                ->addAsColumn("address_CITY", AddressTableMap::CITY)
                ->addAsColumn("address_PHONE", AddressTableMap::PHONE)
                ->addAsColumn("address_CELLPHONE", AddressTableMap::CELLPHONE)
                ->addAsColumn("address_IS_DEFAULT", AddressTableMap::IS_DEFAULT)
            ->endUse()
            ->addJoinObject($newsletterJoin)
            ->addAsColumn("newsletter_IS_REGISTRED", "IF(NOT ISNULL(".NewsletterTableMap::EMAIL."),1,0)")
            ->select([
                CustomerTableMap::ID,
                CustomerTableMap::REF,
                CustomerTableMap::LASTNAME,
                CustomerTableMap::FIRSTNAME,
                CustomerTableMap::EMAIL,
                CustomerTableMap::DISCOUNT,
                CustomerTableMap::CREATED_AT,
                "title_TITLE",
                "address_TITLE",
                "address_LABEL",
                "address_COMPANY",
                "address_FIRST_NAME",
                "address_LAST_NAME",
                "address_ADDRESS1",
                "address_ADDRESS2",
                "address_ADDRESS3",
                "address_ZIPCODE",
                "address_CITY",
                "address_COUNTRY",
                "address_PHONE",
                "address_CELLPHONE",
                "address_IS_DEFAULT",
                "newsletter_IS_REGISTRED",
            ])
            ->orderById()
        ;

        I18n::addI18nCondition(
            $query,
            CountryI18nTableMap::TABLE_NAME,
            CountryTableMap::ID,
            CountryI18nTableMap::ID,
            CountryI18nTableMap::LOCALE,
            $locale
        );

        I18n::addI18nCondition(
            $query,
            CustomerTitleI18nTableMap::TABLE_NAME,
            "`customer_title_`.ID",
            "`customer_title_i18n_`.ID",
            "`customer_title_i18n_`.LOCALE",
            $locale
        );

        I18n::addI18nCondition(
            $query,
            CustomerTitleI18nTableMap::TABLE_NAME,
            "`address_title`.ID",
            "`address_title_i18n`.ID",
            "`address_title_i18n`.LOCALE",
            $locale
        );

        /** @var CustomerQuery $query */
        $results = $query
            ->find()
            ->toArray()
        ;

        /**
         * Then get the orders
         */
        $orders = OrderQuery::create()
            ->useCustomerQuery()
                ->orderById()
            ->endUse()
            ->find()
        ;

        /**
         * And add them info the array
         */
        $orders->rewind();

        $arrayLength = count($results);

        $previousCustomerId = null;

        for ($i = 0; $i < $arrayLength; ++$i) {
            $currentCustomer = &$results[$i];

            $currentCustomerId = $currentCustomer[CustomerTableMap::ID];
            unset ($currentCustomer[CustomerTableMap::ID]);

            if ($currentCustomerId === $previousCustomerId) {
                $currentCustomer["title_TITLE"] = "";
                $currentCustomer[CustomerTableMap::LASTNAME] = "";
                $currentCustomer[CustomerTableMap::FIRSTNAME] = "";
                $currentCustomer[CustomerTableMap::EMAIL] = "";
                $currentCustomer["address_COMPANY"] = "";
                $currentCustomer["newsletter_IS_REGISTRED"] = "";
                $currentCustomer[CustomerTableMap::CREATED_AT] = "";
                $currentCustomer[CustomerTableMap::DISCOUNT] = "";

                $currentCustomer += [
                    "order_TOTAL" => "",
                    "last_order_AMOUNT" => "",
                    "last_order_DATE" => "",
                ];
            } else {

                /**
                 * Reformat created_at date
                 */
                $date = $currentCustomer[CustomerTableMap::CREATED_AT];
                $dateTime = new \DateTime($date);
                $currentCustomer[CustomerTableMap::CREATED_AT] = $dateTime->format($lang->getDatetimeFormat());

                /**
                 * Then compute everything about the orders
                 */
                $total = 0;
                $lastOrderAmount = 0;
                $lastOrderDate = null;
                $lastOrder = null;
                $lastOrderCurrencyCode = null;
                $lastOrderId = 0;

                $defaultCurrency = Currency::getDefaultCurrency();
                $defaultCurrencyCode = $defaultCurrency
                    ->getCode()
                ;

                if (empty($defaultCurrencyCode)) {
                    $defaultCurrencyCode = $defaultCurrency
                        ->getCode()
                    ;
                }

                $formattedDate = null;

                /** @var \Thelia\Model\Order $currentOrder */
                while (false !== $currentOrder = $orders->current()) {
                    if ($currentCustomerId != $currentOrder->getCustomerId()) {
                        break;
                    }

                    $amount = $currentOrder->getTotalAmount($tax);
                    if (0 < $rate = $currentOrder->getCurrencyRate()) {
                        $amount = round($amount / $rate, 2);
                    }

                    $total += $amount;

                    /** @var \DateTime $date */
                    $date = $currentOrder->getCreatedAt();

                    if (null === $lastOrderDate || ($date >= $lastOrderDate && $lastOrderId < $currentOrder->getId())) {
                        $lastOrder = $currentOrder;
                        $lastOrderDate = $date;
                        $lastOrderId = $currentOrder->getId();
                    }

                    $orders->next();
                }

                if ($lastOrderDate !== null) {
                    $formattedDate = $lastOrderDate->format($lang->getDatetimeFormat());

                    $orderCurrency = $lastOrder->getCurrency();
                    $lastOrderCurrencyCode = $orderCurrency
                        ->getCode()
                    ;

                    if (empty($lastOrderCurrencyCode)) {
                        $lastOrderCurrencyCode = $orderCurrency
                            ->getCode()
                        ;
                    }

                    $lastOrderAmount = $lastOrder->getTotalAmount($tax_);
                }

                $currentCustomer += [
                    "order_TOTAL" => $total . " " . $defaultCurrencyCode,
                    "last_order_AMOUNT" => $lastOrderAmount === 0 ? "" : $lastOrderAmount . " " . $lastOrderCurrencyCode,
                    "last_order_DATE" => $formattedDate,
                ];
            }

            $previousCustomerId = $currentCustomerId;
        }

        return $results;
    }

    protected function getAliases()
    {
        return [
            CustomerTableMap::REF => "ref",
            CustomerTableMap::LASTNAME => "last_name",
            CustomerTableMap::FIRSTNAME => "first_name",
            CustomerTableMap::EMAIL => "email",
            CustomerTableMap::DISCOUNT => "discount",
            CustomerTableMap::CREATED_AT => "sign_up_date",
            "title_TITLE" => "title",
            "address_TITLE" => "address_title",
            "address_LABEL" => "label",
            "address_IS_DEFAULT" => "is_default_address",
            "address_COMPANY" => "company",
            "address_ADDRESS1" => "address1",
            "address_ADDRESS2" => "address2",
            "address_ADDRESS3" => "address3",
            "address_ZIPCODE" => "zipcode",
            "address_CITY" => "city",
            "address_COUNTRY" => "country",
            "address_PHONE" => "phone",
            "address_CELLPHONE" => "cellphone",
            "address_FIRST_NAME" => "address_first_name",
            "address_LAST_NAME" => "address_last_name",
            "newsletter_IS_REGISTRED" => "is_registered_to_newsletter",
            "order_TOTAL" => "total_orders",
            "last_order_AMOUNT" => "last_order_amount",
            "last_order_DATE" => "last_order_date",
        ];
    }

    public function getOrder()
    {
        return [
            "ref",
            "title",
            "last_name",
            "first_name",
            "email",
            "discount",
            "is_registered_to_newsletter",
            "sign_up_date",
            "total_orders",
            "last_order_amount",
            "last_order_date",
            "label",
            "address_title",
            "address_first_name",
            "address_last_name",
            "company",
            "address1",
            "address2",
            "address3",
            "zipcode",
            "city",
            "country",
            "phone",
            "cellphone",
            "is_default_address",
        ];
    }
}
