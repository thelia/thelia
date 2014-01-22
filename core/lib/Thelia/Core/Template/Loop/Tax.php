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

use Thelia\Model\Base\TaxRuleCountryQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Model\TaxQuery;

/**
 *
 * Tax loop
 *
 *
 * Class Tax
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Tax extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createIntListTypeArgument('tax_rule'),
            Argument::createIntListTypeArgument('exclude_tax_rule'),
            Argument::createIntTypeArgument('country'),
                new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse'))
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = TaxQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, array('TITLE', 'DESCRIPTION'));

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $country = $this->getCountry();

        $taxRule = $this->getTax_rule();
        if (null !== $taxRule && null !== $country) {
            $search->filterByTaxRuleCountry(
                TaxRuleCountryQuery::create()
                    ->filterByCountryId($country, Criteria::EQUAL)
                    ->filterByTaxRuleId($taxRule, Criteria::IN)
                    ->find(),
                Criteria::IN
            );
        }

        $excludeTaxRule = $this->getExclude_tax_rule();
        if (null !== $excludeTaxRule && null !== $country) {
            $excludedTaxes = TaxRuleCountryQuery::create()
                ->filterByCountryId($country, Criteria::EQUAL)
                ->filterByTaxRuleId($excludeTaxRule, Criteria::IN)
                ->find();
            /*DOES NOT WORK
             * $search->filterByTaxRuleCountry(
                $excludedTaxes,
                Criteria::NOT_IN
            );*/
            foreach ($excludedTaxes as $excludedTax) {
                $search->filterByTaxRuleCountry($excludedTax, Criteria::NOT_EQUAL);
            }
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $search->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
            }
        }

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $tax) {
            $loopResultRow = new LoopResultRow($tax);

            $loopResultRow
                ->set("ID"                      , $tax->getId())
                ->set("TYPE"                    , $tax->getType())
                ->set("ESCAPED_TYPE"            , \Thelia\Model\Tax::escapeTypeName($tax->getType()))
                ->set("REQUIREMENTS"            , $tax->getRequirements())
                ->set("IS_TRANSLATED"           , $tax->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE"                  , $this->locale)
                ->set("TITLE"                   , $tax->getVirtualColumn('i18n_TITLE'))
                ->set("DESCRIPTION"             , $tax->getVirtualColumn('i18n_DESCRIPTION'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
