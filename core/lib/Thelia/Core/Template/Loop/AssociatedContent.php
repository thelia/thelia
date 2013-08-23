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

use Thelia\Core\Template\Loop\Content;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\LoopResult;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Type;

/**
 *
 * AssociatedContent loop
 *
 *
 * Class AssociatedContent
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AssociatedContent extends Content
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection->addArgument(Argument::createIntTypeArgument('product'))
            ->addArgument(Argument::createIntTypeArgument('category'));

        $argumentCollection->get('order')->default = "associated_content";

        $argumentCollection->get('order')->type->getKey(0)->addValue('associated_content');
        $argumentCollection->get('order')->type->getKey(0)->addValue('associated_content_reverse');

        return $argumentCollection;
    }

    /**
     * @param $pagination
     *
     * @return LoopResult
     * @throws \InvalidArgumentException
     */
    public function exec(&$pagination)
    {
        //

        $product = $this->getProduct();
        $category = $this->getCategory();

        if($product === null && $category === null) {
            throw new \InvalidArgumentException('You have to provide either `product` or `category` argument in associated_content loop');
        }

        if($product !== null) {
            $search = ProductAssociatedContentQuery::create();

            $search->filterByProductId($product, Criteria::EQUAL);
        } elseif($category !== null) {
            $search = CategoryAssociatedContentQuery::create();

            $search->filterByCategoryId($category, Criteria::EQUAL);
        }

        $order = $this->getOrder();
        $orderByAssociatedContent = array_search('associated_content', $order);
        $orderByAssociatedContentReverse = array_search('associated_content_reverse', $order);

        if ($orderByAssociatedContent !== false) {
            $search->orderByPosition(Criteria::ASC);
            $order[$orderByAssociatedContent] = 'given_id';
            $this->args->get('order')->setValue( implode(',', $order) );
        }
        if ($orderByAssociatedContentReverse !== false) {
            $search->orderByPosition(Criteria::DESC);
            $order[$orderByAssociatedContentReverse] = 'given_id';
            $this->args->get('order')->setValue( implode(',', $order) );
        }

        $associatedContents = $this->search($search);

        $associatedContentIdList = array(0);
        foreach ($associatedContents as $associatedContent) {
            array_push($associatedContentIdList, $associatedContent->getContentId());
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue( implode(',', $associatedContentIdList) );
        } else {
            $this->args->get('id')->setValue( implode(',', array_intersect($receivedIdList, $associatedContentIdList)) );
        }

        return parent::exec($pagination);
    }

}
