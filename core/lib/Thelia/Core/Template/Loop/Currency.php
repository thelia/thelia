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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CurrencyQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Currency loop.
 *
 * Class Currency
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int[]       getExclude()
 * @method bool        getDefaultOnly()
 * @method bool|string getVisible()
 * @method string[]    getOrder()
 */
class Currency extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanTypeArgument('default_only', false),
            Argument::createBooleanOrBothTypeArgument('visible', true),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'name', 'name_reverse',
                            'code', 'code_reverse',
                            'symbol', 'symbol_reverse',
                            'rate', 'rate_reverse',
                            'visible', 'visible_reverse',
                            'is_default', 'is_default_reverse',
                            'manual', 'manual_reverse', ],
                    ),
                ),
                'manual',
            ),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = CurrencyQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['NAME']);

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $exclude = $this->getExclude()) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if (true === $this->getDefaultOnly()) {
            $search->filterByByDefault(true);
        }

        if ('*' !== $visible = $this->getVisible()) {
            $search->filterByVisible($visible);
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
                case 'name':
                    $search->addAscendingOrderByColumn('i18n_NAME');
                    break;
                case 'name_reverse':
                    $search->addDescendingOrderByColumn('i18n_NAME');
                    break;
                case 'code':
                    $search->orderByCode(Criteria::ASC);
                    break;
                case 'code_reverse':
                    $search->orderByCode(Criteria::DESC);
                    break;
                case 'symbol':
                    $search->orderBySymbol(Criteria::ASC);
                    break;
                case 'symbol_reverse':
                    $search->orderBySymbol(Criteria::DESC);
                    break;
                case 'rate':
                    $search->orderByRate(Criteria::ASC);
                    break;
                case 'rate_reverse':
                    $search->orderByRate(Criteria::DESC);
                    break;
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible_reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case 'is_default':
                    $search->orderByByDefault(Criteria::ASC);
                    break;
                case 'is_default_reverse':
                    $search->orderByByDefault(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var CurrencyModel $currency */
        foreach ($loopResult->getResultDataCollection() as $currency) {
            $loopResultRow = new LoopResultRow($currency);
            $loopResultRow
                ->set('ID', $currency->getId())
                ->set('IS_TRANSLATED', $currency->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('NAME', $currency->getVirtualColumn('i18n_NAME'))
                ->set('ISOCODE', $currency->getCode())
                ->set('SYMBOL', $currency->getSymbol())
                ->set('FORMAT', $currency->getFormat())
                ->set('RATE', $currency->getRate())
                ->set('VISIBLE', $currency->getVisible())
                ->set('POSITION', $currency->getPosition())
                ->set('IS_DEFAULT', $currency->getByDefault());
            $this->addOutputFields($loopResultRow, $currency);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
