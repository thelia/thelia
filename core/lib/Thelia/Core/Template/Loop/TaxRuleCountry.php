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
use Thelia\Model\TaxRuleCountry as TaxRuleCountryModel;

/**
 *
 * TaxRuleCountry loop
 *
 *
 * Class TaxRuleCountry
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getCountry()
 * @method int getTaxRule()
 * @method string getAsk()
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
        $taxRule = $this->getTaxRule();

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

                //$search->having('COUNT(*)=?', $this->taxCountForOriginCountry, \PDO::PARAM_INT);

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
        /** @var TaxRuleCountryModel $taxRuleCountry */
        foreach ($loopResult->getResultDataCollection() as $taxRuleCountry) {
            $loopResultRow = new LoopResultRow($taxRuleCountry);

            if ($this->getAsk() === 'countries') {
                if ($this->taxCountForOriginCountry > 0) {
                    $loopResultRow
                        ->set("COUNTRY", $taxRuleCountry->getCountryId())
                        ->set("COUNTRY_TITLE", $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_TITLE'))
                        ->set("COUNTRY_CHAPO", $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_CHAPO'))
                        ->set("COUNTRY_DESCRIPTION", $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                        ->set("COUNTRY_POSTSCRIPTUM", $taxRuleCountry->getVirtualColumn(CountryTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'));
                } else {
                    $loopResultRow
                        ->set("COUNTRY", $taxRuleCountry->getId())
                        ->set("COUNTRY_TITLE", $taxRuleCountry->getVirtualColumn('i18n_TITLE'))
                        ->set("COUNTRY_CHAPO", $taxRuleCountry->getVirtualColumn('i18n_CHAPO'))
                        ->set("COUNTRY_DESCRIPTION", $taxRuleCountry->getVirtualColumn('i18n_DESCRIPTION'))
                        ->set("COUNTRY_POSTSCRIPTUM", $taxRuleCountry->getVirtualColumn('i18n_POSTSCRIPTUM'));
                }
            } elseif ($this->getAsk() === 'taxes') {
                $loopResultRow
                    ->set("TAX_RULE", $taxRuleCountry->getTaxRuleId())
                    ->set("COUNTRY", $taxRuleCountry->getCountryId())
                    ->set("TAX", $taxRuleCountry->getTaxId())
                    ->set("POSITION", $taxRuleCountry->getPosition())
                    ->set("TAX_TITLE", $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME . '_i18n_TITLE'))
                    ->set("TAX_DESCRIPTION", $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ;
            }
            $this->addOutputFields($loopResultRow, $taxRuleCountry);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
