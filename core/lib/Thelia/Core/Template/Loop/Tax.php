<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Tax as TaxModel;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Tax loop.
 *
 * Class Tax
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getExclude()
 * @method int[]    getTaxRule()
 * @method int[]    getExcludeTaxRule()
 * @method int      getCountry()
 * @method string[] getOrder()
 */
class Tax extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
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
                    new EnumListType(['id', 'id_reverse', 'alpha', 'alpha_reverse']),
                ),
                'alpha',
            ),
        );
    }

    public function buildModelCriteria(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        $search = TaxQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'DESCRIPTION']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $country = $this->getCountry();

        $taxRule = $this->getTaxRule();

        if (null !== $taxRule && null !== $country) {
            $search->filterByTaxRuleCountry(
                TaxRuleCountryQuery::create()
                    ->filterByCountryId($country, Criteria::EQUAL)
                    ->filterByTaxRuleId($taxRule, Criteria::IN)
                    ->find(),
                Criteria::IN,
            );
        }

        $excludeTaxRule = $this->getExcludeTaxRule();

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

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var TaxModel $tax */
        foreach ($loopResult->getResultDataCollection() as $tax) {
            $loopResultRow = new LoopResultRow($tax);

            $loopResultRow
                ->set('ID', $tax->getId())
                ->set('TYPE', $tax->getType())
                ->set('ESCAPED_TYPE', TaxModel::escapeTypeName($tax->getType()))
                ->set('REQUIREMENTS', $tax->getRequirements())
                ->set('IS_TRANSLATED', $tax->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $tax->getVirtualColumn('i18n_TITLE'))
                ->set('DESCRIPTION', $tax->getVirtualColumn('i18n_DESCRIPTION'));
            $this->addOutputFields($loopResultRow, $tax);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
