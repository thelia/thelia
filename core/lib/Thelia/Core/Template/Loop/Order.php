<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Base\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Order extends BaseLoop implements SearchLoopInterface, PropelSearchLoopInterface
{
    protected $countable = true;
    protected $timestampable = true;
    protected $versionable = false;

    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(array('current', '*'))
                ),
                'current'
            ),
            new Argument(
                'status',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(array('*'))
                )
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array(
                        'id', 'id-reverse',
                        'reference', 'reference-reverse',
                        'create-date', 'create-date-reverse',
                        'company', 'company-reverse',
                        'customer-name', 'customer-name-reverse',
                        'status', 'status-reverse'
                    ))
                ),
                'create-date-reverse'
            )
        );
    }

    public function getSearchIn()
    {
        return array(
            "ref",
            "customer_ref",
            "customer_firstname",
            "customer_lastname",
            "customer_email",
        );
    }

    /**
     * @param OrderQuery $search
     * @param $searchTerm
     * @param $searchIn
     * @param $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {

        $search->_and();
        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }
            switch ($searchInElement) {
                case "ref":
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case "customer_ref":
                    $search->filterByCustomer(
                        CustomerQuery::create()->filterByRef($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case "customer_firstname":
                    $search->filterByOrderAddressRelatedByInvoiceOrderAddressId(
                        OrderAddressQuery::create()->filterByFirstname($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case "customer_lastname":
                    $search->filterByOrderAddressRelatedByInvoiceOrderAddressId(
                        OrderAddressQuery::create()->filterByLastname($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case "customer_email":
                    $search->filterByCustomer(
                        CustomerQuery::create()->filterByEmail($searchTerm, $searchCriteria)->find()
                    );
                    break;
            }
        }
    }

    public function buildModelCriteria()
    {
        $search = OrderQuery::create();

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $customer = $this->getCustomer();

        if ($customer === 'current') {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
            } else {
                $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
            }
        } elseif ($customer !== '*') {
            $search->filterByCustomerId($customer, Criteria::EQUAL);
        }

        $status = $this->getStatus();

        if (null !== $status && $status != '*') {
            $search->filterByStatusId($status, Criteria::IN);
        }

        $orderers = $this->getOrder();

        foreach ($orderers as $orderer) {
            switch ($orderer) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;

                case 'reference':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'reference_reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;

                case "create-date":
                    $search->orderByCreatedAt(Criteria::ASC);
                    break;
                case "create-date-reverse":
                    $search->orderByCreatedAt(Criteria::DESC);
                    break;

                case "status":
                    $search->orderByStatusId(Criteria::ASC);
                    break;
                case "status":
                    $search->orderByStatusId(Criteria::DESC);
                    break;

                case 'company' :
                    $search
                        ->joinOrderAddressRelatedByDeliveryOrderAddressId()
                        ->withColumn(OrderAddressTableMap::COMPANY, 'company')
                        ->orderBy('company', Criteria::ASC)
                    ;
                    break;
                case 'companyreverse' :
                    $search
                        ->joinOrderAddressRelatedByDeliveryOrderAddressId()
                        ->withColumn(OrderAddressTableMap::COMPANY, 'company')
                        ->orderBy('company', Criteria::DESC)
                    ;
                    break;

                case 'customer-name' :
                    $search
                        ->joinCustomer()
                        ->withColumn(CustomerTableMap::FIRSTNAME, 'firstname')
                        ->withColumn(CustomerTableMap::LASTNAME, 'lastname')
                        ->orderBy('lastname', Criteria::ASC)
                        ->orderBy('firstname', Criteria::ASC)
                        ;
                    break;
                case 'customer-name-reverse' :
                    $search
                        ->joinCustomer()
                        ->withColumn(CustomerTableMap::FIRSTNAME, 'firstname')
                        ->withColumn(CustomerTableMap::LASTNAME, 'lastname')
                        ->orderBy('lastname', Criteria::DESC)
                        ->orderBy('firstname', Criteria::DESC)
                    ;
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $order) {
            $tax = 0;
            $amount = $order->getTotalAmount($tax);
            $loopResultRow = new LoopResultRow($order);
            $loopResultRow
                ->set("ID", $order->getId())
                ->set("REF", $order->getRef())
                ->set("CUSTOMER", $order->getCustomerId())
                ->set("DELIVERY_ADDRESS", $order->getDeliveryOrderAddressId())
                ->set("INVOICE_ADDRESS", $order->getInvoiceOrderAddressId())
                ->set("INVOICE_DATE", $order->getInvoiceDate())
                ->set("CURRENCY", $order->getCurrencyId())
                ->set("CURRENCY_RATE", $order->getCurrencyRate())
                ->set("TRANSACTION_REF", $order->getTransactionRef())
                ->set("DELIVERY_REF", $order->getDeliveryRef())
                ->set("INVOICE_REF", $order->getInvoiceRef())
                ->set("POSTAGE", $order->getPostage())
                ->set("PAYMENT_MODULE", $order->getPaymentModuleId())
                ->set("DELIVERY_MODULE", $order->getDeliveryModuleId())
                ->set("STATUS", $order->getStatusId())
                ->set("LANG", $order->getLangId())
                ->set("POSTAGE", $order->getPostage())
                ->set("DISCOUNT", $order->getDiscount())
                ->set("TOTAL_TAX", $tax)
                ->set("TOTAL_AMOUNT", $amount - $tax)
                ->set("TOTAL_TAXED_AMOUNT", $amount)
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
