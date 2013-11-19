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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\CountryQuery;
use Thelia\Model\Map\CountryTableMap;
use Thelia\Model\Map\TaxRuleCountryTableMap;
use Thelia\Model\Map\TaxTableMap;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Model\TaxRuleCountryQuery;

/**
 *
 * TaxRuleCountry loop
 *
 *
 * Class TaxRuleCountry
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxRuleCountry extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $taxCountForOriginCountry;

    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('country', null, true),
            new Argument(
                'ask',
                new TypeCollection(
                    new Type\EnumType(array('taxes', 'countries'))
                ),
                'taxes'
            ),
            Argument::createIntTypeArgument('tax_rule', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = TaxRuleCountryQuery::create();

        $ask = $this->getAsk();

        $country = $this->getCountry();
        $taxRule = $this->getTax_rule();

        if ($ask === 'countries') {
            $this->taxCountForOriginCountry = TaxRuleCountryQuery::create()->filterByCountryId($country)->count();

            if ($this->taxCountForOriginCountry > 0) {
                $search->groupByCountryId();

                $originalCountryJoin = new Join();
                $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'TAX_RULE_ID', null, TaxRuleCountryTableMap::TABLE_NAME, 'TAX_RULE_ID', 'origin');
                $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'TAX_ID', null, TaxRuleCountryTableMap::TABLE_NAME, 'TAX_ID', 'origin');
                $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'POSITION', null, TaxRuleCountryTableMap::TABLE_NAME, 'POSITION', 'origin');
                $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'COUNTRY_ID', null, TaxRuleCountryTableMap::TABLE_NAME, 'COUNTRY_ID', 'origin', Criteria::NOT_EQUAL);
                $originalCountryJoin->setJoinType(Criteria::LEFT_JOIN);

                $search->addJoinObject($originalCountryJoin, 's_to_o');
                $search->where('`origin`.`COUNTRY_ID`' . Criteria::EQUAL . '?', $country, \PDO::PARAM_INT);

                $search->having('COUNT(*)=?', $this->taxCountForOriginCountry, \PDO::PARAM_INT);

                $search->filterByTaxRuleId($taxRule);

                /* manage tax translation */
                $this->configureI18nProcessing(
                    $search,
                    array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
                    CountryTableMap::TABLE_NAME,
                    'COUNTRY_ID'
                );

                $search->addAscendingOrderByColumn('`' . CountryTableMap::TABLE_NAME . '_i18n_TITLE`');
            } else {
                $search = CountryQuery::create()
                    ->joinTaxRuleCountry('trc', Criteria::LEFT_JOIN);

                /* manage tax translation */
                $this->configureI18nProcessing(
                    $search
                );

                $search->where('ISNULL(`trc`.`COUNTRY_ID`)');

                $search->addAscendingOrderByColumn('i18n_TITLE');
            }
        } elseif ($ask === 'taxes') {
            $search->filterByCountryId($country);

            /* manage tax translation */
            $this->configureI18nProcessing(
                $search,
                array('TITLE', 'DESCRIPTION'),
                TaxTableMap::TABLE_NAME,
                'TAX_ID'
            );

            $search->filterByTaxRuleId($taxRule);
            $search->orderByPosition(Criteria::ASC);
        }

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $taxRuleCountry) {
            $loopResultRow = new LoopResultRow($taxRuleCountry);

            if ($this->getAsk() === 'countries') {
                if ($this->taxCountForOriginCountry > 0) {
                    $loopResultRow
                        ->set("COUNTRY"             , $taxRuleCountry->getCountryId())
                        ->set("COUNTRY_TITLE"               , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_TITLE'))
                        ->set("COUNTRY_CHAPO"               , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_CHAPO'))
                        ->set("COUNTRY_DESCRIPTION"         , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                        ->set("COUNTRY_POSTSCRIPTUM"         , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'));
                } else {
                    $loopResultRow
                        ->set("COUNTRY"             , $taxRuleCountry->getId())
                        ->set("COUNTRY_TITLE"               , $taxRuleCountry->getVirtualColumn('i18n_TITLE'))
                        ->set("COUNTRY_CHAPO"               , $taxRuleCountry->getVirtualColumn('i18n_CHAPO'))
                        ->set("COUNTRY_DESCRIPTION"         , $taxRuleCountry->getVirtualColumn('i18n_DESCRIPTION'))
                        ->set("COUNTRY_POSTSCRIPTUM"         , $taxRuleCountry->getVirtualColumn('i18n_POSTSCRIPTUM'));
                }
            } elseif ($this->getAsk() === 'taxes') {
                $loopResultRow
                    ->set("TAX_RULE"            , $taxRuleCountry->getTaxRuleId())
                    ->set("COUNTRY"             , $taxRuleCountry->getCountryId())
                    ->set("TAX"                 , $taxRuleCountry->getTaxId())
                    ->set("POSITION"            , $taxRuleCountry->getPosition())
                    ->set("TAX_TITLE"               , $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME . '_i18n_TITLE'))
                    ->set("TAX_DESCRIPTION"         , $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ;
            }

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
