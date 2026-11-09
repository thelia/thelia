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
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Export\JsonFileAbstractExport;
use Thelia\Domain\DataTransfer\Export\MailableReportInterface;
use Thelia\Domain\DataTransfer\Export\ReportSummaryLine;
use Thelia\Domain\DataTransfer\Export\SaleUnitScopedExportInterface;
use Thelia\Domain\Order\Sql\OrderAmountSql;
use Thelia\Model\Currency;
use Thelia\Model\OrderStatusQuery;

/**
 * Class OrderExport.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class OrderExport extends JsonFileAbstractExport implements MailableReportInterface, SaleUnitScopedExportInterface
{
    public const FILE_NAME = 'order';
    public const USE_RANGE_DATE = true;

    protected array $orderAndAliases = [
        'order_ref' => 'ref',
        'order_created_at' => 'date',
        'customer_ref' => 'customer_ref',
        'order_coupon_code' => 'coupons',
        'order_total_price' => 'total_excluding_taxes',
        'order_total_tax' => 'total_taxes',
        'order_total_taxed_price' => 'total_taxed_price',
        'order_discount' => 'discount',
        'order_postage' => 'shipping_cost',
        'order_total_with_taxes_shipping_discount' => 'total_including_taxes',
        'delivery_module_title' => 'delivery_module',
        'order_delivery_ref' => 'delivery_ref',
        'payment_module_title' => 'payment_module',
        'order_invoice_ref' => 'invoice_ref',
        'order_status_i18n_title' => 'status',
        'delivery_address_customer_title_long' => 'delivery_title',
        'delivery_address_company' => 'delivery_company',
        'delivery_address_firstname' => 'delivery_first_name',
        'delivery_address_lastname' => 'delivery_last_name',
        'delivery_address_address1' => 'delivery_address_1',
        'delivery_address_address2' => 'delivery_address_2',
        'delivery_address_address3' => 'delivery_address_3',
        'delivery_address_zipcode' => 'delivery_zip_code',
        'delivery_address_city' => 'delivery_city',
        'delivery_country_i18n_title' => 'delivery_country',
        'delivery_address_phone' => 'delivery_phone',
        'invoice_address_customer_title_long' => 'invoice_title',
        'invoice_address_company' => 'invoice_company',
        'invoice_address_firstname' => 'invoice_first_name',
        'invoice_address_lastname' => 'invoice_last_name',
        'invoice_address_address1' => 'invoice_address_1',
        'invoice_address_address2' => 'invoice_address_2',
        'invoice_address_address3' => 'invoice_address_3',
        'invoice_address_zipcode' => 'invoice_zip_code',
        'invoice_address_city' => 'invoice_city',
        'invoice_country_i18n_title' => 'invoice_country',
        'invoice_address_phone' => 'invoice_phone',
        'currency_code' => 'currency',
        'order_postage_tax' => 'shipping_tax',
        'order_postage_tax_rule_title' => 'shipping_tax_rule_title',
        'order_product_tax_title' => 'tax',
    ];

    /** @var list<int>|null */
    private ?array $saleUnitIds = null;

    public function restrictToSaleUnits(?array $saleUnitIds): void
    {
        $this->saleUnitIds = null === $saleUnitIds ? null : array_values(array_unique(array_map('intval', $saleUnitIds)));
    }

    protected function getData(): array|string|ModelCriteria
    {
        $stmt = $this->statement($this->rowsSql());
        $stmt->execute();

        return $this->getDataJsonCache($stmt, 'order');
    }

    /**
     * The orders of the period, then the paid ones only (the statuses the dashboard counts): their
     * revenue with taxes and shipping less the discount, and the average of a paid order, each order brought back to the default currency of the shop
     * by the rate it was placed with (the amounts of an order are in its own currency, and the
     * file lists them so, with the currency beside): counted with the very query of the rows, so the figures
     * of the e-mail and the lines of the file agree.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $paidStatuses = implode(',', array_map('intval', OrderStatusQuery::getPaidStatusIdList()));
        $isPaid = '' === $paidStatuses ? '0' : 'summarized.order_status_id IN ('.$paidStatuses.')';
        $amount = 'CASE WHEN summarized.order_currency_rate > 0 THEN summarized.order_total_with_taxes_shipping_discount / summarized.order_currency_rate ELSE summarized.order_total_with_taxes_shipping_discount END';

        $stmt = $this->statement('SELECT COUNT(*) AS orders_count, COALESCE(SUM(CASE WHEN '.$isPaid.' THEN 1 ELSE 0 END), 0) AS paid_orders_count, COALESCE(SUM(CASE WHEN '.$isPaid.' THEN '.$amount.' ELSE 0 END), 0) AS revenue FROM ('.$this->rowsSql().') AS summarized');
        $stmt->execute();
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['orders_count' => 0, 'paid_orders_count' => 0, 'revenue' => 0];

        $count = (int) $totals['orders_count'];
        $paidCount = (int) $totals['paid_orders_count'];
        $revenue = (float) $totals['revenue'];
        $locale = $this->language->getLocale();
        $translator = Translator::getInstance();

        return [
            new ReportSummaryLine($translator->trans('Orders', [], 'core', $locale), (string) $count),
            new ReportSummaryLine($translator->trans('Paid orders', [], 'core', $locale), (string) $paidCount),
            new ReportSummaryLine($translator->trans('Revenue incl. tax', [], 'core', $locale), $this->money($revenue, $locale)),
            new ReportSummaryLine($translator->trans('Average order value', [], 'core', $locale), $this->money(0 === $paidCount ? 0.0 : $revenue / $paidCount, $locale)),
        ];
    }

    private function money(float $amount, string $locale): string
    {
        return (new FormatService(new RequestStack()))->money($amount, Currency::getDefaultCurrency()->getId(), $locale);
    }

    private function statement(string $query): StatementInterface
    {
        $stmt = Propel::getConnection()->prepare($query);
        $stmt->bindValue('locale', $this->language->getLocale());
        OrderAmountSql::bindRoundingPivots($stmt);

        foreach ($this->getDateRangeBounds() as $bound => $date) {
            $stmt->bindValue($bound, $date->format('Y-m-d H:i:s'));
        }

        return $stmt;
    }

    private function rowsSql(): string
    {
        return '
            SELECT
                *,
                (order_total_taxed_price - order_total_price) as order_total_tax,
                (order_total_taxed_price + order_postage - order_discount) as order_total_with_taxes_shipping_discount
            FROM (
                SELECT
                    `order`.ref as "order_ref",
                    `order`.created_at as "order_created_at",
                    customer.ref as "customer_ref",
                    ROUND(`order`.discount, 2) as order_discount,
                    '.$this->couponCodes().' as order_coupon_code,
                    ROUND(`order`.postage, 2) as order_postage,
                    `order`.postage_tax as "order_postage_tax",
                    `order`.postage_tax_rule_title as "order_postage_tax_rule_title",
                    '.$this->orderTotal(taxed: false).' as order_total_price,
                    '.$this->orderTotal(taxed: true).' as order_total_taxed_price,
                    COALESCE(delivery_module.title, `order`.delivery_module_title) as "delivery_module_title",
                    `order`.delivery_ref as "order_delivery_ref",
                    COALESCE(payment_module.title, `order`.payment_module_title) as "payment_module_title",
                    `order`.invoice_ref as "order_invoice_ref",
                    order_status_i18n.title as "order_status_i18n_title",
                    delivery_address_customer_title.long as "delivery_address_customer_title_long",
                    delivery_address.company as "delivery_address_company",
                    delivery_address.firstname as "delivery_address_firstname",
                    delivery_address.lastname as "delivery_address_lastname",
                    delivery_address.address1 as "delivery_address_address1",
                    delivery_address.address2 as "delivery_address_address2",
                    delivery_address.address3 as "delivery_address_address3",
                    delivery_address.zipcode as "delivery_address_zipcode",
                    delivery_address.city as "delivery_address_city",
                    delivery_country_i18n.title as "delivery_country_i18n_title",
                    delivery_address.phone as "delivery_address_phone",
                    invoice_address_customer_title.long as "invoice_address_customer_title_long",
                    invoice_address.company as "invoice_address_company",
                    invoice_address.firstname as "invoice_address_firstname",
                    invoice_address.lastname as "invoice_address_lastname",
                    invoice_address.address1 as "invoice_address_address1",
                    invoice_address.address2 as "invoice_address_address2",
                    invoice_address.address3 as "invoice_address_address3",
                    invoice_address.zipcode as "invoice_address_zipcode",
                    invoice_address.city as "invoice_address_city",
                    invoice_country_i18n.title as "invoice_country_i18n_title",
                    invoice_address.phone as "invoice_address_phone",
                    currency.code as "currency_code",
                    `order`.currency_rate as "order_currency_rate",
                    `order`.status_id as "order_status_id",
                    '.$this->taxTitles().' as "order_product_tax_title"
                FROM `order`
                LEFT JOIN customer ON customer.id = `order`.customer_id
                LEFT JOIN order_product ON order_product.order_id = `order`.id
                LEFT JOIN `module_i18n` as delivery_module ON delivery_module.id = `order`.delivery_module_id AND delivery_module.locale = :locale
                LEFT JOIN `module_i18n` as payment_module ON payment_module.id = `order`.payment_module_id AND payment_module.locale = :locale
                LEFT JOIN order_status_i18n ON order_status_i18n.id = `order`.status_id AND order_status_i18n.locale = :locale
                LEFT JOIN order_address as delivery_address ON delivery_address.id = `order`.delivery_order_address_id
                LEFT JOIN order_address as invoice_address ON invoice_address.id = `order`.invoice_order_address_id
                LEFT JOIN customer_title_i18n as delivery_address_customer_title ON delivery_address_customer_title.id = delivery_address.customer_title_id AND delivery_address_customer_title.locale = :locale
                LEFT JOIN customer_title_i18n as invoice_address_customer_title ON invoice_address_customer_title.id = invoice_address.customer_title_id AND invoice_address_customer_title.locale = :locale
                LEFT JOIN country_i18n as delivery_country_i18n ON delivery_country_i18n.id = delivery_address.country_id AND delivery_country_i18n.locale = :locale
                LEFT JOIN country_i18n as invoice_country_i18n ON invoice_country_i18n.id = invoice_address.country_id AND invoice_country_i18n.locale = :locale
                LEFT JOIN currency ON currency.id = order.currency_id
                '.$this->buildDateRangeCondition().'
                GROUP BY `order`.id
                ORDER BY `order`.created_at DESC
            ) as tmp
        ';

        return $query;
    }

    /**
     * The codes of the coupons the order carries, in the single `coupons`
     * column the export has always had.
     *
     * A top-level join would repeat every line of the order once per coupon,
     * and the totals are summed over those lines: an order paid with two
     * coupons exported twice the amount it was invoiced. Reading the codes in
     * a subquery keeps one row per line, and an order carrying several coupons
     * now lists them all instead of whichever one the database happened to
     * hand back.
     */
    private function couponCodes(): string
    {
        return "
                    (
                        SELECT GROUP_CONCAT(order_coupon.code ORDER BY order_coupon.id SEPARATOR ', ')
                        FROM order_coupon
                        WHERE order_coupon.order_id = `order`.id
                    )";
    }

    /**
     * The tax labels the order bears, listed the same way and for the same
     * reason: a line taxed twice reached the totals twice. The column has
     * carried the label rather than an amount since Thelia 2 — the amount is
     * what `order_total_tax` states.
     */
    private function taxTitles(): string
    {
        return "
                    (
                        SELECT GROUP_CONCAT(DISTINCT order_product_tax.title ORDER BY order_product_tax.title SEPARATOR ', ')
                        FROM order_product_tax
                        INNER JOIN order_product as taxed_product ON taxed_product.id = order_product_tax.order_product_id
                        WHERE taxed_product.order_id = `order`.id
                    )";
    }

    /**
     * Totals the lines of an order the way it was invoiced: OrderAmountSql states the rule of
     * each order, the sum of the order is brought back to a decimal.
     */
    private function orderTotal(bool $taxed): string
    {
        return '
                    CAST(SUM('.OrderAmountSql::lineTotal('order_product', '`order`.id', $taxed).'
                    ) AS DECIMAL(16,6))';
    }

    /**
     * A date range is optional: without one, the export covers every order.
     * A single bound is honoured on its own, so `--start` alone exports
     * everything since that date.
     *
     * @return array<string, \DateTimeInterface>
     */
    protected function getDateRangeBounds(): array
    {
        $bounds = [];

        foreach (['start', 'end'] as $bound) {
            if (($this->rangeDate[$bound] ?? null) instanceof \DateTimeInterface) {
                $bounds[$bound] = $this->rangeDate[$bound];
            }
        }

        return $bounds;
    }

    protected function buildDateRangeCondition(): string
    {
        $comparisons = ['start' => '>=', 'end' => '<='];
        $conditions = [];

        foreach ($this->getDateRangeBounds() as $bound => $date) {
            $conditions[] = '`order`.created_at '.$comparisons[$bound].' :'.$bound;
        }

        // The orders of the sale units the administrator is restricted to: a national order is left out.
        if (null !== $this->saleUnitIds) {
            $conditions[] = [] === $this->saleUnitIds ? '1 = 0' : '`order`.sale_unit_id IN ('.implode(', ', $this->saleUnitIds).')';
        }

        if ($conditions === []) {
            return '';
        }

        return 'WHERE '.implode(' AND ', $conditions);
    }
}
