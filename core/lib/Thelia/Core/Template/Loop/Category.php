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



use Thelia\Log\Tlog;
use Thelia\Tpex\Element\Loop\BaseLoop;
use Thelia\Model\CategoryQuery;

class Category extends BaseLoop {

    public $id;
    public $parent;
    public $current;
    public $not_empty;
    public $visible;
    public $link;
    public $order;
    public $random;
    public $exclude;
    public $start;
    public $offset;

    public function defineArgs()
    {
        return array(
            "id" => "optional",
            "parent" => "optional",
            "current" => "optional",
            "not_empty" => 0,
            "visible" => 1,
            "link" => "optional",
            "order" => "optional",
            "random" => 0,
            "exclude" => "optional",
            "limit" => 10,
            "offset" => 0,
        );
    }

    /**
     *
     *
     * @param \Thelia\Tpex\Element\Loop\text $text
     * @return mixed|string
     */
    public function exec($text)
    {
        $search = CategoryQuery::create();

        if (!is_null($this->id)) {
            $search->filterById($this->id);
        }

        if(!is_null($this->parent)) {
            $search->filterByParent($this->parent);
        }

        if($this->current == 1) {
            $search->filterById($this->request->get("category_id"));
        } else if ($this->current == 0) {
            $search->filterById($this->request->get("category_id"), \Criteria::NOT_IN);
        }

        if (!is_null($this->exclude)) {
            $search->filterById(explode(",", $this->exclude), \Criteria::NOT_IN);
        }

        if (!is_null($this->link)) {
            $search->filterByLink($this->link);
        }

        if($this->limit > -1) {
            $search->limit($this->limit);
        }
        $search->filterByVisible($this->visible);
        $search->offset($this->offset);


        switch($this->order) {
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

        if($this->random == 1) {
            $search->clearOrderByColumns();
            $search->addAscendingOrderByColumn('RAND()');
        }

        /**
         * \Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */
        $search->joinWithI18n($this->request->getSession()->get('locale', 'en_US'), \Criteria::INNER_JOIN);

        $categories = $search->find();

        $res = "";

        foreach ($categories as $category) {

            if ($this->not_empty && $category->countAllProducts() == 0) continue;

            $temp = str_replace("#TITLE", $category->getTitle(), $text);
            $temp = str_replace("#CHAPO", $category->getChapo(), $temp);
            $temp = str_replace("#DESCRIPTION", $category->getDescription(), $temp);
            $temp = str_replace("#POSTSCRIPTUM", $category->getPostscriptum(), $temp);
            $temp = str_replace("#PARENT", $category->getParent(), $temp);
            $temp = str_replace("#ID", $category->getId(), $temp);
            $temp = str_replace("#URL", $category->getUrl(), $temp);
            $temp = str_replace("#LINK", $category->getLink(), $temp);
            $temp = str_replace("#NB_CHILD", $category->countChild(), $temp);
            $res .= $temp;
        }

        return $res;
    }

}