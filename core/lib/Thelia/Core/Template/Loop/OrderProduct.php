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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * OrderProduct loop.
 *
 * Class OrderProduct
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int         getOrder()
 * @method int[]       getId()
 * @method bool|string getVirtual()
 * @method int|null    getShipment()
 */
class OrderProduct extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order', null, true),
            Argument::createIntListTypeArgument('id'),
            Argument::createBooleanOrBothTypeArgument('virtual', BooleanOrBothType::ANY),
            // A delivery note of one shipment lists only the lines that shipment carries,
            // with the quantity it carries: the rest of the order leaves in another parcel.
            Argument::createIntTypeArgument('shipment'),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = OrderProductQuery::create();

        $search->joinOrderProductTax('opt', Criteria::LEFT_JOIN)
            ->withColumn('SUM(`opt`.AMOUNT)', 'TOTAL_TAX')
            ->withColumn('SUM(`opt`.PROMO_AMOUNT)', 'TOTAL_PROMO_TAX')
            ->groupById();

        // new join to get the product id if it exists
        $pseJoin = new Join(
            OrderProductTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
            ProductSaleElementsTableMap::COL_ID,
            Criteria::LEFT_JOIN,
        );
        $search
            ->addJoinObject($pseJoin)
            ->addAsColumn(
                'product_id',
                ProductSaleElementsTableMap::COL_PRODUCT_ID,
            );

        $order = $this->getOrder();

        $search->filterByOrderId($order, Criteria::EQUAL);

        $virtual = $this->getVirtual();

        if (BooleanOrBothType::ANY !== $virtual) {
            if ($virtual) {
                $search
                    ->filterByVirtual(1, Criteria::EQUAL)
                    ->filterByVirtualDocument(null, Criteria::NOT_EQUAL);
            } else {
                $search
                    ->filterByVirtual(0);
            }
        }

        if (null !== $this->getId()) {
            $search->filterById($this->getId(), Criteria::IN);
        }

        $shipment = $this->getShipment();

        if (null !== $shipment) {
            // One item per (shipment, order line) at most, so this join adds no row to
            // the tax rows summed above. MAX() keeps the column valid whatever the SQL mode.
            $search
                ->joinOrderShipmentItem('osi', Criteria::INNER_JOIN)
                ->where('osi.OrderShipmentId = ?', $shipment, \PDO::PARAM_INT)
                ->withColumn('MAX(`osi`.QUANTITY)', 'SHIPMENT_QUANTITY');
        }

        $search->orderById(Criteria::ASC);

        return $search;
    }

    /**
     * @throws PropelException
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\OrderProduct $orderProduct */
        foreach ($loopResult->getResultDataCollection() as $orderProduct) {
            $loopResultRow = new LoopResultRow($orderProduct);

            // Every total below is the one of the quantity this document is about: the
            // whole line, or the part of it a given shipment carries.
            $quantity = null !== $this->getShipment()
                ? (float) $orderProduct->getVirtualColumn('SHIPMENT_QUANTITY')
                : $orderProduct->getQuantity();

            // The loop walks orders of several shops' worth of history at once in the back
            // office, and the rounding an order was invoiced with depends on when it was
            // placed: the line reads it itself. The tax of a unit is summed in SQL.
            $unitTax = (float) $orderProduct->getVirtualColumn('TOTAL_TAX'); // 1,39755 => 1.4
            $unitPromoTax = (float) $orderProduct->getVirtualColumn('TOTAL_PROMO_TAX');

            $regular = $orderProduct->getLineTotals(false, $quantity, $unitTax);
            $promotional = $orderProduct->getLineTotals(true, $quantity, $unitPromoTax);

            $tax = $regular['unitTax'];
            $promoTax = $promotional['unitTax'];
            $taxedPrice = $regular['taxedUnitPrice'];
            $taxedPromoPrice = $promotional['taxedUnitPrice'];
            $totalPrice = $regular['untaxed'];
            $totalPromoPrice = $promotional['untaxed'];
            $totalTaxedPrice = $regular['taxed'];
            $totalTaxedPromoPrice = $promotional['taxed'];
            $totalTax = $regular['tax'];
            $totalPromoTax = $promotional['tax'];

            $loopResultRow->set('ID', $orderProduct->getId())
                ->set('REF', $orderProduct->getProductRef())
                ->set('PRODUCT_ID', $orderProduct->getVirtualColumn('product_id'))
                ->set('PRODUCT_SALE_ELEMENTS_ID', $orderProduct->getProductSaleElementsId())
                ->set('PRODUCT_SALE_ELEMENTS_REF', $orderProduct->getProductSaleElementsRef())
                ->set('WAS_NEW', 1 === $orderProduct->getWasNew() ? 1 : 0)
                ->set('WAS_IN_PROMO', 1 === $orderProduct->getWasInPromo() ? 1 : 0)
                ->set('WEIGHT', $orderProduct->getWeight())
                ->set('TITLE', $orderProduct->getTitle())
                ->set('CHAPO', $orderProduct->getChapo())
                ->set('DESCRIPTION', $orderProduct->getDescription())
                ->set('POSTSCRIPTUM', $orderProduct->getPostscriptum())
                ->set('VIRTUAL', $orderProduct->getVirtual())
                ->set('VIRTUAL_DOCUMENT', $orderProduct->getVirtualDocument())
                // What the line stands for — a good, or a service the shop invoiced beside
                // them. A picking list leaves the services out, an invoice states them.
                ->set('LINE_TYPE', $orderProduct->getLineType())
                ->set('QUANTITY', $quantity)

                ->set('PRICE', $orderProduct->getPrice())
                ->set('PRICE_TAX', $tax)
                ->set('TAXED_PRICE', $taxedPrice)
                ->set('PROMO_PRICE', $orderProduct->getPromoPrice())
                ->set('PROMO_PRICE_TAX', $promoTax)
                ->set('TAXED_PROMO_PRICE', $taxedPromoPrice)
                ->set('TOTAL_PRICE', $totalPrice)
                ->set('TOTAL_TAXED_PRICE', $totalTaxedPrice)
                ->set('TOTAL_PROMO_PRICE', $totalPromoPrice)
                ->set('TOTAL_TAXED_PROMO_PRICE', $totalTaxedPromoPrice)

                ->set('TAX_RULE_TITLE', $orderProduct->getTaxRuleTitle())
                ->set('TAX_RULE_DESCRIPTION', $orderProduct->getTaxRuledescription())
                ->set('PARENT', $orderProduct->getParent())
                ->set('EAN_CODE', $orderProduct->getEanCode())
                ->set('MPN', $orderProduct->getMpn())
                ->set('CART_ITEM_ID', $orderProduct->getCartItemId())

                ->set('REAL_PRICE', $orderProduct->getWasInPromo() ? $orderProduct->getPromoPrice() : $orderProduct->getPrice())
                ->set('REAL_TAXED_PRICE', $orderProduct->getWasInPromo() ? $taxedPromoPrice : $taxedPrice)
                ->set('REAL_PRICE_TAX', $orderProduct->getWasInPromo() ? $promoTax : $tax)

                ->set('REAL_TOTAL_PRICE', $orderProduct->getWasInPromo() ? $totalPromoPrice : $totalPrice)
                ->set('REAL_TOTAL_TAXED_PRICE', $orderProduct->getWasInPromo() ? $totalTaxedPromoPrice : $totalTaxedPrice)
                ->set('REAL_TOTAL_PRICE_TAX', $orderProduct->getWasInPromo() ? $totalPromoTax : $totalTax);
            $this->addOutputFields($loopResultRow, $orderProduct);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
