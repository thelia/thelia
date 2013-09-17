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

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Base\ProductCategoryQuery;
use Thelia\Model\Base\TaxRuleQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * TaxRule loop
 *
 *
 * Class TaxRule
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxRule extends BaseI18nLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse'))
                ),
                'alpha'
            )
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = TaxRuleQuery::create();

        /* manage translations */
        $locale = $this->configureI18nProcessing($search, 'TITLE', 'DESCRIPTION');

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
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

        /* perform search */
        $tax_rules = $this->search($search, $pagination);

        $loopResult = new LoopResult($tax_rules);

        foreach ($tax_rules as $tax_rule) {

            $loopResultRow = new LoopResultRow($loopResult, $tax_rule, $this->versionable, $this->timestampable, $this->countable);

            $loopResultRow
                ->set("ID"            , $tax_rule->getId())
                ->set("IS_TRANSLATED" , $tax_rule->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE"        , $locale)
                ->set("TITLE"         , $tax_rule->getVirtualColumn('i18n_TITLE'))
                ->set("DESCRIPTION"   , $tax_rule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("IS_DEFAULT"    , $tax_rule->getIsDefault() ? '1' : '0')
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}