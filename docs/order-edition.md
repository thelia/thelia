# Editing an order after it was placed

A merchant can change the lines of an order once it is placed: the quantity or
the unit price of a line, a line removed, a product added by its reference or
its GTIN, the discount and the postage. The totals are computed again, the
stock follows, the change is written to the order history and the customer can
be told by e-mail.

This document is the map for developers who work on the feature. The behaviour
itself is specified by the test suites named below.

## When an order can be edited

`Thelia\Domain\Order\Edition\OrderEditor::refusal()` says why an order cannot
be edited, or returns null. An order is refused when:

- it has an invoice reference: once invoiced, changing the lines needs a credit
  note, not an edit;
- its status is not `not_paid`, `paid` or `processing`, compared through the
  equivalence of custom statuses (`getEffectiveCode()`): an order is edited
  until it is sent;
- it is exempt from VAT: the exemption was granted on the order as placed.

No column was added for this rule: it reads what the order already stores.

## What an edit changes

An edit is a `OrderEdit` value object: the lines to keep (`OrderEditLine::keep()`,
with a new quantity and optionally a new unit price excluding tax), the lines to
add (`OrderEditLine::add()`, by sale element id), and optionally a new discount
and a new postage, both including tax. A line of the order left out of the edit
is removed.

- A line added freezes the catalogue label and price of the moment, in the
  currency of the order and with the customer discount, as
  `OrderProductFactory` does at checkout. Cart price rules are not applied
  again.
- A price typed by hand stays as typed and clears the promo of the line.
- The taxes of a touched line are computed again for the country of the
  invoice address. When the product no longer exists, its taxes are scaled.
- The discount replaces the discount of the order.
- The postage is never computed again: the merchant types it. Its tax keeps the
  ratio of the old postage, and is zero when the old postage was zero.
- A quantity cannot go below what was already returned on the line, and the
  order keeps at least one product line.
- When the order holds its stock (`StockPolicy`), the stock moves by the
  difference, through `StockDecrementer`, as a change of status would.

## How an edit is applied

`OrderEditor::apply(Order, OrderEdit, string $fingerprint)` checks the whole
edit before writing anything, then writes it inside a savepoint, under a
`SELECT ... FOR UPDATE` lock of the order row. Any failure rolls back to the
savepoint, so a caller that already holds a transaction keeps it usable.

`OrderEditor::fingerprint()` hashes the status, the invoice, the discount, the
postage and every line. The back office sends the fingerprint read when the
form was displayed; an order changed since then is refused with
`OrderEditConflictException` instead of being overwritten.

`OrderEditor::preview()` runs the same code and undoes it: the totals shown
before saving are the totals the order will have.

The result is an `OrderEditOutcome`: totals and taxes before and after, the
list of changes, and whether the order was paid. `amountToRefund()` and
`amountToCollect()` give the gap a paid order leaves; the edit is accepted and
the gap is shown to the merchant, nothing is refunded or charged automatically.

Exceptions extend `OrderException`: `OrderNotEditableException`,
`InvalidOrderEditException`, `OrderEditConflictException`.

## Events, history and e-mail

- `TheliaEvents::ORDER_BEFORE_EDIT` is dispatched inside the transaction,
  before any write; `TheliaEvents::ORDER_AFTER_EDIT` after the commit. Both
  carry an `OrderEditEvent` (order, edit, outcome). A preview dispatches
  neither.
- The history gets an `order_edited` line with the changes and the totals
  before and after (see `order-history.md`).
- Order status actions gain the `edit` trigger
  (`OrderStatusActionTrigger::EDIT`): the actions of the status the order is in
  run when it is edited. Only e-mail actions (`AbstractEmailAction`) run on an
  edit; any other type is recorded as a failure, and the back office refuses to
  create one. The e-mail receives the changes as `order_changes`.
- The message `order_edited` is seeded, with a template in the default e-mail
  theme. Nothing is sent until the shop adds an e-mail action on the `edit`
  trigger of a status.

## Access

The back-office resource `admin.order.edit` (`AdminResources::ORDER_EDIT`) is
separate from `admin.order`: an administrator who can update an order does not
edit its lines without it. `setup/update/sql/3.3.0.sql` adds the resource.

## Tests

- `tests/Integration/Domain/Order/Edition/OrderEditorTest.php`: the rules,
  totals, stock, concurrency, atomicity and the customer notice.
- `tests/Integration/Domain/Order/OrderStatusActionRunnerTest.php`: the `edit`
  trigger.
- `tests/Http/BackOffice/OrderEditionBackOfficeTest.php`: the back-office
  sheet, the right and the action configuration (skipped when the installed
  back-office theme does not ship the edition page).
