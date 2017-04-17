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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\ClassNotFoundException;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Import as ImportModel;
use Thelia\Tools\URL;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class ImportExportType
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getCategory()
 * @method string[] getOrder()
 */
abstract class ImportExportType extends BaseI18nLoop implements PropelSearchLoopInterface
{
    const DEFAULT_ORDER = "manual";

    protected $timestampable = true;

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var ImportModel $type */
        foreach ($loopResult->getResultDataCollection() as $type) {
            $loopResultRow = new LoopResultRow($type);

            $url = URL::getInstance()->absoluteUrl(
                $this->getBaseUrl() . "/" . $type->getId()
            );

            try {
                $loopResultRow
                    ->set("HANDLE_CLASS", $type->getHandleClass())
                    ->set("ID", $type->getId())
                    ->set("TITLE", $type->getVirtualColumn("i18n_TITLE"))
                    ->set("DESCRIPTION", $type->getVirtualColumn("i18n_DESCRIPTION"))
                    ->set("URL", $url)
                    ->set("POSITION", $type->getPosition())
                    ->set("CATEGORY_ID", $type->getByName($this->getCategoryName()))
                ;
            } catch (ClassNotFoundException $e) {
            } catch (\ErrorException $e) {
            }

            $this->addOutputFields($loopResultRow, $type);
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
        /** @var ModelCriteria $query */
        $query = $this->getQueryModel();

        $this->configureI18nProcessing($query, array('TITLE', 'DESCRIPTION'));

        if (null !== $ids = $this->getId()) {
            $query->filterById($ids);
        }

        if (null !== $categories = $this->getCategory()) {
            $query->filterBy($this->getCategoryName(), $categories, Criteria::IN);
        }

        if (null !== $orders = $this->getOrder()) {
            foreach ($orders as $order) {
                switch ($order) {
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
                    new EnumListType(static::getAllowedOrders())
                ),
                static::DEFAULT_ORDER
            )
        );
    }

    public static function getAllowedOrders()
    {
        return ["id", "id_reverse", "alpha", "alpha_reverse", "manual", "manual_reverse"];
    }

    abstract protected function getBaseUrl();

    abstract protected function getQueryModel();

    abstract protected function getCategoryName();
}
