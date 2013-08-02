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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Log\Tlog;

use Thelia\Model\Base\FeatureProductQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Product loop
 *
 *
 * Class Product
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Product extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            new Argument(
                'ref',
                new TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
            Argument::createIntListTypeArgument('category'),
            //Argument::createBooleanTypeArgument('new'),
            //Argument::createBooleanTypeArgument('promo'),
            //Argument::createFloatTypeArgument('min_price'),
            //Argument::createFloatTypeArgument('max_price'),
            //Argument::createIntTypeArgument('min_stock'),
            //Argument::createFloatTypeArgument('min_weight'),
            //Argument::createFloatTypeArgument('max_weight'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('current_category'),
            Argument::createIntTypeArgument('depth', 1),
            Argument::createBooleanTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', /*'min_price', 'max_price',*/ 'manual', 'manual_reverse', 'ref', /*'promo', 'new',*/ 'random', 'given_id'))
                ),
                'alpha'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createIntListTypeArgument('exclude_category'),
            new Argument(
                'feature_available',
                new TypeCollection(
                    new Type\IntToCombinedIntsListType()
                )
            ),
            new Argument(
                'feature_values',
                new TypeCollection(
                    new Type\IntToCombinedStringsListType()
                )
            )
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     * @throws \InvalidArgumentException
     */
    public function exec(&$pagination)
    {
        $search = ProductQuery::create();

        //$search->withColumn('CASE WHEN ' . ProductTableMap::PROMO . '=1 THEN ' . ProductTableMap::PRICE2 . ' ELSE ' . ProductTableMap::PRICE . ' END', 'real_price');

        $id = $this->getId();

        if (!is_null($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $ref = $this->getRef();

        if (!is_null($ref)) {
            $search->filterByRef($ref, Criteria::IN);
        }

      	$category = $this->getCategory();

        if (!is_null($category)) {
            $categories = CategoryQuery::create()->filterById($category, Criteria::IN)->find();

            $depth = $this->getDepth();

            if(null !== $depth) {
                foreach(CategoryQuery::findAllChild($category, $depth) as $subCategory) {
                    $categories->prepend($subCategory);
                }
            }

            $search->filterByCategory(
                $categories,
                Criteria::IN
            );
        }

        /*$new = $this->getNew();

        if ($new === true) {
            $search->filterByNewness(1, Criteria::EQUAL);
        } else if($new === false) {
            $search->filterByNewness(0, Criteria::EQUAL);
        }

        $promo = $this->getPromo();

        if ($promo === true) {
            $search->filterByPromo(1, Criteria::EQUAL);
        } else if($promo === false) {
            $search->filterByNewness(0, Criteria::EQUAL);
        }

        $min_stock = $this->getMin_stock();

        if (null != $min_stock) {
            $search->filterByQuantity($min_stock, Criteria::GREATER_EQUAL);
        }

        $min_price = $this->getMin_price();*/

        //if(null !== $min_price) {
            /**
             * Following should work but does not :
             *
             *  $search->filterBy('real_price', $max_price, Criteria::GREATER_EQUAL);
             */
            /*$search->condition('in_promo', ProductTableMap::PROMO . Criteria::EQUAL . '1')
                    ->condition('not_in_promo', ProductTableMap::PROMO . Criteria::NOT_EQUAL . '1')
                    ->condition('min_price2', ProductTableMap::PRICE2 . Criteria::GREATER_EQUAL . '?', $min_price)
                    ->condition('min_price', ProductTableMap::PRICE . Criteria::GREATER_EQUAL . '?', $min_price)
                    ->combine(array('in_promo', 'min_price2'), Criteria::LOGICAL_AND, 'in_promo_min_price')
                    ->combine(array('not_in_promo', 'min_price'), Criteria::LOGICAL_AND, 'not_in_promo_min_price')
                    ->where(array('not_in_promo_min_price', 'in_promo_min_price'), Criteria::LOGICAL_OR);
        }

        $max_price = $this->getMax_price();*/

        //if(null !== $max_price) {
            /**
             * Following should work but does not :
             *
             *  $search->filterBy('real_price', $max_price, Criteria::LESS_EQUAL);
             */
            /*$search->condition('in_promo', ProductTableMap::PROMO . Criteria::EQUAL . '1')
                    ->condition('not_in_promo', ProductTableMap::PROMO . Criteria::NOT_EQUAL . '1')
                    ->condition('max_price2', ProductTableMap::PRICE2 . Criteria::LESS_EQUAL . '?', $max_price)
                    ->condition('max_price', ProductTableMap::PRICE . Criteria::LESS_EQUAL . '?', $max_price)
                    ->combine(array('in_promo', 'max_price2'), Criteria::LOGICAL_AND, 'in_promo_max_price')
                    ->combine(array('not_in_promo', 'max_price'), Criteria::LOGICAL_AND, 'not_in_promo_max_price')
                    ->where(array('not_in_promo_max_price', 'in_promo_max_price'), Criteria::LOGICAL_OR);
        }*/

        /*$min_weight = $this->getMin_weight();

        if(null !== $min_weight) {
            $search->filterByWeight($min_weight, Criteria::GREATER_EQUAL);
        }

        $max_weight = $this->getMax_weight();

        if(null !== $max_weight) {
            $search->filterByWeight($max_weight, Criteria::LESS_EQUAL);
        }*/

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->request->get("product_id"));
        } elseif($current === false) {
            $search->filterById($this->request->get("product_id"), Criteria::NOT_IN);
        }

        $current_category = $this->getCurrent_category();

        if ($current_category === true) {
            $search->filterByCategory(
                CategoryQuery::create()->filterByProduct(
                    ProductCategoryQuery::create()->filterByProductId(
                        $this->request->get("product_id"),
                        Criteria::EQUAL
                    )->find(),
                    Criteria::IN
                )->find(),
                Criteria::IN
            );
        } elseif($current_category === false) {
            $search->filterByCategory(
                CategoryQuery::create()->filterByProduct(
                    ProductCategoryQuery::create()->filterByProductId(
                        $this->request->get("product_id"),
                        Criteria::EQUAL
                    )->find(),
                    Criteria::IN
                )->find(),
                Criteria::NOT_IN
            );
        }

        $visible = $this->getVisible();

        $search->filterByVisible($visible);

        $orders  = $this->getOrder();


        foreach($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn(\Thelia\Model\Map\ProductI18nTableMap::TITLE);
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn(\Thelia\Model\Map\ProductI18nTableMap::TITLE);
                    break;
                /*case "min_price":
                    $search->orderBy('real_price', Criteria::ASC);
                    break;
                case "max_price":
                    $search->orderBy('real_price', Criteria::DESC);
                    break;*/
                case "manual":
                    if(null === $category || count($category) != 1)
                        throw new \InvalidArgumentException('Manual order cannot be set without single category argument');
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    if(null === $category || count($category) != 1)
                        throw new \InvalidArgumentException('Manual order cannot be set without single category argument');
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case "ref":
                    $search->orderByRef(Criteria::ASC);
                    break;
                /*case "promo":
                    $search->orderByPromo(Criteria::DESC);
                    break;
                case "new":
                    $search->orderByNewness(Criteria::DESC);
                    break;*/
                case "given_id":
                    if(null === $id)
                        throw new \InvalidArgumentException('Given_id order cannot be set without `id` argument');
                    foreach($id as $singleId) {
                        $givenIdMatched = 'given_id_matched_' . $singleId;
                        $search->withColumn(ProductTableMap::ID . "='$singleId'", $givenIdMatched);
                        $search->orderBy($givenIdMatched, Criteria::DESC);
                    }
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
            }
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $exclude_category = $this->getExclude_category();

        if (!is_null($exclude_category)) {
            $search->filterByCategory(
                CategoryQuery::create()->filterById($exclude_category, Criteria::IN)->find(),
                Criteria::NOT_IN
            );
        }

        $feature_available = $this->getFeature_available();

        if(null !== $feature_available) {
            foreach($feature_available as $feature => $feature_choice) {
                foreach($feature_choice['values'] as $feature_av) {
                    $featureAlias = 'fa_' . $feature;
                    if($feature_av != '*')
                        $featureAlias .= '_' . $feature_av;
                    $search->joinFeatureProduct($featureAlias, Criteria::LEFT_JOIN)
                        ->addJoinCondition($featureAlias, "`$featureAlias`.FEATURE_ID = ?", $feature, null, \PDO::PARAM_INT);
                    if($feature_av != '*')
                        $search->addJoinCondition($featureAlias, "`$featureAlias`.FEATURE_AV_ID = ?", $feature_av, null, \PDO::PARAM_INT);
                }

                /* format for mysql */
                $sqlWhereString = $feature_choice['expression'];
                if($sqlWhereString == '*') {
                    $sqlWhereString = 'NOT ISNULL(`fa_' . $feature . '`.ID)';
                } else {
                    $sqlWhereString = preg_replace('#([0-9]+)#', 'NOT ISNULL(`fa_' . $feature . '_' . '\1`.ID)', $sqlWhereString);
                    $sqlWhereString = str_replace('&', ' AND ', $sqlWhereString);
                    $sqlWhereString = str_replace('|', ' OR ', $sqlWhereString);
                }

                $search->where("(" . $sqlWhereString . ")");
            }
        }

        $feature_values = $this->getFeature_values();

        if(null !== $feature_values) {
            foreach($feature_values as $feature => $feature_choice) {
                foreach($feature_choice['values'] as $feature_value) {
                    $featureAlias = 'fv_' . $feature;
                    if($feature_value != '*')
                        $featureAlias .= '_' . $feature_value;
                    $search->joinFeatureProduct($featureAlias, Criteria::LEFT_JOIN)
                        ->addJoinCondition($featureAlias, "`$featureAlias`.FEATURE_ID = ?", $feature, null, \PDO::PARAM_INT);
                    if($feature_value != '*')
                        $search->addJoinCondition($featureAlias, "`$featureAlias`.BY_DEFAULT = ?", $feature_value, null, \PDO::PARAM_STR);
                }

                /* format for mysql */
                $sqlWhereString = $feature_choice['expression'];
                if($sqlWhereString == '*') {
                    $sqlWhereString = 'NOT ISNULL(`fv_' . $feature . '`.ID)';
                } else {
                    $sqlWhereString = preg_replace('#([a-zA-Z0-9_\-]+)#', 'NOT ISNULL(`fv_' . $feature . '_' . '\1`.ID)', $sqlWhereString);
                    $sqlWhereString = str_replace('&', ' AND ', $sqlWhereString);
                    $sqlWhereString = str_replace('|', ' OR ', $sqlWhereString);
                }

                $search->where("(" . $sqlWhereString . ")");
            }
        }

        /**
         * Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */

        $search->joinWithI18n(
            $this->request->getSession()->getLocale(),
            (ConfigQuery::read("default_lang_without_translation", 1)) ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN
        );

        $search->groupBy(ProductTableMap::ID);

        $products = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($products as $product) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow->set("ID", $product->getId())
	            ->set("REF",$product->getRef())
	            ->set("TITLE",$product->getTitle())
	            ->set("CHAPO", $product->getChapo())
	            ->set("DESCRIPTION", $product->getDescription())
	            ->set("POSTSCRIPTUM", $product->getPostscriptum())
	            //->set("PRICE", $product->getPrice())
	            //->set("PROMO_PRICE", $product->getPrice2())
	            //->set("WEIGHT", $product->getWeight())
	            //->set("PROMO", $product->getPromo())
	            //->set("NEW", $product->getNewness())
	            ->set("POSITION", $product->getPosition())
			;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

}
