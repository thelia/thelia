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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Log\Tlog;

use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 *
 * Product loop
 *
 *
 * Class Product
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @todo : manage currency in price filter
 */
class Product extends BaseI18nLoop
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
            Argument::createBooleanTypeArgument('new'),
            Argument::createBooleanTypeArgument('promo'),
            Argument::createFloatTypeArgument('min_price'),
            Argument::createFloatTypeArgument('max_price'),
            Argument::createIntTypeArgument('min_stock'),
            Argument::createFloatTypeArgument('min_weight'),
            Argument::createFloatTypeArgument('max_weight'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('current_category'),
            Argument::createIntTypeArgument('depth', 1),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'min_price', 'max_price', 'manual', 'manual_reverse', 'ref', 'promo', 'new', 'random', 'given_id'))
                ),
                'alpha'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createIntListTypeArgument('exclude_category'),
            new Argument(
                'feature_availability',
                new TypeCollection(
                    new Type\IntToCombinedIntsListType()
                )
            ),
            new Argument(
                'feature_values',
                new TypeCollection(
                    new Type\IntToCombinedStringsListType()
                )
            ),
            /*
             * promo, new, quantity, weight or price may differ depending on the different attributes
             * by default, product loop will look for at least 1 attribute which matches all the loop criteria : attribute_non_strict_match="none"
             * you can also provide a list of non-strict attributes.
             *      ie : attribute_non_strict_match="promo,new"
             *      loop will return the product if he has at least an attribute in promo and at least an attribute as new ; even if it's not the same attribute.
             * you can set all the attributes as non strict : attribute_non_strict_match="*"
             *
             * In order to allow such a process, we will have to make a LEFT JOIN foreach of the following case.
            */
            new Argument(
                'attribute_non_strict_match',
                new TypeCollection(
                    new Type\EnumListType(array('min_stock', 'promo', 'new', 'min_weight', 'max_weight', 'min_price', 'max_price')),
                    new Type\EnumType(array('*', 'none'))
                ),
                'none'
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

        /* manage translations */
        $locale = $this->configureI18nProcessing($search);

        $attributeNonStrictMatch = $this->getAttribute_non_strict_match();
        $isPSELeftJoinList = array();
        $isProductPriceLeftJoinList = array();

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

        $new = $this->getNew();

        if ($new === true) {
            $isPSELeftJoinList[] = 'is_new';
            $search->joinProductSaleElements('is_new', Criteria::LEFT_JOIN)
                ->where('`is_new`.NEWNESS' . Criteria::EQUAL . '1')
                ->where('NOT ISNULL(`is_new`.ID)');
        } else if($new === false) {
            $isPSELeftJoinList[] = 'is_new';
            $search->joinProductSaleElements('is_new', Criteria::LEFT_JOIN)
                ->where('`is_new`.NEWNESS' . Criteria::EQUAL . '0')
                ->where('NOT ISNULL(`is_new`.ID)');
        }

        $promo = $this->getPromo();

        if ($promo === true) {
            $isPSELeftJoinList[] = 'is_promo';
            $search->joinProductSaleElements('is_promo', Criteria::LEFT_JOIN)
                ->where('`is_promo`.PROMO' . Criteria::EQUAL . '1')
                ->where('NOT ISNULL(`is_promo`.ID)');
        } else if($promo === false) {
            $isPSELeftJoinList[] = 'is_promo';
            $search->joinProductSaleElements('is_promo', Criteria::LEFT_JOIN)
                ->where('`is_promo`.PROMO' . Criteria::EQUAL . '0')
                ->where('NOT ISNULL(`is_promo`.ID)');
        }

        $min_stock = $this->getMin_stock();

        if (null != $min_stock) {
            $isPSELeftJoinList[] = 'is_min_stock';
            $search->joinProductSaleElements('is_min_stock', Criteria::LEFT_JOIN)
                ->where('`is_min_stock`.QUANTITY' . Criteria::GREATER_THAN . '?', $min_stock, \PDO::PARAM_INT)
                ->where('NOT ISNULL(`is_min_stock`.ID)');
        }

        $min_weight = $this->getMin_weight();

        if (null != $min_weight) {
            $isPSELeftJoinList[] = 'is_min_weight';
            $search->joinProductSaleElements('is_min_weight', Criteria::LEFT_JOIN)
                ->where('`is_min_weight`.WEIGHT' . Criteria::GREATER_THAN . '?', $min_weight, \PDO::PARAM_STR)
                ->where('NOT ISNULL(`is_min_weight`.ID)');
        }

        $max_weight = $this->getMax_weight();

        if (null != $max_weight) {
            $isPSELeftJoinList[] = 'is_max_weight';
            $search->joinProductSaleElements('is_max_weight', Criteria::LEFT_JOIN)
                ->where('`is_max_weight`.WEIGHT' . Criteria::LESS_THAN . '?', $max_weight, \PDO::PARAM_STR)
                ->where('NOT ISNULL(`is_max_weight`.ID)');
        }

        $min_price = $this->getMin_price();

        if(null !== $min_price) {
            $isPSELeftJoinList[] = 'is_min_price';
            $isProductPriceLeftJoinList['is_min_price'] = 'min_price_data';
            $minPriceJoin = new Join();
            $minPriceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'is_min_price', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'min_price_data');
            $minPriceJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->joinProductSaleElements('is_min_price', Criteria::LEFT_JOIN)
                ->addJoinObject($minPriceJoin)
                ->condition('in_promo', '`is_min_price`.promo'. Criteria::EQUAL .'1')
                ->condition('not_in_promo', '`is_min_price`.promo'. Criteria::NOT_EQUAL .'1')
                ->condition('min_promo_price', '`min_price_data`.promo_price' . Criteria::GREATER_EQUAL . '?', $min_price, \PDO::PARAM_STR)
                ->condition('min_price', '`min_price_data`.price' . Criteria::GREATER_EQUAL . '?', $min_price, \PDO::PARAM_STR)
                ->combine(array('in_promo', 'min_promo_price'), Criteria::LOGICAL_AND, 'in_promo_min_price')
                ->combine(array('not_in_promo', 'min_price'), Criteria::LOGICAL_AND, 'not_in_promo_min_price')
                ->where(array('not_in_promo_min_price', 'in_promo_min_price'), Criteria::LOGICAL_OR);
        }

        $max_price = $this->getMax_price();

        if(null !== $max_price) {
            $isPSELeftJoinList[] = 'is_max_price';
            $isProductPriceLeftJoinList['is_max_price'] = 'max_price_data';
            $minPriceJoin = new Join();
            $minPriceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'is_max_price', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'max_price_data');
            $minPriceJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->joinProductSaleElements('is_max_price', Criteria::LEFT_JOIN)
                ->addJoinObject($minPriceJoin)
                ->condition('in_promo', '`is_max_price`.promo'. Criteria::EQUAL .'1')
                ->condition('not_in_promo', '`is_max_price`.promo'. Criteria::NOT_EQUAL .'1')
                ->condition('min_promo_price', '`max_price_data`.promo_price' . Criteria::LESS_EQUAL . '?', $max_price, \PDO::PARAM_STR)
                ->condition('max_price', '`max_price_data`.price' . Criteria::LESS_EQUAL . '?', $max_price, \PDO::PARAM_STR)
                ->combine(array('in_promo', 'min_promo_price'), Criteria::LOGICAL_AND, 'in_promo_max_price')
                ->combine(array('not_in_promo', 'max_price'), Criteria::LOGICAL_AND, 'not_in_promo_max_price')
                ->where(array('not_in_promo_max_price', 'in_promo_max_price'), Criteria::LOGICAL_OR);
        }

        if( $attributeNonStrictMatch != '*' ) {
            if($attributeNonStrictMatch == 'none') {
                $actuallyUsedAttributeNonStrictMatchList = $isPSELeftJoinList;
            } else {
                $actuallyUsedAttributeNonStrictMatchList = array_values(array_intersect($isPSELeftJoinList, $attributeNonStrictMatch));
            }

            foreach($actuallyUsedAttributeNonStrictMatchList as $key => $actuallyUsedAttributeNonStrictMatch) {
                if($key == 0)
                    continue;
                $search->where('`' . $actuallyUsedAttributeNonStrictMatch . '`.ID=' . '`' . $actuallyUsedAttributeNonStrictMatchList[$key-1] . '`.ID');
            }
        }

        /*
         * for ordering and outputs, the product will be :
         * - new if at least one the criteria matching PSE is new
         * - in promo if at least one the criteria matching PSE is in promo
         */

        if(count($isProductPriceLeftJoinList) == 0) {
            if(count($isPSELeftJoinList) == 0) {
                $joiningTable = "global";
                $isPSELeftJoinList[] = $joiningTable;
                $search->joinProductSaleElements('global', Criteria::LEFT_JOIN);
            } else {
                $joiningTable = $isPSELeftJoinList[0];
            }

            $isProductPriceLeftJoinList[$joiningTable] = 'global_price_data';

            $minPriceJoin = new Join();
            $minPriceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', $joiningTable, ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'global_price_data');
            $minPriceJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->addJoinObject($minPriceJoin);
        }

        /*
         * we need to test all promo field from our previous conditions. Indeed ie:
         * product P0, attributes color : red
         * P0red is in promo and is the only attribute combinaton availability.
         * so the product might be consider as in promo (in outputs and ordering)
         * We got the following loop to display in promo AND new product but we don't care it's the same attribute which is new and in promo :
         * {loop type="product" promo="1" new="1" attribute_non_strict_match="promo,new"} {/loop}
         * our request will so far returns 1 line
         *
         * is_promo.ID | is_promo.PROMO | is_promo.NEWNESS | is_new.ID | is_new.PROMO | is_new.NEWNESS
         *      NULL            NULL              NULL        red_id         1               0
         *
         * So that we can say the product is in global promo only with is_promo.PROMO, we must acknowledge it with (is_promo.PROMO OR is_new.PROMO)
         */
        $booleanMatchedPromoList = array();
        $booleanMatchedNewnessList = array();
        foreach($isPSELeftJoinList as $isPSELeftJoin) {
            $booleanMatchedPromoList[] = '`' . $isPSELeftJoin . '`.PROMO';
            $booleanMatchedNewnessList[] = '`' . $isPSELeftJoin . '`.NEWNESS';
        }
        $booleanMatchedPriceList = array();
        foreach($isProductPriceLeftJoinList as $pSE => $isProductPriceLeftJoin) {
            $booleanMatchedPriceList[] = 'CASE WHEN `' . $pSE . '`.PROMO=1 THEN `' . $isProductPriceLeftJoin . '`.PROMO_PRICE ELSE `' . $isProductPriceLeftJoin . '`.PRICE END';
        }
        $search->withColumn('MAX(' . implode(' OR ', $booleanMatchedPromoList) . ')', 'main_product_is_promo');
        $search->withColumn('MAX(' . implode(' OR ', $booleanMatchedNewnessList) . ')', 'main_product_is_new');
        $search->withColumn('MAX(' . implode(' OR ', $booleanMatchedPriceList) . ')', 'real_highest_price');
        $search->withColumn('MIN(' . implode(' OR ', $booleanMatchedPriceList) . ')', 'real_lowest_price');


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

        if ($visible != BooleanOrBothType::ANY) $search->filterByVisible($visible ? 1 : 0);

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

        $feature_availability = $this->getFeature_availability();

        if(null !== $feature_availability) {
            foreach($feature_availability as $feature => $feature_choice) {
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

        $search->groupBy(ProductTableMap::ID);

        $orders  = $this->getOrder();

        foreach($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "min_price":
                    $search->addAscendingOrderByColumn('real_lowest_price', Criteria::ASC);
                    break;
                case "max_price":
                    $search->addDescendingOrderByColumn('real_lowest_price');
                    break;
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
                case "promo":
                    $search->addDescendingOrderByColumn('main_product_is_promo');
                    break;
                case "new":
                    $search->addDescendingOrderByColumn('main_product_is_new');
                    break;
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

        /* perform search */
        $products = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($products as $product) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow->set("ID", $product->getId())
                ->set("REF",$product->getRef())
                ->set("IS_TRANSLATED",$product->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$locale)
                ->set("TITLE",$product->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $product->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $product->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $product->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("URL", $product->getUrl($locale))
                ->set("BEST_PRICE", $product->getVirtualColumn('real_lowest_price'))
                ->set("IS_PROMO", $product->getVirtualColumn('main_product_is_promo'))
                ->set("IS_NEW", $product->getVirtualColumn('main_product_is_new'))
                ->set("POSITION", $product->getPosition())

                ->set("CREATE_DATE", $product->getCreatedAt())
                ->set("UPDATE_DATE", $product->getUpdatedAt())
                ->set("VERSION", $product->getVersion())
                ->set("VERSION_DATE", $product->getVersionCreatedAt())
                ->set("VERSION_AUTHOR", $product->getVersionCreatedBy())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
