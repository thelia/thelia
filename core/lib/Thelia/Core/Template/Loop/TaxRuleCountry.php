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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Map\TaxTableMap;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;
use Thelia\Model\TaxRuleCountry as TaxRuleCountryModel;

/**
 *
 * TaxRuleCountry loop
 *
 * Two functions provided by this loop depending of the attribute `ask` :
 * - `country` : list all country/state having the same taxes configuration (same tax rule, same taxes, same order)
 * - `taxes` : list taxes for this tax rule and country/state
 *
 * Class TaxRuleCountry
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getCountry()
 * @method int|null getState()
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

    /**
     * @inheritdoc
     */
    public function buildModelCriteria()
    {
        $ask = $this->getAsk();

        if ($ask === 'countries') {
            return null;
        }

        $country = $this->getCountry();
        $state = $this->getState();
        $taxRule = $this->getTaxRule();

        $search = TaxRuleCountryQuery::create();

        $search->filterByCountryId($country);
        $search->filterByStateId($state);
        $search->filterByTaxRuleId($taxRule);

        /* manage tax translation */
        $this->configureI18nProcessing(
            $search,
            array('TITLE', 'DESCRIPTION'),
            TaxTableMap::TABLE_NAME,
            'TAX_ID'
        );

        $search->orderByPosition(Criteria::ASC);

        return $search;
    }

    /**
     * @inheritdoc
     */
    public function parseResults(LoopResult $loopResult)
    {
        if ($this->getAsk() === 'countries') {
            return $loopResult;
        }

        /** @var TaxRuleCountryModel $taxRuleCountry */
        foreach ($loopResult->getResultDataCollection() as $taxRuleCountry) {
            $loopResultRow = new LoopResultRow($taxRuleCountry);
            $loopResultRow
                ->set("TAX_RULE", $taxRuleCountry->getTaxRuleId())
                ->set("COUNTRY", $taxRuleCountry->getCountryId())
                ->set("STATE", $taxRuleCountry->getStateId())
                ->set("TAX", $taxRuleCountry->getTaxId())
                ->set("POSITION", $taxRuleCountry->getPosition())
                ->set("TAX_TITLE", $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME . '_i18n_TITLE'))
                ->set(
                    "TAX_DESCRIPTION",
                    $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME . '_i18n_DESCRIPTION')
                )
            ;

            $this->addOutputFields($loopResultRow, $taxRuleCountry);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
