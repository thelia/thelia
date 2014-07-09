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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Base\ImportExportCategoryQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class ImportExportCategory
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportExportCategory extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ImportExportCategory $category */
        foreach ($loopResult->getResultDataCollection() as $category)
        {
            $loopResultRow = new LoopResultRow($category);

            $loopResultRow
                ->set("ID", $category->getId())
                ->set("TITLE", $category->getTitle())
                ->set("POSITION", $category->getPosition())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $query = ImportExportCategoryQuery::create();

        if (null !== $ids = $this->getId()) {
            $query->filterById($ids, Criteria::IN);
        }

        if (null !== $orders = $this->getOrder()) {
            foreach ($orders as $order) {
                switch($order) {
                    case "id":
                        $query->orderById();
                        break;
                    case "id_reverse":
                        $query->orderById(Criteria::DESC);
                        break;
                    case "alpha":
                        $query->addAscendingOrderByColumn("i18n_TITLE");
                        break;
                    case "alpha_reverse":
                        $query->addDescendingOrderByColumn("i18n_TITLE");
                        break;
                    case "manual":
                        $query->orderByPosition();
                        break;
                    case "manual_reverse":
                        $query->orderByPosition(Criteria::DESC);
                        break;
                }
            }
        }

        return $query;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            new Argument(
                "order",
                new TypeCollection(
                    new EnumListType(["id", "id_reverse", "alpha", "alpha_reverse", "manual", "manual_reverse"])
                ),
                "manual"
            )
        );
    }
} 