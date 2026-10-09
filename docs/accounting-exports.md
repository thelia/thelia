# Accounting exports

Two exports of the Orders category give the accountant the sales of a period in a
file their software loads: the sales journal, one row per accounting entry, and
the tax summary, one row per month and per tax rate. Both run from the back-office
export screen and from the command line, on the invoices of the period by their
invoice date.

This document is the map for developers who work on the feature. The behavior
itself is specified by the test suites named below.

## Chart of accounts

Settings of the shop. The sales journal refuses to run until the customer
account, the shipping account and at least one tax rate are set, and says which
one is missing: no account is ever made up.

| Setting | Example | Meaning |
|---|---|---|
| `accounting_journal_code` | `VE` | code of the sales journal, `VE` when empty |
| `accounting_journal_label` | `Ventes` | its label, `Ventes` when empty |
| `accounting_customer_account` | `411000` | debited of every invoice |
| `accounting_shipping_account` | `708500` | credited of the shipping, excluding tax |
| `accounting_rate_accounts` | `20:706200:445720,5.5:706055:445705,0:706000` | per tax rate in percent: the product account, then the account of the tax collected (not needed at 0) |

`AccountingChart` reads them and refuses a setting it cannot read: an account
that is not letters and digits, a taxed rate without tax account, a rate given
twice.

## A piece per invoice

`SalesPieceBuilder` books one invoiced order, in whole cents:

- the customer is debited of the total of the invoice, `Order::getTotalAmount()`,
  and the tax collected is its tax: the journal says what the invoice handed to
  the customer said, under the rounding rule the order was priced with, older
  orders included;
- the products are credited by tax rate, the rate being the one their frozen
  taxes make on their frozen price (to the hundredth of a percent);
- an order discount is spread over the rates in proportion of their amount
  excluding tax;
- the shipping is credited excluding tax, at the rates `order_postage_tax` froze
  when it was split between rules, or at the one rate its tax makes;
- the tax collected is credited by rate, the shipping's included.

Every split is made in whole cents, the cents left over going to the largest
remainders, so the lines add up to the totals and the piece balances to the cent.
An order in another currency is booked in the shop currency at the rate frozen on
the order, each line keeping its own amount and currency.

An order the chart cannot book (a rate without account, a discount larger than
the products, an invoice of zero) is left out and named in the report.

## The journal

`SalesJournal` selects the orders whose invoice date falls in the period and that
have an invoice reference, by invoice date, by batches of 200, and gives their
pieces one by one: a year of invoices is never held at once. The order is booked
when it is invoiced, so an order placed in January and invoiced in February is in
February. The index `idx_order_invoice_date` (3.3.0) serves the selection.

## The two exports

- `thelia.export.sales_journal` (`SalesJournalExport`): its columns are the 18
  fields of the French accounting entries file, in their order. The piece is the
  invoice: `EcritureNum` and `PieceRef` are its reference; `EcritureDate`,
  `PieceDate` and `ValidDate` its date; `CompAuxNum` and `CompAuxLib` the
  customer reference and name, on the customer line only; `EcritureLib`
  "Invoice <ref> <customer>"; `EcritureLet` and `DateLet` empty;
  `Montantdevise` and `Idevise` only for an order in another currency. Labels
  follow the language of the export (`--locale=fr_FR` on the command line).
- `thelia.export.tax_summary` (`TaxSummaryExport`): per month of invoice and
  per rate, the amount taxed, the tax and the number of invoices, read from the
  same pieces, so it adds up to the tax lines of the journal.

Written with the FEC serializer (`thelia.fec`, `FECSerializer`), the journal is
the accounting entries file: tab separated, CR LF, the field names first, dates
as YYYYMMDD, amounts with a decimal comma, UTF-8, no spreadsheet guard. With the
CSV serializer it is a sheet to read.

```bash
php Thelia export thelia.export.sales_journal thelia.fec --start=2026-02-01 --end=2026-02-28 --locale=fr_FR
```

## Report

An export implementing `ReportingExportInterface` gives an `ExportReport` once
it has run: the export command prints it after the path of the file. The sales
journal reports the number of pieces and the totals of debit and credit, the
orders it left out with the reason, how many orders of the period have an invoice
date but no invoice reference, and that credit notes are not in the journal.

## Not covered

- Credit notes: no credit note module runs on Thelia 3 yet. The report says so;
  an active `CreditNote` module is named.
- Matching (`EcritureLet`) and the payments received.

## Tests

- `tests/Unit/Domain/Accounting/AccountingChartTest.php`
- `tests/Integration/Domain/Accounting/SalesPieceBuilderTest.php`: one rate, two rates, shipping split, discount, the rounding rules, an order older than them, a rate without account, another currency.
- `tests/Unit/Core/Serializer/FECSerializerTest.php`
- `tests/Integration/Domain/DataTransfer/AccountingExportsTest.php`: refusal, a month by invoice date, the report, the tax summary against the journal, the catalogue, the command.
