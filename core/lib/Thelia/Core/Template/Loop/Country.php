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

use Thelia\Model\CountryQuery;
use Thelia\Model\ConfigQuery;

/**
 *
 * Country loop
 *
 *
 * Class Country
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Country extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area'),
            Argument::createBooleanTypeArgument('with_area'),
            Argument::createIntListTypeArgument('exclude'),
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
        $search = CountryQuery::create();

        /* manage translations */
        ModelCriteriaTools::getI18n($search, ConfigQuery::read("default_lang_without_translation", 1), $this->request->getSession()->getLocale());

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $area = $this->getArea();

        if (null !== $area) {
            $search->filterByAreaId($area, Criteria::IN);
        }

        $withArea = $this->getWith_area();

        if (true === $withArea) {
            $search->filterByAreaId(null, Criteria::ISNOTNULL);
        } elseif (false == $withArea) {
            $search->filterByAreaId(null, Criteria::ISNULL);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $search->addAscendingOrderByColumn('i18n_TITLE');

        /* perform search */
        $countries = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($countries as $country) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $country->getId())
                ->set("TITLE",$country->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $country->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $country->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $country->getVirtualColumn('i18n_POSTSCRIPTUM'));
            $loopResultRow->set("ISOCODE", $country->getIsocode());
            $loopResultRow->set("ISOALPHA2", $country->getIsoalpha2());
            $loopResultRow->set("ISOALPHA3", $country->getIsoalpha3());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
