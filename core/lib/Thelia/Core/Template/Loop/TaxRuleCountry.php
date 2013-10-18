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

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Map\CountryTableMap;
use Thelia\Model\Map\TaxRuleCountryTableMap;
use Thelia\Model\Map\TaxTableMap;
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
class TaxRuleCountry extends BaseI18nLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('country'),
            Argument::createIntListTypeArgument('taxes'),
            Argument::createIntTypeArgument('tax_rule', null, true)
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = TaxRuleCountryQuery::create();

        $country = $this->getCountry();
        $taxes = $this->getTaxes();

        if ((null === $country && null === $taxes)) {
            throw new \InvalidArgumentException('You must provide either `country` or `taxes` parameter in tax-rule-country loop');
        }

        if ((null === $country && null !== $taxes)) {
            throw new \InvalidArgumentException('You must provide `country` parameter with `taxes` parameter in tax-rule-country loop');
        }

        if (null !== $taxes) {
            $search->groupByCountryId();

            $originalCountryJoin = new Join();
            $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'TAX_RULE_ID', null, TaxRuleCountryTableMap::TABLE_NAME, 'TAX_RULE_ID', 'origin');
            $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'TAX_ID', null, TaxRuleCountryTableMap::TABLE_NAME, 'TAX_ID', 'origin');
            $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'POSITION', null, TaxRuleCountryTableMap::TABLE_NAME, 'POSITION', 'origin');
            $originalCountryJoin->addExplicitCondition(TaxRuleCountryTableMap::TABLE_NAME, 'COUNTRY_ID', null, TaxRuleCountryTableMap::TABLE_NAME, 'COUNTRY_ID', 'origin', Criteria::NOT_EQUAL);
            $originalCountryJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->addJoinObject($originalCountryJoin, 's_to_o');
            $search->where('`origin`.`COUNTRY_ID`' . Criteria::EQUAL . '?', $country, \PDO::PARAM_INT);

            $search->having('COUNT(*)=?', count($taxes), \PDO::PARAM_INT);

            /* manage tax translation */
            $this->configureI18nProcessing(
                $search,
                array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
                CountryTableMap::TABLE_NAME,
                'COUNTRY_ID'
            );
        } elseif (null !== $country) {
            $search->filterByCountryId($country);

            /* manage tax translation */
            $this->configureI18nProcessing(
                $search,
                array('TITLE', 'DESCRIPTION'),
                TaxTableMap::TABLE_NAME,
                'TAX_ID'
            );
        }

        $taxRule = $this->getTax_rule();
        $search->filterByTaxRuleId($taxRule);

        $search->orderByPosition(Criteria::ASC);

        /* perform search */
        $taxRuleCountries = $this->search($search, $pagination);

        $loopResult = new LoopResult($taxRuleCountries);

        foreach ($taxRuleCountries as $taxRuleCountry) {

            $loopResultRow = new LoopResultRow($loopResult, $taxRuleCountry, $this->versionable, $this->timestampable, $this->countable);

            if (null !== $taxes) {
                $loopResultRow
                    ->set("TAX_RULE"            , $taxRuleCountry->getTaxRuleId())
                    ->set("COUNTRY"             , $taxRuleCountry->getCountryId())
                    ->set("COUNTRY_TITLE"               , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_TITLE'))
                    ->set("COUNTRY_CHAPO"               , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_CHAPO'))
                    ->set("COUNTRY_DESCRIPTION"         , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                    ->set("COUNTRY_POSTSCRIPTUM"         , $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'))
                ;
            } elseif (null !== $country) {
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
