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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Log\Tlog;

use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
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
 * Class Category
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Category extends BaseLoop
{
    public $id;
    public $parent;
    public $current;
    public $not_empty;
    public $visible;
    public $link;
    public $order;
    public $random;
    public $exclude;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('parent'),
            Argument::createIntTypeArgument('current'),
            Argument::createIntTypeArgument('not_empty', 0),
            Argument::createIntTypeArgument('visible', 1),
            Argument::createAnyTypeArgument('link'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumType('alpha', 'alpha_reverse', 'reverse')
                )
            ),
            Argument::createIntTypeArgument('random', 0),
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
        $search = CategoryQuery::create();

        $id = $this->getArgValue('id');

        if (!is_null($id)) {
            $search->filterById(explode(',', $id), Criteria::IN);
        }

        $parent = $this->getArgValue('parent');

        if (!is_null($parent)) {
            $search->filterByParent($parent);
        }

        $current = $this->getArgValue('current');

        if ($current == 1) {
            $search->filterById($this->request->get("category_id"));
        } elseif (null !== $current && $current == 0) {
            $search->filterById($this->request->get("category_id"), Criteria::NOT_IN);
        }

        $exclude = $this->getArgValue('exclude');

        if (!is_null($exclude)) {
            $search->filterById(explode(",", $exclude), Criteria::NOT_IN);
        }

        $link = $this->getArgValue('link');

        if (!is_null($link)) {
            $search->filterByLink($link);
        }

        $search->filterByVisible($this->getArgValue('visible'));

        switch ($this->getArgValue('order')) {
            case "alpha":
                $search->addAscendingOrderByColumn(\Thelia\Model\CategoryI18nPeer::TITLE);
                break;
            case "alpha_reverse":
                $search->addDescendingOrderByColumn(\Thelia\Model\CategoryI18nPeer::TITLE);
                break;
            case "reverse":
                $search->orderByPosition(\Criteria::DESC);
                break;
            default:
                $search->orderByPosition();
                break;
        }

        if ($this->getArgValue('random') == 1) {
            $search->clearOrderByColumns();
            $search->addAscendingOrderByColumn('RAND()');
        }

        /**
         * \Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */

        $search->joinWithI18n(
            $this->request->getSession()->get('locale', 'en_US'),
            (ConfigQuery::read("default_lang_without_translation", 1)) ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN
        );

        $categories = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($categories as $category) {

            if ($this->not_empty && $category->countAllProducts() == 0) continue;

            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("TITLE",$category->getTitle());
            $loopResultRow->set("CHAPO", $category->getChapo());
            $loopResultRow->set("DESCRIPTION", $category->getDescription());
            $loopResultRow->set("POSTSCRIPTUM", $category->getPostscriptum());
            $loopResultRow->set("PARENT", $category->getParent());
            $loopResultRow->set("ID", $category->getId());
            $loopResultRow->set("URL", $category->getUrl());
            $loopResultRow->set("LINK", $category->getLink());
            $loopResultRow->set("NB_CHILD", $category->countChild());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

}
