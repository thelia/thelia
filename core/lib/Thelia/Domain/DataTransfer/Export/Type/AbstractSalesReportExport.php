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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;
use Thelia\Domain\DataTransfer\Export\MailableReportInterface;
use Thelia\Domain\DataTransfer\Export\ReportSummaryLine;
use Thelia\Domain\DataTransfer\Export\SaleUnitScopedExportInterface;
use Thelia\Domain\Report\Sales\SalesReportCalculator;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Domain\Report\Sales\StatusScope;
use Thelia\Model\Currency;
use Thelia\Model\OrderQuery;

/**
 * What the three sales exports share: the period of the job, the paid orders, and the
 * calculation the back-office screen reads, so that a file holds the very rows of the screen.
 *
 * Without a start the period opens on the first order of the shop (today when there is none),
 * without an end it runs up to today; a start after the end is an empty export, not a file. The
 * orders are narrowed to the sale units of the administrator who runs the export, when it is
 * restricted to some.
 */
abstract class AbstractSalesReportExport extends ArrayAbstractExport implements MailableReportInterface, SaleUnitScopedExportInterface
{
    public const USE_RANGE_DATE = true;

    private const int PERCENT_PRECISION = 2;

    /** @var list<int>|null */
    private ?array $saleUnitIds = null;

    public function restrictToSaleUnits(?array $saleUnitIds): void
    {
        $this->saleUnitIds = $saleUnitIds;
    }

    protected function calculator(): SalesReportCalculator
    {
        return new SalesReportCalculator();
    }

    protected function criteria(): SalesReportCriteria
    {
        $from = $this->bound('start') ?? $this->firstOrderDate();
        $to = $this->bound('end') ?? new \DateTimeImmutable('today 23:59:59');

        if ($from > $to) {
            throw self::noData();
        }

        return new SalesReportCriteria($from, $to, StatusScope::Paid, $this->saleUnitIds);
    }

    protected static function noData(): DataTransferNoDataFoundException
    {
        return new DataTransferNoDataFoundException(Translator::getInstance()->trans('No data found for your export.'));
    }

    protected function locale(): string
    {
        return $this->language->getLocale();
    }

    protected function label(string $key): string
    {
        return Translator::getInstance()->trans($key, [], 'core', $this->locale());
    }

    /**
     * An amount as the file states it: two decimals, never a negative zero.
     */
    protected static function amount(float $amount): float
    {
        return round($amount, 2) + 0.0;
    }

    /**
     * The change of a figure against the previous period, in percent; null when that period sold nothing.
     */
    protected static function variation(float $current, float $previous): ?float
    {
        return 0.0 === $previous ? null : self::amount(($current - $previous) / $previous * 100);
    }

    protected static function percent(float $share): float
    {
        return self::amount($share * 100);
    }

    protected function money(float $amount): string
    {
        return (new FormatService(new RequestStack()))->money($amount, Currency::getDefaultCurrency()->getId(), $this->locale());
    }

    /**
     * The key lines of every sales report: the revenue of the period, and its change against
     * the previous period when that one sold something.
     *
     * @return list<ReportSummaryLine>
     */
    protected function revenueLines(SalesReportCriteria $criteria): array
    {
        $current = $this->calculator()->reconciliation($criteria);
        $previous = $this->calculator()->reconciliation($criteria->previous());

        $lines = [new ReportSummaryLine($this->label('Revenue incl. tax'), $this->money($current->revenue))];

        $variation = self::variation($current->revenue, $previous->revenue);

        if (null !== $variation) {
            $lines[] = new ReportSummaryLine(
                $this->label('Change from the previous period'),
                ($variation > 0 ? '+' : '').number_format($variation, self::PERCENT_PRECISION, (string) $this->language->getDecimalSeparator(), '').' %',
            );
        }

        return $lines;
    }

    private function firstOrderDate(): \DateTimeImmutable
    {
        $first = OrderQuery::create()->orderByCreatedAt()->findOne()?->getCreatedAt();

        return $first instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($first) : new \DateTimeImmutable('today');
    }

    private function bound(string $bound): ?\DateTimeImmutable
    {
        $date = $this->rangeDate[$bound] ?? null;

        return $date instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($date) : null;
    }
}
