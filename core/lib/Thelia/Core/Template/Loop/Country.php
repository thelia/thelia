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
use Thelia\Log\Tlog;

use Thelia\Model\CountryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

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
            Argument::createIntTypeArgument('limit', 500), // overwrite orginal param to increase the limit
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area'),
            Argument::createBooleanTypeArgument('with_area'),
            Argument::createIntListTypeArgument('exclude')
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

        /**
         * Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */

        $search->joinWithI18n(
            $this->request->getSession()->getLocale(),
            (ConfigQuery::read("default_lang_without_translation", 1)) ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN
        );

        $search->addAscendingOrderByColumn(\Thelia\Model\Map\CountryI18nTableMap::TITLE);

        $countries = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($countries as $country) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $country->getId());
            $loopResultRow->set("AREA", $country->getAreaId());
            $loopResultRow->set("TITLE", $country->getTitle());
            $loopResultRow->set("CHAPO", $country->getChapo());
            $loopResultRow->set("DESCRIPTION", $country->getDescription());
            $loopResultRow->set("POSTSCRIPTUM", $country->getPostscriptum());
            $loopResultRow->set("ISOCODE", $country->getIsocode());
            $loopResultRow->set("ISOALPHA2", $country->getIsoalpha2());
            $loopResultRow->set("ISOALPHA3", $country->getIsoalpha3());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}