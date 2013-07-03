<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Log\Tlog;

use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductPeer;
use Thelia\Model\ProductQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Category loop, all params available :
 *
 * - id : can be an id (eq : 3) or a "string list" (eg: 3, 4, 5)
 * - parent : categories having this parent id
 * - current : current id is used if you are on a category page
 * - not_empty : if value is 1, category and subcategories must have at least 1 product
 * - visible : default 1, if you want category not visible put 0
 * - order : all value available :
 *      * alpha : sorting by title alphabetical order
 *      * alpha_reverse : sorting by title alphabetical reverse order
 *      * reverse : sorting by position descending
 *      * by default results are sorting by position ascending
 * - random : if 1, random results. Default value is 0
 * - exclude : all category id you want to exclude (as for id, an integer or a "string list" can be used)
 *
 * example :
 *
 * <THELIA_cat type="category" parent="3" limit="4">
 *      #TITLE : #ID
 * </THELIA_cat>
 *
 *
 * Class Product
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Product extends BaseLoop
{
    public $id;
    public $ref;
    public $category;
    public $new;
    public $promo;
    public $min_price;
    public $max_price;
    public $min_stock;
    public $min_weight;
    public $max_weight;
    public $current;
    public $current_category;
    public $depth;
    public $visible;
    public $order;
    public $random;
    public $exclude;

    /**
     * @return ArgumentCollection
     */
    protected function defineArgs()
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
            Argument::createIntTypeArgument('depth'),
            Argument::createBooleanTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumType(array('alpha', 'alpha_reverse', 'reverse', 'min_price', 'max_price', 'category', 'manual', 'manual_reverse', 'ref', 'promo', 'new'))
                )
            ),
            Argument::createBooleanTypeArgument('random', 0),
            Argument::createIntListTypeArgument('exclude')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = ProductQuery::create();

        if (!is_null($this->id)) {
            $search->filterById($this->id, \Criteria::IN);
        }

        if (!is_null($this->ref)) {
            $search->filterByRef($this->ref, \Criteria::IN);
        }

        if (!is_null($this->category)) {
            $categories = CategoryQuery::create()->filterById($this->category, \Criteria::IN)->find();

            if(null !== $this->depth) {
                foreach(CategoryQuery::findAllChild($this->category, $this->depth) as $subCategory) {
                    $categories->prepend($subCategory);
                }
            }

            $search->filterByCategory(
                $categories,
                \Criteria::IN
            );
        }

        if ($this->new === true) {
            $search->filterByNewness(1, \Criteria::EQUAL);
        } else if($this->new === false) {
            $search->filterByNewness(0, \Criteria::EQUAL);
        }

        if ($this->promo === true) {
            $search->filterByPromo(1, \Criteria::EQUAL);
        } else if($this->promo === false) {
            $search->filterByNewness(0, \Criteria::EQUAL);
        }

        if (null != $this->min_stock) {
            $search->filterByQuantity($this->min_stock, \Criteria::GREATER_EQUAL);
        }

        if(null !== $this->min_price) {
            $search->condition('in_promo', ProductPeer::PROMO . \Criteria::EQUAL . '1')
                    ->condition('not_in_promo', ProductPeer::PROMO . \Criteria::NOT_EQUAL . '1')
                    ->condition('min_price2', ProductPeer::PRICE2 . \Criteria::GREATER_EQUAL . '?', $this->min_price)
                    ->condition('min_price', ProductPeer::PRICE . \Criteria::GREATER_EQUAL . '?', $this->min_price)
                    ->combine(array('in_promo', 'min_price2'), \Criteria::LOGICAL_AND, 'in_promo_min_price')
                    ->combine(array('not_in_promo', 'min_price'), \Criteria::LOGICAL_AND, 'not_in_promo_min_price')
                    ->where(array('not_in_promo_min_price', 'in_promo_min_price'), \Criteria::LOGICAL_OR);
        }

        if(null !== $this->max_price) {
            $search->condition('in_promo', ProductPeer::PROMO . \Criteria::EQUAL . '1')
                    ->condition('not_in_promo', ProductPeer::PROMO . \Criteria::NOT_EQUAL . '1')
                    ->condition('max_price2', ProductPeer::PRICE2 . \Criteria::LESS_EQUAL . '?', $this->max_price)
                    ->condition('max_price', ProductPeer::PRICE . \Criteria::LESS_EQUAL . '?', $this->max_price)
                    ->combine(array('in_promo', 'max_price2'), \Criteria::LOGICAL_AND, 'in_promo_max_price')
                    ->combine(array('not_in_promo', 'max_price'), \Criteria::LOGICAL_AND, 'not_in_promo_max_price')
                    ->where(array('not_in_promo_max_price', 'in_promo_max_price'), \Criteria::LOGICAL_OR);
        }

        if(null !== $this->min_weight) {
            $search->filterByWeight($this->min_weight, \Criteria::GREATER_EQUAL);
        }

        if(null !== $this->max_weight) {
            $search->filterByWeight($this->max_weight, \Criteria::LESS_EQUAL);
        }

        if ($this->current === true) {
            $search->filterById($this->request->get("product_id"));
        } elseif($this->current === false) {
            $search->filterById($this->request->get("product_id"), \Criteria::NOT_IN);
        }

        if ($this->current_category === true) {
            $search->filterByCategory(
                CategoryQuery::create()->filterByProduct(
                    ProductCategoryQuery::create()->filterByProductId(
                        $this->request->get("product_id"),
                        \Criteria::EQUAL
                    )->find(),
                    \Criteria::IN
                )->find(),
                \Criteria::IN
            );
        } elseif($this->current_category === false) {
            $search->filterByCategory(
                CategoryQuery::create()->filterByProduct(
                    ProductCategoryQuery::create()->filterByProductId(
                        $this->request->get("product_id"),
                        \Criteria::EQUAL
                    )->find(),
                    \Criteria::IN
                )->find(),
                \Criteria::NOT_IN
            );
        }

        $search->filterByVisible($this->visible);

        switch ($this->order) {
            case "alpha":
                $search->addAscendingOrderByColumn(\Thelia\Model\CategoryI18nPeer::TITLE);
                break;
            case "alpha_reverse":
                $search->addDescendingOrderByColumn(\Thelia\Model\CategoryI18nPeer::TITLE);
                break;
            case "reverse":
                $search->orderByPosition(\Criteria::DESC);
                break;
            /*case "min_price":
                $search->orderByPosition(\Criteria::DESC);
                break;
            case "max_price":
                $search->orderByPosition(\Criteria::DESC);
                break;
            case "category":
                $search->orderByPosition(\Criteria::DESC);
                break;*/
            case "manual":
                $search->addAscendingOrderByColumn(\Thelia\Model\ProductPeer::POSITION);
                break;
            case "manual_reverse":
                $search->addDescendingOrderByColumn(\Thelia\Model\ProductPeer::POSITION);
                break;
            case "ref":
                $search->addAscendingOrderByColumn(\Thelia\Model\ProductPeer::REF);
                break;
            case "promo":
                $search->addDescendingOrderByColumn(\Thelia\Model\ProductPeer::PROMO);
                break;
            case "new":
                $search->addDescendingOrderByColumn(\Thelia\Model\ProductPeer::NEWNESS);
                break;
            default:
                $search->orderByPosition();
                break;
        }

        if ($this->random === true) {
            $search->clearOrderByColumns();
            $search->addAscendingOrderByColumn('RAND()');
        }

        if (!is_null($this->exclude)) {
            $search->filterById($this->exclude, \Criteria::NOT_IN);
        }

        /**
         * \Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */
        $search->joinWithI18n($this->request->getSession()->get('locale', 'en_US'), \Criteria::INNER_JOIN);

        $products = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($products as $product) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $product->getId());
            $loopResultRow->set("REF",$product->getRef());
            $loopResultRow->set("TITLE",$product->getTitle());
            $loopResultRow->set("CHAPO", $product->getChapo());
            $loopResultRow->set("DESCRIPTION", $product->getDescription());
            $loopResultRow->set("POSTSCRIPTUM", $product->getPostscriptum());
            $loopResultRow->set("PRICE", $product->getPrice());
            $loopResultRow->set("PROMO_PRICE", $product->getPrice2());
            $loopResultRow->set("WEIGHT", $product->getWeight());
            $loopResultRow->set("PROMO", $product->getPromo());
            $loopResultRow->set("NEW", $product->getNewness());

            //$loopResultRow->set("URL", $product->getUrl());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

}
