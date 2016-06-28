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
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\CategoryQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Core\Template\Element\BaseI18nLoop;

/**
 *
 * Category tree loop, to get a category tree from a given category to a given depth.
 *
 * - category is the category id
 * - depth is the maximum depth to go, default unlimited
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method int getCategory()
 * @method int getDepth()
 * @method bool getNeedCountChild()
 * @method bool|string getVisible()
 * @method int[] getExclude()
 * @method string[] getOrder()
 */
class CategoryTree extends BaseI18nLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category', null, true),
            Argument::createIntTypeArgument('depth', PHP_INT_MAX),
            Argument::createBooleanTypeArgument('need_count_child', false),
            Argument::createBooleanOrBothTypeArgument('visible', true, false),
            Argument::createIntListTypeArgument('exclude', array()),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('position', 'position_reverse', 'id', 'id_reverse', 'alpha', 'alpha_reverse'))
                ),
                'position'
            )
        );
    }

    // changement de rubrique
    protected function buildCategoryTree($parent, $visible, $level, $previousLevel, $maxLevel, $exclude, &$resultsList)
    {
        if ($level > $maxLevel) {
            return;
        }

        $search = CategoryQuery::create();
        $this->configureI18nProcessing($search, array('TITLE'));

        $search->filterByParent($parent);

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible);
        }

        if ($exclude != null) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "position":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "position_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case "id":
                    $search->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
            }
        }

        $results = $search->find();

        $needCountChild = $this->getNeedCountChild();

        foreach ($results as $result) {
            $row = array(
                "ID" => $result->getId(),
                "TITLE" => $result->getVirtualColumn('i18n_TITLE'),
                "PARENT" => $result->getParent(),
                "URL" => $this->getReturnUrl() ? $result->getUrl($this->locale) : null,
                "VISIBLE" => $result->getVisible() ? "1" : "0",
                "LEVEL" => $level,
                'PREV_LEVEL' => $previousLevel,
            );

            if ($needCountChild) {
                $row['CHILD_COUNT'] = $result->countChild();
            }

            $resultsList[] = $row;

            $this->buildCategoryTree($result->getId(), $visible, 1 + $level, $level, $maxLevel, $exclude, $resultsList);
        }
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);
            foreach ($result as $output => $outputValue) {
                $loopResultRow->set($output, $outputValue);
            }

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    public function buildArray()
    {
        $id = $this->getCategory();
        $depth = $this->getDepth();
        $visible = $this->getVisible();
        $exclude = $this->getExclude();

        $resultsList = array();

        $this->buildCategoryTree($id, $visible, 0, 0, $depth, $exclude, $resultsList);

        return $resultsList;
    }
}
