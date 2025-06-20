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

use Thelia\Type\EnumListType;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderProductAttributeCombination as OrderProductAttributeCombinationModel;
use Thelia\Model\OrderProductAttributeCombinationQuery;
use Thelia\Type\TypeCollection;

/**
 * OrderProductOrderProductAttributeCombination loop.
 *
 * Class OrderProductAttributeCombination
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int      getOrderProduct()
 * @method string[] getOrder()
 * @method bool     getVirtual()
 */
class OrderProductAttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha_reverse'])
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductAttributeCombinationQuery::create();

        $orderProduct = $this->getOrderProduct();

        $search->filterByOrderProductId($orderProduct, Criteria::EQUAL);

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->orderByAttributeTitle(Criteria::ASC);
                    break;
                case 'alpha_reverse':
                    $search->orderByAttributeTitle(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var OrderProductAttributeCombinationModel $orderAttributeCombination */
        foreach ($loopResult->getResultDataCollection() as $orderAttributeCombination) {
            $loopResultRow = new LoopResultRow($orderAttributeCombination);

            $loopResultRow
                ->set('ID', $orderAttributeCombination->getId())
                ->set('ORDER_PRODUCT_ID', $orderAttributeCombination->getOrderProductId())
                ->set('ATTRIBUTE_TITLE', $orderAttributeCombination->getAttributeTitle())
                ->set('ATTRIBUTE_CHAPO', $orderAttributeCombination->getAttributeChapo())
                ->set('ATTRIBUTE_DESCRIPTION', $orderAttributeCombination->getAttributeDescription())
                ->set('ATTRIBUTE_POSTSCRIPTUM', $orderAttributeCombination->getAttributePostscriptum())
                ->set('ATTRIBUTE_AVAILABILITY_TITLE', $orderAttributeCombination->getAttributeAvTitle())
                ->set('ATTRIBUTE_AVAILABILITY_CHAPO', $orderAttributeCombination->getAttributeAvChapo())
                ->set('ATTRIBUTE_AVAILABILITY_DESCRIPTION', $orderAttributeCombination->getAttributeAvDescription())
                ->set('ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM', $orderAttributeCombination->getAttributeAvPostscriptum())
            ;
            $this->addOutputFields($loopResultRow, $orderAttributeCombination);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
