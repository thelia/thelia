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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method null|int[] getId()
 * @method null|int[] getRef()
 * @method null|int[] getInvoiceRef()
 * @method null|int[] getDeliveryRef()
 * @method null|int[] getTransactionRef()
 * @method null|string getCustomer()
 * @method null|string[] getStatus()
 * @method null|int[] getExcludeStatus()
 * @method null|string[] getStatusCode()
 * @method null|string[] getExcludeStatusCode()
 * @method null|string[] getOrder()
 * @method null|bool getWithPrevNextInfo()
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
            Argument::createAnyListTypeArgument('ref'),
            Argument::createAnyListTypeArgument('invoice_ref'),
            Argument::createAnyListTypeArgument('delivery_ref'),
            Argument::createAnyListTypeArgument('transaction_ref'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(['current', '*'])
                ),
                'current'
            ),
            new Argument(
                'status',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(['*'])
                )
            ),
            Argument::createIntListTypeArgument('exclude_status'),
            new Argument(
                'status_code',
                new TypeCollection(
                    new Type\AnyListType(),
                    new Type\EnumType(['*'])
                )
            ),
            Argument::createAnyListTypeArgument('exclude_status_code'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id', 'id-reverse',
                            'reference', 'reference-reverse',
                            'create-date', 'create-date-reverse',
                            'invoice-date', 'invoice-date-reverse',
                            'company', 'company-reverse',
                            'customer-name', 'customer-name-reverse',
                            'status', 'status-reverse'
                        ]
                    )
                ),
                'create-date-reverse'
            )
        );
    }

    public function getSearchIn()
    {
        return [
            'ref',
            'invoice_ref',
            'delivery_ref',
            'transaction_ref',
            'customer_ref',
            'customer_firstname',
            'customer_lastname',
            'customer_email',
        ];
    }

    /**
     * @param OrderQuery $search
     * @param $searchTerm
     * @param $searchIn
     * @param $searchCriteria
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();
        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }
            switch ($searchInElement) {
                case 'ref':
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case 'invoice_ref':
                    $search->filterByInvoiceRef($searchTerm, $searchCriteria);
                    break;
                case 'delivery_ref':
                    $search->filterByDeliveryRef($searchTerm, $searchCriteria);
                    break;
                case 'transaction_ref':
                    $search->filterByTransactionRef($searchTerm, $searchCriteria);
                    break;
                case 'customer_ref':
                    $search->filterByCustomer(
                        CustomerQuery::create()->filterByRef($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case 'customer_firstname':
                    $search->filterByOrderAddressRelatedByInvoiceOrderAddressId(
                        OrderAddressQuery::create()->filterByFirstname($searchTerm, $searchCriteria)->find()
                    );
                    $search->_or();
                    $search->filterByOrderAddressRelatedByDeliveryOrderAddressId(
                        OrderAddressQuery::create()->filterByFirstname($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case 'customer_lastname':
                    $search->filterByOrderAddressRelatedByInvoiceOrderAddressId(
                        OrderAddressQuery::create()->filterByLastname($searchTerm, $searchCriteria)->find()
                    );
                    $search->_or();
                    $search->filterByOrderAddressRelatedByDeliveryOrderAddressId(
                        OrderAddressQuery::create()->filterByLastname($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case 'customer_email':
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

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $ref = $this->getRef()) {
            $search->filterByRef($ref, Criteria::IN);
        }

        if (null !== $ref = $this->getDeliveryRef()) {
            $search->filterByDeliveryRef($ref, Criteria::IN);
        }

        if (null !== $ref = $this->getInvoiceRef()) {
            $search->filterByInvoiceRef($ref, Criteria::IN);
        }

        if (null !== $ref = $this->getTransactionRef()) {
            $search->filterByTransactionRef($ref, Criteria::IN);
        }

        $customer = $this->getCustomer();

        if ($customer === 'current') {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
            }

            $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
        } elseif ($customer !== '*') {
            $search->filterByCustomerId($customer, Criteria::EQUAL);
        }

        $status = $this->getStatus();

        if (null !== $status && $status !== '*') {
            $search->filterByStatusId($status, Criteria::IN);
        }

        if (null !== $excludeStatus = $this->getExcludeStatus()) {
            $search->filterByStatusId($excludeStatus, Criteria::NOT_IN);
        }

        $statusCode = $this->getStatusCode();

        if (null !== $statusCode && $statusCode !== '*') {
            $search
                ->useOrderStatusQuery()
                ->filterByCode($statusCode, Criteria::IN)
                ->endUse();
        }

        if (null !== $excludeStatusCode = $this->getExcludeStatusCode()) {
            $search
                ->useOrderStatusQuery()
                ->filterByCode($excludeStatusCode, Criteria::NOT_IN)
                ->endUse();
        }

        $orderers = $this->getOrder();

        foreach ($orderers as $orderer) {
            switch ($orderer) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'reference':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'reference-reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;
                case 'create-date':
                    $search->orderByCreatedAt(Criteria::ASC);
                    break;
                case 'create-date-reverse':
                    $search->orderByCreatedAt(Criteria::DESC);
                    break;
                case 'invoice-date':
                    $search->orderByInvoiceDate(Criteria::ASC);
                    break;
                case 'invoice-date-reverse':
                    $search->orderByInvoiceDate(Criteria::DESC);
                    break;
                case 'status':
                    $search->orderByStatusId(Criteria::ASC);
                    break;
                case 'status-reverse':
                    $search->orderByStatusId(Criteria::DESC);
                    break;
                case 'company':
                    $search
                        ->joinOrderAddressRelatedByDeliveryOrderAddressId()
                        ->withColumn(OrderAddressTableMap::COL_COMPANY, 'company')
                        ->orderBy('company', Criteria::ASC);
                    break;
                case 'company-reverse':
                    $search
                        ->joinOrderAddressRelatedByDeliveryOrderAddressId()
                        ->withColumn(OrderAddressTableMap::COL_COMPANY, 'company')
                        ->orderBy('company', Criteria::DESC);
                    break;
                case 'customer-name':
                    $search
                        ->joinCustomer()
                        ->withColumn(CustomerTableMap::COL_FIRSTNAME, 'firstname')
                        ->withColumn(CustomerTableMap::COL_LASTNAME, 'lastname')
                        ->orderBy('lastname', Criteria::ASC)
                        ->orderBy('firstname', Criteria::ASC);
                    break;
                case 'customer-name-reverse':
                    $search
                        ->joinCustomer()
                        ->withColumn(CustomerTableMap::COL_FIRSTNAME, 'firstname')
                        ->withColumn(CustomerTableMap::COL_LASTNAME, 'lastname')
                        ->orderBy('lastname', Criteria::DESC)
                        ->orderBy('firstname', Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    /**
     * @return LoopResult
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function parseResults(LoopResult $loopResult)
    {
        $lastLegacyOrderId = ConfigQuery::read('last_legacy_rounding_order_id', 0);

        /**  @var \Thelia\Model\Order $order */
        foreach ($loopResult->getResultDataCollection() as $order) {
            $tax = $itemsTax = 0;

            $amount = $order->getTotalAmount($tax);
            $itemsAmount = $order->getTotalAmount($itemsTax, false, false);

            // Legacy orders have no discount tax calculation
            if ($order->getId() <= $lastLegacyOrderId) {
                $discountWithoutTax = $order->getDiscount();
            } else {
                $discountWithoutTax = Calculator::getUntaxedOrderDiscount($order);
            }

            $hasVirtualDownload = $order->hasVirtualProduct();

            $loopResultRow = new LoopResultRow($order);
            $loopResultRow
                ->set('ID', $order->getId())
                ->set('REF', $order->getRef())
                ->set('CUSTOMER', $order->getCustomerId())
                ->set('DELIVERY_ADDRESS', $order->getDeliveryOrderAddressId())
                ->set('INVOICE_ADDRESS', $order->getInvoiceOrderAddressId())
                ->set('INVOICE_DATE', $order->getInvoiceDate())
                ->set('CURRENCY', $order->getCurrencyId())
                ->set('CURRENCY_RATE', $order->getCurrencyRate())
                ->set('TRANSACTION_REF', $order->getTransactionRef())
                ->set('DELIVERY_REF', $order->getDeliveryRef())
                ->set('INVOICE_REF', $order->getInvoiceRef())
                ->set('VIRTUAL', $hasVirtualDownload)
                ->set('POSTAGE', $order->getPostage())
                ->set('POSTAGE_TAX', $order->getPostageTax())
                ->set('POSTAGE_UNTAXED', $order->getUntaxedPostage())
                ->set('POSTAGE_TAX_RULE_TITLE', $order->getPostageTaxRuleTitle())
                ->set('PAYMENT_MODULE', $order->getPaymentModuleId())
                ->set('DELIVERY_MODULE', $order->getDeliveryModuleId())
                ->set('STATUS', $order->getStatusId())
                ->set('STATUS_CODE', $order->getOrderStatus()->getCode())
                ->set('LANG', $order->getLangId())
                ->set('DISCOUNT', $order->getDiscount())
                ->set('DISCOUNT_WITHOUT_TAX', $discountWithoutTax)
                ->set('DISCOUNT_TAX', $order->getDiscount() - $discountWithoutTax)
                ->set('TOTAL_ITEMS_TAX', $itemsTax)
                ->set('TOTAL_ITEMS_AMOUNT', $itemsAmount - $itemsTax)
                ->set('TOTAL_TAXED_ITEMS_AMOUNT', $itemsAmount)
                ->set('TOTAL_TAX', $tax)
                ->set('TOTAL_AMOUNT', $amount - $tax)
                ->set('TOTAL_TAXED_AMOUNT', $amount)
                ->set('WEIGHT', $order->getWeight())
                ->set('HAS_PAID_STATUS', $order->isPaid())
                ->set('IS_PAID', $order->isPaid(false))
                ->set('IS_CANCELED', $order->isCancelled())
                ->set('IS_NOT_PAID', $order->isNotPaid())
                ->set('IS_SENT', $order->isSent())
                ->set('IS_PROCESSING', $order->isProcessing());

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = OrderQuery::create()
                    ->filterById($order->getId(), Criteria::LESS_THAN)
                    ->filterByStatusId($order->getStatusId(), Criteria::EQUAL);

                $previous = $previousQuery
                    ->orderById(Criteria::DESC)
                    ->findOne();

                $nextQuery = OrderQuery::create()
                    ->filterById($order->getId(), Criteria::GREATER_THAN)
                    ->filterByStatusId($order->getStatusId(), Criteria::EQUAL);

                $next = $nextQuery
                    ->orderById(Criteria::ASC)
                    ->findOne();

                $loopResultRow
                    ->set('HAS_PREVIOUS', $previous !== null ? 1 : 0)
                    ->set('HAS_NEXT', $next !== null ? 1 : 0)
                    ->set('PREVIOUS', $previous !== null ? $previous->getId() : -1)
                    ->set('NEXT', $next !== null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $order);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
