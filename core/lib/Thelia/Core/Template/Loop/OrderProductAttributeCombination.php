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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\OrderProductAttributeCombinationQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Model\OrderProductAttributeCombination as OrderProductAttributeCombinationModel;

/**
 *
 * OrderProductOrderProductAttributeCombination loop
 *
 * Class OrderProductAttributeCombination
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getOrderProduct()
 * @method string[] getOrder()
 * @method bool getVirtual()
 */
class OrderProductAttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse'))
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

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->orderByAttributeTitle(Criteria::ASC);
                    break;
                case "alpha_reverse":
                    $search->orderByAttributeTitle(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderProductAttributeCombinationModel $orderAttributeCombination */
        foreach ($loopResult->getResultDataCollection() as $orderAttributeCombination) {
            $loopResultRow = new LoopResultRow($orderAttributeCombination);

            $loopResultRow
                ->set("ATTRIBUTE_TITLE", $orderAttributeCombination->getAttributeTitle())
                ->set("ATTRIBUTE_CHAPO", $orderAttributeCombination->getAttributeChapo())
                ->set("ATTRIBUTE_DESCRIPTION", $orderAttributeCombination->getAttributeDescription())
                ->set("ATTRIBUTE_POSTSCRIPTUM", $orderAttributeCombination->getAttributePostscriptum())
                ->set("ATTRIBUTE_AVAILABILITY_TITLE", $orderAttributeCombination->getAttributeAvTitle())
                ->set("ATTRIBUTE_AVAILABILITY_CHAPO", $orderAttributeCombination->getAttributeAvChapo())
                ->set("ATTRIBUTE_AVAILABILITY_DESCRIPTION", $orderAttributeCombination->getAttributeAvDescription())
                ->set("ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM", $orderAttributeCombination->getAttributeAvPostscriptum())
            ;
            $this->addOutputFields($loopResultRow, $orderAttributeCombination);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
