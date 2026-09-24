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

namespace Thelia\Domain\DataTransfer\Export\Type;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;
use Thelia\Domain\Report\ConversionFunnel\ConversionFunnelCalculator;
use Thelia\Domain\Report\ConversionFunnel\FunnelStep;

/**
 * The conversion funnel, one row per calendar day of the period. Days without any cart
 * are kept with zero counts, so a quiet period still yields a file. No rate per day: the
 * orders of a day come from carts created on other days, so a daily ratio would mislead.
 */
class ConversionFunnelExport extends ArrayAbstractExport
{
    public const FILE_NAME = 'conversion_funnel';
    public const USE_RANGE_DATE = true;

    private const string DEFAULT_PERIOD_START = 'first day of -12 months';

    /** @var array<string, string> */
    protected array $orderAndAliases = [
        'day' => 'date',
        FunnelStep::CARTS_CREATED => 'carts_created',
        FunnelStep::CARTS_WITH_ITEMS => 'carts_with_items',
        FunnelStep::CARTS_WITH_DELIVERY => 'carts_with_delivery_module',
        FunnelStep::CARTS_WITH_PAYMENT => 'carts_with_payment_module',
        FunnelStep::ORDERS_CREATED => 'orders_created',
        FunnelStep::ORDERS_PAID => 'orders_paid',
    ];

    /**
     * @return list<array<string, string|int|float|null>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        [$from, $to] = $this->period();

        if ($from > $to) {
            throw new DataTransferNoDataFoundException(Translator::getInstance()->trans('No data found for your export.'));
        }

        $rows = [];

        foreach ((new ConversionFunnelCalculator())->daily($from, $to) as $day) {
            $rows[] = [
                'day' => $day->day->format('Y-m-d'),
                FunnelStep::CARTS_CREATED => $day->cartsCreated,
                FunnelStep::CARTS_WITH_ITEMS => $day->cartsWithItems,
                FunnelStep::CARTS_WITH_DELIVERY => $day->cartsWithDelivery,
                FunnelStep::CARTS_WITH_PAYMENT => $day->cartsWithPayment,
                FunnelStep::ORDERS_CREATED => $day->ordersCreated,
                FunnelStep::ORDERS_PAID => $day->ordersPaid,
            ];
        }

        return $rows;
    }

    /**
     * Without a range, the twelve months before the current one and the current month up to
     * today. A single bound is honoured on its own: an end alone reaches back twelve months
     * from its own month, a start alone runs up to today.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function period(): array
    {
        $start = $this->bound('start');
        $end = $this->bound('end') ?? new \DateTimeImmutable('today 23:59:59');

        return [
            $start ?? $end->modify(self::DEFAULT_PERIOD_START)->setTime(0, 0),
            $end,
        ];
    }

    private function bound(string $bound): ?\DateTimeImmutable
    {
        $date = $this->rangeDate[$bound] ?? null;

        return $date instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($date) : null;
    }
}
