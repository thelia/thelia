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
use Thelia\Tools\URL;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class ImportExportType
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ImportExportType extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $type) {
            $loopResultRow = new LoopResultRow($type);

            $url = URL::getInstance()->absoluteUrl(
                $this->getBaseUrl() . DS . $type->getId()
            );

            $loopResultRow
                ->set("ID", $type->getId())
                ->set("TITLE", $type->getTitle())
                ->set("DESCRIPTION", $type->getDescription())
                ->set("URL", $type->isImport() ? $url : null)
                ->set("POSITION", $type->getPosition())
                ->set("CATEGORY_ID", $type->getImportExportCategoryId())
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
        $query = $this->getQueryModel();

        if (null !== $ids = $this->getId()) {
            $query->filterById($ids);
        }

        if (null !== $categories = $this->getCategory()) {
            $query->filterBy($this->getCategoryName(), $categories, Criteria::IN);
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
            Argument::createIntListTypeArgument('category'),
            new Argument(
                "order",
                new TypeCollection(
                    new EnumListType(["id", "id_reverse", "alpha", "alpha_reverse", "manual", "manual_reverse"])
                ),
                "manual"
            )
        );
    }

    abstract protected function getBaseUrl();

    abstract protected function getQueryModel();

    abstract protected function getCategoryName();
} 