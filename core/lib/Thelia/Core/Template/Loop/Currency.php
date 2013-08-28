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

use Thelia\Model\CurrencyQuery;
use Thelia\Model\ConfigQuery;

/**
 *
 * Currency loop
 *
 *
 * Class Currency
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Currency extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanTypeArgument('default_only', false),
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
        $search = CurrencyQuery::create();

        $backendContext = $this->getBackend_context();

        $lang = $this->getLang();

        /* manage translations */
        ModelCriteriaTools::getI18n($backendContext, $lang, $search, ConfigQuery::read("default_lang_without_translation", 1), $this->request->getSession()->getLocale(), array('NAME'));

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $default_only = $this->getDefaultOnly();

        if ($default_only === true) {
            $search->filterByByDefault(true);
        }

        $search->orderByPosition();

        /* perform search */
        $currencies = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($currencies as $currency) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $currency->getId())
                ->set("IS_TRANSLATED",$currency->getVirtualColumn('IS_TRANSLATED'))
                ->set("NAME",$currency->getVirtualColumn('i18n_NAME'))
                ->set("ISOCODE", $currency->getCode())
                ->set("SYMBOL", $currency->getSymbol())
                ->set("RATE", $currency->getRate())
                ->set("IS_DEFAULT", $currency->getByDefault());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
