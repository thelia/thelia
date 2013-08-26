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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Tools\ModelCriteriaTools;

use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\ConfigQuery;

/**
 *
 * Title loop
 *
 *
 * Class Title
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Title extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('lang')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = CustomerTitleQuery::create();

        /* manage translations */
        ModelCriteriaTools::getI18n($search, ConfigQuery::read("default_lang_without_translation", 1), $this->request->getSession()->getLocale(), array('SHORT', 'LONG'));

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $search->orderByPosition();

        /* perform search */
        $titles = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($titles as $title) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $title->getId())
                ->set("DEFAULT", $title->getByDefault())
                ->set("SHORT", $title->getVirtualColumn('i18n_SHORT'))
                ->set("LONG", $title->getVirtualColumn('i18n_LONG'));

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
