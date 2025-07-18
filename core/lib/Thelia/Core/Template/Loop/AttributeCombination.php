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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\AttributeTableMap;
use Thelia\Model\Map\AttributeTemplateTableMap;
use Thelia\Model\Map\ProductTableMap;

/**
 * Attribute Combination loop.
 *
 * Class AttributeCombination
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int      getProductSaleElements()
 * @method string[] getOrder()
 */
class AttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('product_sale_elements', null, true),
            Argument::createEnumListTypeArgument(
                'order',
                [
                    'alpha', 'alpha_reverse', 'manual', 'manual_reverse',
                ],
                'alpha',
            ),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = AttributeCombinationQuery::create();

        /* manage attribute translations */
        $this->configureI18nProcessing(
            $search,
            ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
            AttributeTableMap::TABLE_NAME,
            'ATTRIBUTE_ID',
        );

        /* manage attributeAv translations */
        $this->configureI18nProcessing(
            $search,
            ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
            AttributeAvTableMap::TABLE_NAME,
            'ATTRIBUTE_AV_ID',
        );

        $productSaleElements = $this->getProductSaleElements();

        $search->filterByProductSaleElementsId($productSaleElements, Criteria::EQUAL);

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn(AttributeTableMap::TABLE_NAME.'_i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn(AttributeTableMap::TABLE_NAME.'_i18n_TITLE');
                    break;
                case 'manual':
                    $this->orderByTemplateAttributePosition($search, Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $this->orderByTemplateAttributePosition($search, Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\AttributeCombination $attributeCombination */
        foreach ($loopResult->getResultDataCollection() as $attributeCombination) {
            $loopResultRow = new LoopResultRow($attributeCombination);

            $loopResultRow
                ->set('LOCALE', $this->locale)

                ->set('ATTRIBUTE_ID', $attributeCombination->getAttributeId())
                ->set('ATTRIBUTE_TITLE', $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME.'_i18n_TITLE'))
                ->set('ATTRIBUTE_CHAPO', $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME.'_i18n_CHAPO'))
                ->set('ATTRIBUTE_DESCRIPTION', $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME.'_i18n_DESCRIPTION'))
                ->set('ATTRIBUTE_POSTSCRIPTUM', $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME.'_i18n_POSTSCRIPTUM'))

                ->set('ATTRIBUTE_AVAILABILITY_ID', $attributeCombination->getAttributeAvId())
                ->set('ATTRIBUTE_AVAILABILITY_TITLE', $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME.'_i18n_TITLE'))
                ->set('ATTRIBUTE_AVAILABILITY_CHAPO', $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME.'_i18n_CHAPO'))
                ->set('ATTRIBUTE_AVAILABILITY_DESCRIPTION', $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME.'_i18n_DESCRIPTION'))
                ->set('ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM', $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME.'_i18n_POSTSCRIPTUM'));
            $this->addOutputFields($loopResultRow, $attributeCombination);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @param string $order Criteria::ASC|Criteria::DESC
     */
    protected function orderByTemplateAttributePosition(AttributeCombinationQuery $search, string $order): AttributeCombinationQuery
    {
        $search
            ->useProductSaleElementsQuery()
            ->joinProduct()
            ->endUse()
            ->useAttributeQuery()
            ->leftJoinAttributeTemplate(AttributeTemplateTableMap::TABLE_NAME)
            ->addJoinCondition(
                AttributeTemplateTableMap::TABLE_NAME,
                AttributeTemplateTableMap::COL_TEMPLATE_ID.Criteria::EQUAL.ProductTableMap::COL_TEMPLATE_ID,
            )
            ->endUse()
            ->orderBy(AttributeTemplateTableMap::COL_POSITION, $order);

        return $search;
    }
}
