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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\CategoryAssociatedContentQuery;

/**
 *
 * AssociatedContent loop
 *
 *
 * Class AssociatedContent
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getProduct()
 * @method int getCategory()
 * @method int[] getExcludeProduct()
 * @method int[] getExcludeCategory()
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
            ->addArgument(Argument::createIntListTypeArgument('exclude_product'))
            ->addArgument(Argument::createIntListTypeArgument('exclude_category'))
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
            /** @var ProductAssociatedContentQuery $search */
            $search = ProductAssociatedContentQuery::create();

            $search->filterByProductId($product, Criteria::EQUAL);
        } elseif ($category !== null) {
            /** @var CategoryAssociatedContentQuery $search */
            $search = CategoryAssociatedContentQuery::create();

            $search->filterByCategoryId($category, Criteria::EQUAL);
        }

        $excludeProduct = $this->getExcludeProduct();

        // If we have to filter by product, find all products assigned to this product, and filter by found IDs
        if (null !== $excludeProduct) {
            // Exclude all contents related to the given product
            $search->filterById(
                ProductAssociatedContentQuery::create()->filterByProductId($excludeProduct)->select('product_id')->find(),
                Criteria::NOT_IN
            );
        }

        $excludeCategory = $this->getExcludeCategory();

        // If we have to filter by category, find all contents assigned to this category, and filter by found IDs
        if (null !== $excludeCategory) {
            // Exclure tous les attribut qui sont attachés aux templates indiqués
            $search->filterById(
                CategoryAssociatedContentQuery::create()->filterByProductId($excludeCategory)->select('category_id')->find(),
                Criteria::NOT_IN
            );
        }

        $order = $this->getOrder();
        $orderByAssociatedContent = array_search('associated_content', $order);
        $orderByAssociatedContentReverse = array_search('associated_content_reverse', $order);

        if ($orderByAssociatedContent !== false) {
            $search->orderByPosition(Criteria::ASC);
            $order[$orderByAssociatedContent] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
        }
        if ($orderByAssociatedContentReverse !== false) {
            $search->orderByPosition(Criteria::DESC);
            $order[$orderByAssociatedContentReverse] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
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
            $this->args->get('id')->setValue(implode(',', $associatedContentIdList));
        } else {
            $this->args->get('id')->setValue(implode(',', array_intersect($receivedIdList, $associatedContentIdList)));
        }

        return parent::buildModelCriteria();
    }

    public function parseResults(LoopResult $results)
    {
        $results = parent::parseResults($results);

        foreach ($results as $loopResultRow) {
            $relatedContentId = $loopResultRow->get('ID');

            $loopResultRow
                ->set("ID", $this->contentId[$relatedContentId])
                ->set("CONTENT_ID", $relatedContentId)
                ->set("POSITION", $this->contentPosition[$relatedContentId])

            ;
        }

        return $results;
    }
}
