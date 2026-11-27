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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\OrderPaymentPart as OrderPaymentPartModel;
use Thelia\Model\OrderPaymentPartQuery;

/**
 * The parts an order is paid with, in settlement order: each instant means (credit note,
 * customer credit, gift card) and the provider that charges the balance. An order paid with
 * one payment module has no part.
 *
 * @method int      getOrder()
 * @method string[] getState()
 */
class OrderPaymentPart extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order', null, true),
            Argument::createEnumListTypeArgument(
                'state',
                array_map(static fn (OrderPaymentPartState $state): string => $state->value, OrderPaymentPartState::cases()),
                implode(',', [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::SETTLED->value]),
            ),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        return OrderPaymentPartQuery::create()
            ->filterByOrderId($this->getOrder(), Criteria::EQUAL)
            ->filterByState($this->getState(), Criteria::IN)
            ->orderByPosition(Criteria::ASC)
            ->orderById(Criteria::ASC);
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var OrderPaymentPartModel $part */
        foreach ($loopResult->getResultDataCollection() as $part) {
            $module = $part->getPaymentModule();

            $loopResultRow = (new LoopResultRow($part))
                ->set('ID', $part->getId())
                ->set('ORDER_ID', $part->getOrderId())
                ->set('PAYMENT_MODULE_ID', $part->getPaymentModuleId())
                ->set('PAYMENT_MODULE_CODE', $module?->getCode())
                ->set('PAYMENT_MODULE_DELETED_TITLE', $part->getPaymentModuleTitle())
                ->set('AMOUNT', $part->getAmount())
                ->set('STATE', $part->getState())
                ->set('IS_PRIMARY', (bool) $part->getIsPrimary())
                ->set('POSITION', $part->getPosition());
            $this->addOutputFields($loopResultRow, $part);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
