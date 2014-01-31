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
    protected $contentId;
    protected $contentPosition;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection
            ->addArgument(Argument::createIntTypeArgument('product'))
            ->addArgument(Argument::createIntTypeArgument('category'))
            ->addArgument(Argument::createIntTypeArgument('exclude_product'))
            ->addArgument(Argument::createIntTypeArgument('exclude_category'))
            ;

        $argumentCollection->get('order')->default = "associated_content";

        $argumentCollection->get('order')->type->getKey(0)->addValue('associated_content');
        $argumentCollection->get('order')->type->getKey(0)->addValue('associated_content_reverse');

        return $argumentCollection;
    }

    public function buildModelCriteria()
    {
        $product = $this->getProduct();
        $category = $this->getCategory();

        if ($product === null && $category === null) {
            throw new \InvalidArgumentException('You have to provide either `product` or `category` argument in associated_content loop');
        }

        if ($product !== null) {
            $search = ProductAssociatedContentQuery::create();

            $search->filterByProductId($product, Criteria::EQUAL);
        } elseif ($category !== null) {
            $search = CategoryAssociatedContentQuery::create();

            $search->filterByCategoryId($category, Criteria::EQUAL);
        }

        $exclude_product = $this->getExcludeProduct();

        // If we have to filter by product, find all products assigned to this product, and filter by found IDs
        if (null !== $exclude_product) {
            // Exclude all contents related to the given product
            $search->filterById(
                    ProductAssociatedContentQuery::create()->filterByProductId($exclude_product)->select('product_id')->find(),
                    Criteria::NOT_IN
            );
        }

        $exclude_category = $this->getExcludeCategory();

        // If we have to filter by category, find all contents assigned to this category, and filter by found IDs
        if (null !== $exclude_category) {
            // Exclure tous les attribut qui sont attachés aux templates indiqués
            $search->filterById(
                    CategoryAssociatedContentQuery::create()->filterByProductId($exclude_category)->select('category_id')->find(),
                    Criteria::NOT_IN
            );
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

        $this->contentPosition = $this->contentId = array();

        foreach ($associatedContents as $associatedContent) {

            $associatedContentId = $associatedContent->getContentId();

            array_push($associatedContentIdList, $associatedContentId);
            $this->contentPosition[$associatedContentId] = $associatedContent->getPosition();
            $this->contentId[$associatedContentId] = $associatedContent->getId();
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue( implode(',', $associatedContentIdList) );
        } else {
            $this->args->get('id')->setValue( implode(',', array_intersect($receivedIdList, $associatedContentIdList)) );
        }

        return parent::buildModelCriteria();
    }

    public function parseResults(LoopResult $results)
    {
        $results = parent::parseResults($results);

        foreach ($results as $loopResultRow) {

            $relatedContentId = $loopResultRow->get('ID');

            $loopResultRow
                ->set("ID"        , $this->contentId[$relatedContentId])
                ->set("CONTENT_ID", $relatedContentId)
                ->set("POSITION"  , $this->contentPosition[$relatedContentId])

            ;
        }

        return $results;
    }
}
