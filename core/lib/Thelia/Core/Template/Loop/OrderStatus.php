<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\OrderStatusQuery;

/**
 *
 * OrderStatus loop
 *
 *
 * Class OrderStatus
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderStatus extends BaseI18nLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = OrderStatusQuery::create();

        /* manage translations */
        $locale = $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        /* perform search */
        $orderStatusList = $this->search($search, $pagination);

        $loopResult = new LoopResult($orderStatusList);

        foreach ($orderStatusList as $orderStatus) {
            $loopResultRow = new LoopResultRow($loopResult, $orderStatus, $this->versionable, $this->timestampable, $this->countable);
            $loopResultRow->set("ID", $orderStatus->getId())
                ->set("IS_TRANSLATED",$orderStatus->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$locale)
                ->set("CODE", $orderStatus->getCode())
                ->set("TITLE", $orderStatus->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $orderStatus->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $orderStatus->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $orderStatus->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
