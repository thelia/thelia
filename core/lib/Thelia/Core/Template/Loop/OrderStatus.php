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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderStatus as OrderStatusModel;

/**
 *
 * OrderStatus loop
 *
 *
 * Class OrderStatus
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 * @method string getCode()
 */
class OrderStatus extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyTypeArgument('code')
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderStatusQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $code = $this->getCode();

        if (null !== $code) {
            $search->filterByCode($code, Criteria::EQUAL);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderStatusModel $orderStatus */
        foreach ($loopResult->getResultDataCollection() as $orderStatus) {
            $loopResultRow = new LoopResultRow($orderStatus);
            $loopResultRow->set("ID", $orderStatus->getId())
                ->set("IS_TRANSLATED", $orderStatus->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("CODE", $orderStatus->getCode())
                ->set("TITLE", $orderStatus->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $orderStatus->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $orderStatus->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $orderStatus->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;
            $this->addOutputFields($loopResultRow, $orderStatus);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
