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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderProductTax as OrderProductTaxModel;
use Thelia\Model\OrderProductTaxQuery;

/**
 * OrderProductTax loop.
 *
 * Class OrderProductTax
 *
 * @author Zzuutt
 *
 * @method int getOrderProduct()
 */
class OrderProductTax extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = OrderProductTaxQuery::create();

        $orderProduct = $this->getOrderProduct();

        $search->filterByOrderProductId($orderProduct, Criteria::EQUAL);

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var OrderProductTaxModel $orderProductTax */
        foreach ($loopResult->getResultDataCollection() as $orderProductTax) {
            $loopResultRow = new LoopResultRow($orderProductTax);
            $loopResultRow->set('ID', $orderProductTax->getId())
                ->set('TITLE', $orderProductTax->getTitle())
                ->set('DESCRIPTION', $orderProductTax->getDescription())
                ->set('AMOUNT', $orderProductTax->getAmount())
                ->set('PROMO_AMOUNT', $orderProductTax->getPromoAmount());
            $this->addOutputFields($loopResultRow, $orderProductTax);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
