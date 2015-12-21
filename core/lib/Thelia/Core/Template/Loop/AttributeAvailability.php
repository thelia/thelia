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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeAv as AttributeAvModel;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 * AttributeAvailability loop
 *
 *
 * Class AttributeAvailability
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getAttribute()
 * @method int getProduct()
 * @method int[] getExclude()
 * @method string[] getOrder()
 */
class AttributeAvailability extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('attribute'),
            Argument::createIntTypeArgument('product'),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AttributeAvQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $attribute = $this->getAttribute();

        if (null !== $attribute) {
            $search->filterByAttributeId($attribute, Criteria::IN);
        }

        $product = $this->getProduct();

        if (null !== $product) {
            // Return only Attributes Av that are part on a product's combination

            /* The request is:
            select * from attribute_av aav
            left join attribute_combination ac on ac.attribute_av_id = aav.id
            left join product_sale_elements pse on pse.id = ac.product_sale_elements_id
            where aav.attribute_id=3 and pse.product_id = 279
            group by aav.id
             */

            $pseJoin = new Join();
            $pseJoin->addCondition(
                AttributeCombinationTableMap::PRODUCT_SALE_ELEMENTS_ID,
                ProductSaleElementsTableMap::ID,
                Criteria::EQUAL
            );
            $pseJoin->setJoinType(Criteria::LEFT_JOIN);

            $search
                ->leftJoinAttributeCombination('attribute_combination')
                ->groupById()
                ->addJoinObject($pseJoin)
                ->where(ProductSaleElementsTableMap::PRODUCT_ID."=?", $product, \PDO::PARAM_INT)
            ;
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AttributeAvModel $attributeAv */
        foreach ($loopResult->getResultDataCollection() as $attributeAv) {
            $loopResultRow = new LoopResultRow($attributeAv);
            $loopResultRow
                ->set("ID", $attributeAv->getId())
                ->set("ATTRIBUTE_ID", $attributeAv->getAttributeId())
                ->set("IS_TRANSLATED", $attributeAv->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $attributeAv->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $attributeAv->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $attributeAv->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $attributeAv->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION", $attributeAv->getPosition())
            ;
            $this->addOutputFields($loopResultRow, $attributeAv);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
