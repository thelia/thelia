<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Tools\ModelCriteriaTools;

use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Base\ProductCategoryQuery;
use Thelia\Model\Base\FeatureQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 *
 * Feature loop
 *
 *
 * Class Feature
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Feature extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('product'),
            Argument::createIntListTypeArgument('category'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha-reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
            )
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = FeatureQuery::create();

        /* manage translations */
        ModelCriteriaTools::getI18n($search, ConfigQuery::read("default_lang_without_translation", 1), $this->request->getSession()->getLocale());

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible != BooleanOrBothType::ANY) $search->filterByVisible($visible);

        $product = $this->getProduct();
        $category = $this->getCategory();

        if (null !== $product) {
            $productCategories = ProductCategoryQuery::create()->select(array(ProductCategoryTableMap::CATEGORY_ID))->filterByProductId($product, Criteria::IN)->find()->getData();

            if (null === $category) {
                $category = $productCategories;
            } else {
                $category = array_merge($category, $productCategories);
            }
        }

        if (null !== $category) {
            $search->filterByCategory(
                CategoryQuery::create()->filterById($category)->find(),
                Criteria::IN
            );
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
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

        /* perform search */
        $features = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($features as $feature) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $feature->getId())
                ->set("TITLE",$feature->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $feature->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $feature->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $feature->getVirtualColumn('i18n_POSTSCRIPTUM'));

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
