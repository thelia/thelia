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

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\CustomerTitleQuery;

/**
 *
 * Title loop
 *
 *
 * Class Title
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Title extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id')
        );
    }

    public function buildModelCriteria()
    {
        $search = CustomerTitleQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, array('SHORT', 'LONG'));

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $search->orderByPosition();

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $title) {
            $loopResultRow = new LoopResultRow($title);
            $loopResultRow->set("ID", $title->getId())
                ->set("IS_TRANSLATED",$title->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$this->locale)
                ->set("DEFAULT", $title->getByDefault())
                ->set("SHORT", $title->getVirtualColumn('i18n_SHORT'))
                ->set("LONG", $title->getVirtualColumn('i18n_LONG'))
                ->set("POSITION", $title->getPosition());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
