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
| `accounting_commercial_credit_account` | `709000` | optional: debited of commercial credit notes, the sales account of their rate when empty |

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
  taxes make on their frozen price, filed under the rate of the chart whose tax on
  that price, rounded to the cent, is within a cent of the frozen tax, or which is
  within a tenth of a point (0.20 of tax on 0.99 makes 20.20%, 0.83 on 4.17 makes
  19.90%: both are filed at 20%); a line taxed twice is filed under the sum of its
  rates, which the chart needs a row for;
- an order discount is spread over the rates in proportion of their amount
  excluding tax;
- the shipping is credited excluding tax on the shipping account, at the rates
  `order_postage_tax` froze when it was split between rules, or at the one rate
  its tax makes; untaxed shipping needs no row of its own in the chart;
- the tax collected is credited by rate, the shipping's included.

Every split is made in whole cents, the cents left over going to the largest
remainders, so the lines add up to the totals and the piece balances to the cent.
An order in another currency is booked in the shop currency at the rate frozen on
the order, each line keeping its own amount and currency; the cent the conversion
leaves goes to the largest sale, never to a tax. An order without exchange rate
is left out.

An order priced before the rounding rules (`last_legacy_rounding_order_id`) is
booked as its invoice says, and that invoice did not take the discount off the
tax: the tax of such an order with a discount is the tax before discount, and the
amount excluding tax carries the whole discount.

An order the chart cannot book (a rate without account, a discount larger than
the products, an invoice of zero) is left out and named in the report.

## A piece per credit note

When the `CreditNote` module is active and its tables are there, the journal also
books its credit notes. The core does not depend on the module: `CreditNoteReader`
reads its tables in SQL (the columns of CreditNote 4.4.0), and a change of their
schema in the module breaks the read.

- A credit note is booked once it has an invoice reference and a status the
  module marks invoiced (`accepted`, `used`), at its invoice date. The module
  sets an invoice date at creation too: the reference is what tells a numbered
  credit note.
- `CreditPieceBuilder` books it as the reverse of an invoice: the customer is
  credited of `total_price_with_tax`, the products, the shipping and the tax it
  gives back are debited by rate.
- Each line is filed under the rate its unit amounts make, through the chart as
  for the invoices; for a product of the order, the module copied the amounts
  frozen on the order.
- The discount is taken off the products in proportion of their amount in tax
  and untaxed at the rate of each. The module copies the discount of an order to
  `discount_with_tax` and `discount_without_tax` alike: only the first is read.
- The module never writes the exchange rate of a credit note (`credit_note.currency_rate`
  stays NULL): a credit note in another currency is booked at the rate of its order,
  the one its invoice was booked at; without rate and without order, it is left out
  and named in the report.
- A commercial credit note (types `rebate`, `discount`, `difference_refund`) is
  debited to `accounting_commercial_credit_account` when it is set: a reduction
  granted after the invoice goes to 709 in the French chart, where a discount on
  the invoice stays on the sales account.
- The total is the anchor: the cent of rounding goes to the largest line, and a
  total further from the lines than a cent per line is named in the report.

A credit note used to pay an order (`order_credit_note`) is a settlement, not a
sale given back: it is not in the sales journal.

## The journal

`SalesJournal` selects the orders whose invoice date falls in the period and that
have an invoice reference, by invoice date then id, by batches of 200, and gives
their pieces one by one, letting go of the models of each batch: a year of invoices
is never held at once. Each batch starts after the last order of the previous one,
not at an offset, so an order invoiced while the export runs neither shifts the
batches nor makes an invoice appear twice. The customer, the invoice address and
the currency come with the orders of the batch; their lines, the frozen taxes of
the lines and the shares of the postage are read for the whole batch
(`FrozenOrderAmounts`). An order then costs one query, the one
`Order::getTotalAmount()` makes for the totals of its invoice. The order is booked
when it is invoiced, so an order placed in January and invoiced in February is in
February. The index `idx_order_invoice_date` (3.4.0) serves the selection. The
credit notes are read the same way, by batches after the last one read, and
merged with the invoices in date order.

## The two exports

- `thelia.export.sales_journal` (`SalesJournalExport`): its columns are the 18
  fields of the French accounting entries file, in their order. The piece is the
  invoice or the credit note: `EcritureNum` and `PieceRef` are its reference; `EcritureDate`,
  `PieceDate` and `ValidDate` its date; `CompAuxNum` and `CompAuxLib` the
  customer reference and name, on the customer line only; `EcritureLib`
  "Invoice <ref> <customer>" or "Credit note <ref> <customer>"; `EcritureLet` and `DateLet` empty;
  `Montantdevise` and `Idevise` only for an order in another currency. Labels
  follow the language of the export (`--locale=fr_FR` on the command line).
- `thelia.export.tax_summary` (`TaxSummaryExport`): per month of invoice and
  per rate (the highest first), the amount taxed (products and shipping), the
  tax and the number of pieces, less the credit notes, read from the same pieces,
  so it adds up to the tax lines of the journal.

Written with the FEC serializer (`thelia.fec`, `FECSerializer`), the journal is
the accounting entries file: tab separated, CR LF, the field names first (even
for a period without entry), every row in the order of the format, dates as
YYYYMMDD, amounts with a decimal comma, UTF-8, no spreadsheet guard. The file is
named like any export; rename it `<SIREN>FEC<YYYYMMDD>.txt` before handing it to
the tax administration. With the CSV serializer it is a sheet to read.

```bash
php Thelia export thelia.export.sales_journal thelia.fec --start=2026-02-01 --end=2026-02-28 --locale=fr_FR
```

## Report

An export implementing `ReportingExportInterface` gives an `ExportReport` once
it has run: the export command prints it after the path of the file. The sales
journal reports the number of pieces and the totals of debit and credit, the
number of credit notes among them, the orders and credit notes it left out with
the reason, how many orders of the period have an invoice date but no invoice
reference, how many orders placed in the period have no invoice date (with an
invoice reference, a warning: such an order belongs to no period and is booked by
hand; without, they are simply not invoiced), and, when the credit notes cannot be read (no module, or the
`CreditNote` module active without its tables), that they are not in the journal.

A report keeps at most `ExportReport::MAX_WARNINGS` warnings and ends the list with
the number of those it left out; `ExportJob::recordReport()` cuts what remains of a
report that would still be longer than the column of the job.

Both exports implement `MailableReportInterface`: the scheduled report mailing, which
sends the export as a CSV file, opens its mail with their totals, computed again by
`SalesJournal::totals()` and never from the rows of the export. The journal gives the
number of pieces, of which credit notes while the module is read, the total debit and
the total credit; the tax summary the amount taxed, the tax collected and the number of
pieces. A mailing of an export launched without a chart of accounts fails as a whole.

## Not covered

- Matching (`EcritureLet`) and the payments received.

## Tests

- `tests/Unit/Domain/Accounting/AccountingChartTest.php`
- `tests/Integration/Domain/Accounting/SalesPieceBuilderTest.php`: one rate, two rates, shipping split, discount, the rounding rules, an order older than them, a rate without account, another currency.
- `tests/Unit/Core/Serializer/FECSerializerTest.php`
- `tests/Integration/Domain/Accounting/CreditNoteJournalTest.php`: creates the tables of the module for the class; full and partial credit notes, discount, commercial account, statuses left out, module inactive, date order, tax summary, another currency.
- `tests/Integration/Domain/DataTransfer/AccountingExportsTest.php`: refusal, a month by invoice date, the report, the orders without invoice, the queries of an order, an invoice issued during the export, the tax summary against the journal, the catalogue, the command.
