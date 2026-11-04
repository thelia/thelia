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
use Thelia\Domain\Cart\Service\CartPurgeHorizon;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;
use Thelia\Domain\DataTransfer\Export\MailableReportInterface;
use Thelia\Domain\DataTransfer\Export\ReportSummaryLine;
use Thelia\Domain\Report\ConversionFunnel\ConversionFunnelCalculator;
use Thelia\Domain\Report\ConversionFunnel\FunnelStep;

/**
 * The conversion funnel, one row per calendar day of the period. Days without any cart
 * are kept with zero counts, so a quiet period still yields a file. No rate per day: the
 * orders of a day come from carts created on other days, so a daily ratio would mislead.
 * The period starts on the cart purge horizon at the earliest, as on the back-office
 * report: before it, the orders would be set against carts that no longer exist.
 */
class ConversionFunnelExport extends ArrayAbstractExport implements MailableReportInterface
{
    public const FILE_NAME = 'conversion_funnel';
    public const USE_RANGE_DATE = true;

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
     * The totals of the columns over the period, and the share of the created orders that
     * were paid (left out while no order was created).
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        [$from, $to] = $this->period();

        $cartsCreated = 0;
        $ordersCreated = 0;
        $ordersPaid = 0;

        foreach ((new ConversionFunnelCalculator())->daily($from, $to) as $day) {
            $cartsCreated += $day->cartsCreated;
            $ordersCreated += $day->ordersCreated;
            $ordersPaid += $day->ordersPaid;
        }

        $locale = $this->language->getLocale();
        $translator = Translator::getInstance();

        $lines = [
            new ReportSummaryLine($translator->trans('Carts created', [], 'core', $locale), (string) $cartsCreated),
            new ReportSummaryLine($translator->trans('Orders created', [], 'core', $locale), (string) $ordersCreated),
            new ReportSummaryLine($translator->trans('Orders paid', [], 'core', $locale), (string) $ordersPaid),
        ];

        if ($ordersCreated > 0) {
            $percent = FunnelStep::percent($ordersPaid / $ordersCreated);
            $lines[] = new ReportSummaryLine(
                $translator->trans('Paid orders out of created orders', [], 'core', $locale),
                number_format((float) $percent, FunnelStep::PERCENT_PRECISION, (string) $this->language->getDecimalSeparator(), '').' %',
            );
        }

        return $lines;
    }

    /**
     * Without a start, the period starts on the purge horizon; without an end, it runs up to
     * today. A start before the horizon is moved to the horizon, to the second.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function period(): array
    {
        $horizon = CartPurgeHorizon::fromConfig();
        $start = $this->bound('start') ?? $horizon->earliestSurvivingCartDate();

        return [
            $horizon->boundedStart($start),
            $this->bound('end') ?? new \DateTimeImmutable('today 23:59:59'),
        ];
    }

    private function bound(string $bound): ?\DateTimeImmutable
    {
        $date = $this->rangeDate[$bound] ?? null;

        return $date instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($date) : null;
    }
}
