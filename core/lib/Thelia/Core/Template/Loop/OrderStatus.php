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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderStatus as OrderStatusModel;
use Thelia\Model\OrderStatusQuery;

/**
 * OrderStatus loop.
 *
 * Class OrderStatus
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Gilles Bourgeat <gbourgeat@gmail.com>
 *
 * @method int[]    getId()
 * @method string   getCode()
 * @method string[] getOrder()
 */
class OrderStatus extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyTypeArgument('code'),
            Argument::createEnumListTypeArgument(
                'order',
                [
                    'alpha',
                    'alpha_reverse',
                    'manual',
                    'manual_reverse',
                ],
                'manual',
            ),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = OrderStatusQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $code = $this->getCode()) {
            $search->filterByCode($code, Criteria::EQUAL);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
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
        /** @var OrderStatusModel $orderStatus */
        foreach ($loopResult->getResultDataCollection() as $orderStatus) {
            $loopResultRow = new LoopResultRow($orderStatus);
            $loopResultRow->set('ID', $orderStatus->getId())
                ->set('IS_TRANSLATED', $orderStatus->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('CODE', $orderStatus->getCode())
                ->set('COLOR', $orderStatus->getColor())
                ->set('POSITION', $orderStatus->getPosition())
                ->set('PROTECTED_STATUS', $orderStatus->getProtectedStatus())
                ->set('TITLE', $orderStatus->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $orderStatus->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $orderStatus->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $orderStatus->getVirtualColumn('i18n_POSTSCRIPTUM'));
            $this->addOutputFields($loopResultRow, $orderStatus);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
