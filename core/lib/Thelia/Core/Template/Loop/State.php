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
use Thelia\Model\StateQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Country loop.
 *
 * Class Country
 *
 * @author Julien Chanséaume <julien@thelia.net>
 *
 * @method int[]       getId()
 * @method int[]       getCountry()
 * @method int[]       getExclude()
 * @method bool|string getVisible()
 * @method string[]    getOrder()
 */
class State extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $countable = true;

    protected $timestampable = false;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('country'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id',
                            'id_reverse',
                            'alpha',
                            'alpha_reverse',
                            'visible',
                            'visible_reverse',
                            'random',
                        ]
                    )
                ),
                'id'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = StateQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE']);

        $id = $this->getId();
        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $country = $this->getCountry();
        if (null !== $country) {
            $search->filterByCountryId($country, Criteria::IN);
        }

        $exclude = $this->getExclude();
        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();
        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
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
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible_reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\State $state */
        foreach ($loopResult->getResultDataCollection() as $state) {
            $loopResultRow = new LoopResultRow($state);
            $loopResultRow
                ->set('ID', $state->getId())
                ->set('COUNTRY', $state->getCountryId())
                ->set('VISIBLE', $state->getVisible())
                ->set('IS_TRANSLATED', $state->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $state->getVirtualColumn('i18n_TITLE'))
                ->set('ISOCODE', $state->getIsocode())
            ;

            $this->addOutputFields($loopResultRow, $state);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
