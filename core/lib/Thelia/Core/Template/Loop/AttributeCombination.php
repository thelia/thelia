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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Thelia\Model\Map\AttributeTableMap;
use Thelia\Model\Map\AttributeTemplateTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * Attribute Combination loop
 *
 * Class AttributeCombination
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('product_sale_elements', null, true),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'manual', 'manual_reverse'))
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AttributeCombinationQuery::create();

        /* manage attribute translations */
        $this->configureI18nProcessing(
            $search,
            array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
            AttributeTableMap::TABLE_NAME,
            'ATTRIBUTE_ID'
        );

        /* manage attributeAv translations */
        $this->configureI18nProcessing(
            $search,
            array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
            AttributeAvTableMap::TABLE_NAME,
            'ATTRIBUTE_AV_ID'
        );

        $productSaleElements = $this->getProduct_sale_elements();

        $search->filterByProductSaleElementsId($productSaleElements, Criteria::EQUAL);

        $orders  = $this->getOrder();

        if (in_array('manual', $orders) || in_array('manual_reverse', $orders)) {
            $template_id = ProductQuery::create()
                ->useProductSaleElementsQuery()
                    ->filterById($productSaleElements)
                ->endUse()
                ->select(ProductTableMap::TEMPLATE_ID)
                ->findOne()
            ;

            if (empty($template_id)) {
                return null;
            }

            $attributeJoin = new Join(
                AttributeCombinationTableMap::ATTRIBUTE_ID,
                AttributeTemplateTableMap::ATTRIBUTE_ID,
                Criteria::INNER_JOIN
            );

            $search->addJoinObject($attributeJoin)
            ->where(AttributeTemplateTableMap::TEMPLATE_ID."=?", $template_id, \PDO::PARAM_INT)
            ;
        }

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn(AttributeTableMap::TABLE_NAME . '_i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn(AttributeTableMap::TABLE_NAME . '_i18n_TITLE');
                    break;
                case "manual":
                    $search->addAscendingOrderByColumn(AttributeTemplateTableMap::POSITION);
                    break;
                case "manual_reverse":
                    $search->addDescendingOrderByColumn(AttributeTemplateTableMap::POSITION);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\AttributeCombination $attributeCombination */
        foreach ($loopResult->getResultDataCollection() as $attributeCombination) {
            $loopResultRow = new LoopResultRow($attributeCombination);

            $loopResultRow
                ->set("LOCALE", $this->locale)

                ->set("ATTRIBUTE_ID", $attributeCombination->getAttributeId())
                ->set("ATTRIBUTE_TITLE", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_TITLE'))
                ->set("ATTRIBUTE_CHAPO", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_CHAPO'))
                ->set("ATTRIBUTE_DESCRIPTION", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ->set("ATTRIBUTE_POSTSCRIPTUM", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'))

                ->set("ATTRIBUTE_AVAILABILITY_ID", $attributeCombination->getAttributeAvId())
                ->set("ATTRIBUTE_AVAILABILITY_TITLE", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_TITLE'))
                ->set("ATTRIBUTE_AVAILABILITY_CHAPO", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_CHAPO'))
                ->set("ATTRIBUTE_AVAILABILITY_DESCRIPTION", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ->set("ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'));
            $this->addOutputFields($loopResultRow, $attributeCombination);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
