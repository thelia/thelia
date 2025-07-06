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
use Thelia\Model\TaxRule as TaxRuleModel;
use Thelia\Model\TaxRuleQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * TaxRule loop.
 *
 * Class TaxRule
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getExclude()
 * @method string[] getOrder()
 */
class TaxRule extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['id', 'id_reverse', 'alpha', 'alpha_reverse'])
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = TaxRuleQuery::create();

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
        /** @var TaxRuleModel $taxRule */
        foreach ($loopResult->getResultDataCollection() as $taxRule) {
            $loopResultRow = new LoopResultRow($taxRule);

            $loopResultRow
                ->set('ID', $taxRule->getId())
                ->set('IS_TRANSLATED', $taxRule->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $taxRule->getVirtualColumn('i18n_TITLE'))
                ->set('DESCRIPTION', $taxRule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('IS_DEFAULT', $taxRule->getIsDefault() ? '1' : '0')
            ;
            $this->addOutputFields($loopResultRow, $taxRule);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
