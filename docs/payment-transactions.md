# Payment journal and deferred capture

Every money movement on an order — an authorization, a capture, a refund, a
void — is written to a journal the moment it happens, with its amount, its
currency, its outcome, the reference the provider gave it and who did it. A
payment module that reserves the amount first and takes it later can say so,
and the core then offers the capture, checks it against what the authorization
holds, writes it and moves the order along.

This document is the map for developers who work on the feature. The behavior
itself is specified by the test suites named below.

## Data model

One new table. The author is denormalized the way `order_history` does it:
`actor_label` survives the deletion of the admin, and the payment module is
kept by id while it is installed.

```mermaid
erDiagram
    "order" ||--o{ order_payment_transaction : "CASCADE on delete"
    currency ||--o{ order_payment_transaction : "RESTRICT"
    module |o--o{ order_payment_transaction : "SET NULL on delete"
    admin |o--o{ order_payment_transaction : "SET NULL on delete"
    order_payment_transaction |o--o{ order_payment_transaction : "parent, SET NULL"

    order_payment_transaction {
        int id PK
        int order_id FK "indexed"
        varchar_20 type "authorization | capture | refund | void"
        varchar_20 state "pending | succeeded | failed"
        decimal_16_6 amount "DECIMAL like every amount of the schema, never cents"
        int currency_id FK
        varchar_100 psp_reference "as the provider gave it, unique per (order, type)"
        int parent_id FK "the authorization a capture or a void applies to"
        int payment_module_id FK "nullable"
        varchar_20 actor_type "admin | module | system"
        varchar_255 actor_label "login or module code snapshot"
        int admin_id FK "nullable"
        varchar_50 error_code
        longvarchar error_message "for the back office, never for the customer"
        datetime created_at
    }
```

The types and states are the enums `Thelia\Domain\Payment\Enum\PaymentTransactionType`
and `PaymentTransactionState`; the columns stay plain VARCHARs so a screen can
render a value it does not know with its raw code. `order.transaction_ref` is
not touched: it still carries the reference of the main payment, for the
modules, the themes and the PDF documents that read it there.

Amounts are DECIMAL strings on the model and floats in the rest of the core.
`Thelia\Domain\Payment\Service\PaymentAmount` carries them as whole millionths in
an integer — a decimal string parsed digit by digit, a float rounded to six
decimals first — so neither representation is ever compared to the other with
`===`. An amount the column cannot hold (more than ten digits before the point)
or that is not a finite number is refused with `InvalidPaymentAmountException`
rather than wrapped around. `CurrencyMinorUnit` reads the decimals of the order
currency from its ISO code through intl (two for EUR, none for JPY, three for KWD).

## How a line gets written

`Thelia\Domain\Payment\Service\PaymentTransactionRecorder` is the only writer.
It has one method per movement rather than a generic save, because each one
knows what it has to check:

- `recordAuthorization()` needs a positive amount **and the provider
  reference** (`MissingProviderReferenceException`): without it a replayed
  notification cannot be told from a second authorization.
- `recordCapture()` refuses, with `CaptureExceedsAuthorizationException`, a
  pending or succeeded capture above what the authorization still holds. An
  order with no authorization is a module that took the price at once: its
  capture is the payment itself and is not checked against anything. Zero is a
  valid capture — an order that costs nothing is paid without taking anything.
- `recordRefund()` refuses an amount above what was taken and not yet given
  back, refunds still pending counted as given. The refund itself, towards the
  provider, belongs to another feature; this is only the line.
- `recordVoid()` writes what the authorization still held, captures still
  pending set aside, so the totals read zero left afterwards.
- `settle()` gives a pending line its outcome, `attachReference()` the reference
  the provider answered with, `markOutcomeUnknown()` the reason its outcome is
  not known. These are the only changes a line ever receives.

**Pending lines reserve.** `PaymentTransactionTotalsReader` adds up the
succeeded lines and, apart, the pending ones. A pending capture is not reported
as captured, but what it asked for is out of the remainder: a capture whose
provider has not answered may already have taken the money.

**Every check runs under the lock of the order's journal** (`PaymentJournalLock`,
a named database lock `thelia_order_payment:<order id>` taken through the
shared `Thelia\Domain\Order\Service\OrderLock`), with the write that depends on
it. The lock is insisted on: a worker that does not get it within five seconds
writes nothing and gets `PaymentJournalBusyException`. The server counts a lock
taken twice by the same connection, so code holding it calls code that takes it.

**Replays.** A movement reported again is answered with the line already
written, and `ORDER_PAYMENT_TRANSACTION_RECORDED` is raised again for it, so a
notification that failed half way — the line written, the order not moved —
heals when the provider replays it. A line with a reference is found by it, the
unique index `(order_id, type, psp_reference)` backing the lookup; the column
has a binary collation, provider references being case-sensitive. A settled line
without a reference is found by type, outcome and amount within the last
minute. A pending line that a notification reports with an outcome is settled.
A notification whose reference the journal does not know settles the one
pending line of the same movement and amount still waiting for a reference — a
call that timed out, or whose answer could not be recorded, leaves exactly
that — and is never guessed between two such lines.
A reference the journal holds with another outcome or another amount is refused
with `ConflictingPaymentReferenceException`: a new attempt carries a new
reference.

Every write, settlement and replay raises `ORDER_PAYMENT_TRANSACTION_RECORDED`
with an `OrderPaymentTransactionEvent` carrying the order and the line. Its
listeners must stand being called twice for the same line.

A failure of the recorder is **not** swallowed, unlike an order history entry: a
payment whose trace cannot be written is a payment the merchant cannot account
for. The recorder is not called inside a database transaction the caller
opened: the lock would be released before that transaction commits.

## Modules that declare nothing

A module that takes the price at once tells the core nothing but "paid",
through the order status. `RecordImmediateCaptureListener` listens to
`ORDER_UPDATE_STATUS` at priority 4 — after the core has saved the status and
after every core listener of it (history and invoice numbering at 64, coupons at
10, status actions at 5) — and, the first time an order reaches a paid status
coming from an unpaid one, writes a succeeded capture of the order total,
carrying the transaction reference the module saved on the order, authored by
the module (or by the administrator who marked the order paid). Cheque,
FreeOrder and every published module get their line without a line of code.

Nothing is written for a module that implements the capture interface and
answers `supportsDeferredCapture()`, nor when the journal already holds an
authorization or a capture, succeeded or pending, whatever wrote them: an open
authorization is not taken by marking the order paid, and a module that writes
its own captures keeps a single line. A failure here is logged and the status
change goes on: the status is committed, and a notification answered with an
error would be retried on an order already paid.

## Deferred capture

A module that reserves the amount first implements
`Thelia\Module\PaymentModuleWithCaptureInterface`. It is not part of
`PaymentModuleInterface` on purpose: adding a method to an interface every
published module implements would break them all.

- `supportsDeferredCapture()` says whether, as currently configured, the module
  authorizes first. A module can expose that choice to the merchant.
- The module writes the **authorization** itself, through the recorder, when
  the provider confirms it — typically from its notification controller — with
  the provider reference.
- `capture(Order, float $amount, OrderPaymentTransaction $pending)` and
  `voidAuthorization(Order, OrderPaymentTransaction $pending)` call the
  provider and answer a `PaymentOperationResult`: succeeded with the provider
  reference, failed with the provider's code and message, or pending when the
  outcome will only come with a later notification — the module then reports it
  through the recorder under the reference it answered with, which settles the
  pending line.
- A `PaymentException` thrown by the module is a refusal: the line is settled
  as failed with its message. Any other exception — a timeout, a broken
  connection — leaves the line **pending**: the call may have reached the
  provider. The technical message goes to the log, never to the journal every
  order reader sees, which gets a generic one. Both are rethrown.
- A module that answers with a reference another line of the order already
  carries leaves the line pending as well, annotated `conflicting_reference`:
  whether the provider took the money that time cannot be told, and the
  notification bringing the real reference settles it.

`Thelia\Domain\Payment\Service\PaymentCaptureService::capture(Order, ?float)`
is what the back office and the admin API call, through the
`ORDER_PAYMENT_CAPTURE` event (`OrderPaymentCaptureEvent`, null amount for the
whole remainder, rounded down to the smallest coin). Under the journal lock it
reads the totals, refuses an amount that is not positive, has more decimals than
the currency or exceeds the remainder **before anything leaves the shop**,
refuses the same amount asked again within a minute (`DuplicateCaptureException`),
and writes the capture as pending with the latest authorization as its parent.
Outside the lock it calls the module, then settles the line with the answer.
`voidAuthorization()` works the same way.

```mermaid
sequenceDiagram
    participant BO as Back office / API
    participant S as PaymentCaptureService
    participant R as PaymentTransactionRecorder
    participant M as Payment module
    participant L as MoveOrderOnPaymentTransactionListener

    BO->>S: capture(order, 50.00)
    S->>R: under the lock: totals, repeat guard, recordCapture(pending)
    S->>M: capture(order, 50.00, pending line)
    M-->>S: PaymentOperationResult::succeeded("PSP-REF")
    S->>R: settle(line, succeeded, "PSP-REF")
    R-->>L: ORDER_PAYMENT_TRANSACTION_RECORDED
    L->>L: nothing held any more ? move to paid
```

## Statuses

`MoveOrderOnPaymentTransactionListener` moves the order along with the money,
through `ORDER_UPDATE_STATUS`, so the stock, the invoice numbering and the
history see each move like any other:

- a succeeded **authorization** puts an unpaid order (not paid, cancelled or
  refunded, custom equivalents included) in `awaiting_capture`;
- once the authorization holds nothing more — everything captured, or the rest
  released by a succeeded **void** — and no capture or void waits for its
  answer, the order is `paid` if anything was taken and back to `not_paid` if it
  was all released. A remainder smaller than the smallest coin of the currency
  counts as nothing left;
- a capture on an order with no authorization moves nothing: that module says
  "paid" itself.

The journal is the truth and the status follows it. A move the transition graph
refuses is logged and not made, and a status listener that fails is logged: the
line stays written and the provider's notification is answered.

Cancelling an order whose authorization still holds an amount releases it
through the module (`VoidAuthorizationOnCancelListener`, priority 3); a module
that cannot is logged for the merchant to release it at the provider.

`awaiting_capture` (`OrderStatus::CODE_AWAITING_CAPTURE`) is **not** a canonical
status. It is seeded at install, and by `3.3.0.sql`, as a custom status
equivalent to `not_paid`, so `isPaid(false)`, `isNotPaid(false)` and the
modules reading them keep their answer, and `OrderStatus::CANONICAL_CODES` is
unchanged. A merchant may rename it or delete it; the core looks it up by code
and leaves an authorized order unpaid when it is gone.

The checkout does not read `isPaid()` for this: `Order::isPaymentSecured()`
answers true for an order paid, refunded, on hold for capture, or whose journal
still holds an authorized or pending amount. `OrderFacade::findUnpaidOrderOf()`
and the session's paid-cart check read it, so an authorized order is neither
presented to its module again nor cancelled for a new one — which would reserve
the amount twice on the buyer's card — and its cart is consumed.

## Rights

Reading the journal goes with reading orders (`admin.order`). Taking money is
a right of its own, `admin.order.payment-capture`
(`AdminResources::ORDER_PAYMENT_CAPTURE`), granted profile by profile, the way
forcing a status transition already is.

## Security notes

- The journal holds provider references and error messages, never card data,
  cryptograms or reusable tokens. `psp_reference` is capped at 100 characters
  to discourage storing a whole payload there.
- A provider error message is stored as the provider sent it, for the back
  office; it is never shown to the customer and must be escaped when rendered.
- The capture amount is checked in the service, against the journal, not in
  the screen.

## Admin API

Three operations, none of them on the front API — a customer sees whether the
order is paid, which the order already exposes:

- `GET /api/admin/orders/{orderId}/payment_transactions` — the journal, latest
  first, twenty per page (`Thelia\Api\Resource\OrderPaymentTransaction`, read
  only, `admin.order` right).
- `GET /api/admin/orders/{orderId}/payment` — the totals (`pendingCapture`
  apart) and whether the module `supportsCapture` (`OrderPaymentSummary`,
  `admin.order` right).
- `POST /api/admin/orders/{orderId}/capture` with `{"amount": 50}` or `{}` —
  the capture, answered 201 with the journal line
  (`OrderPaymentCapture`, mapped to the `admin.order.payment-capture` right
  with create access). The processor goes through `ORDER_PAYMENT_CAPTURE`, so it
  shares every guard of the capture service with the back office. A repeated
  amount within a minute, or a journal another worker is writing, is a 409; any
  other `PaymentException` a 422; a module that could not reach its provider a
  502 with a generic message, the capture staying pending. `amount` is bounded
  by what the column holds. The global admin API rate limit applies on top.

An administrator authenticated by a JWT holds no back-office session:
`OrderHistoryActorResolver` reads the Symfony security token when the session
has no admin, so the line is the administrator's, for the payment journal and
the order history alike.

## Back office

The payment card of the order sheet (`order/detail.html.twig` of the Twig
theme) includes `order/_payment_journal.html.twig`: the totals when an
authorization exists or the module can capture, what waits for the provider, a
notice that marking the order paid by hand takes nothing while the
authorization still holds an amount, the *Capture payment* button, and the
journal table, newest first. `OrderPaymentContextBuilder` composes it from
`OrderPaymentTransactionRepository` (the reads), `OrderPaymentLinePresenter`
(labels, badges, author in clear) and the core totals reader; it returns an
empty, disabled block to an administrator without the orders permission, the
way the history block does. The button, and the dialog
`order/_payment_capture_modal.html.twig`, only exist for an administrator
holding the capture right (create on `admin.order.payment-capture`) when the
module supports deferred capture and at least a smallest coin is left; the
dialog is prefilled with the remainder rounded down to it.

`OrderController::capturePayment()` answers `POST
/admin/order/update/{order_id}/payment-capture`: the capture right first, then
the shape of the typed amount (a comma counts as a decimal separator, empty
means the remainder), then `AdminFormAction::tokenAction()` with the CSRF
token, the `ORDER_PAYMENT_CAPTURE` event and an administration log line naming
the order, the amount and the outcome. A double submit is refused by the
capture service as a repetition; a refusal from the core comes back as the
usual error flash.

## Installation and update

A fresh install creates the table and seeds the status and the right
(`setup/thelia.sql`, `setup/insert.sql`). An installed shop gets them from
`setup/update/sql/3.3.0.sql`: the table empty, the status appended after the
existing ones, the right with its titles. Orders placed before the update keep
their transaction reference and show no movement until their next payment
event; the demonstration database has no journal either, and the screens must
render an empty one without error.

## Test suites

- `tests/Unit/Domain/Payment/PaymentAmountTest.php` — comparisons at the
  precision of the column, the overflow refused.
- `tests/Unit/Domain/Payment/CurrencyMinorUnitTest.php` — decimals per
  currency, the smallest coin.
- `tests/Unit/Domain/Payment/PaymentTransactionTotalsTest.php` — what is left
  to capture or to refund, pending lines reserving.
- `tests/Integration/Domain/Payment/PaymentTransactionRecorderTest.php` — one
  line per movement, the capture and refund ceilings, partial capture then
  balance, replays (healing after a listener failure, conflicting references,
  a pending line settled by its notification), case-sensitive references, the
  status rules, a transition the graph refuses, the actor.
- `tests/Integration/Domain/Payment/ImmediateCaptureTest.php` — Cheque and
  FreeOrder get their line; `order.transaction_ref` is untouched; paid to
  processing writes nothing more; no line on top of an open authorization or a
  module's own capture; the listener priority.
- `tests/Integration/Domain/Payment/PaymentCaptureServiceTest.php` — a module
  that defers its capture, through `tests/Support/Payment/DeferredCapturePaymentModule.php`:
  hold, capture, partial capture, refusal, unknown outcome left pending,
  pending reserving, repetition, currency precision, void during a pending
  capture, release on cancellation, a module that cannot load.
- `tests/Integration/Domain/Payment/AuthorizedOrderCheckoutTest.php` — an
  authorized order is not the unpaid order of its cart.
- `tests/Http/BackOffice/OrderPaymentBackOfficeTest.php` — the payment card:
  the cheque line, the empty journal, the totals, the prefilled dialog and its
  rounding, the hold notice, the capture from the dialog and its log line, a
  partial capture typed with a comma, the refusals, a forged token, a double
  submit, and who sees or may use the button.
- `tests/Api/Admin/OrderPaymentApiTest.php` — the three admin operations:
  shape of a line, scope, the totals, the capture and its refusals, the repeat
  guard, a retried capture while the first is pending, the amount bound, and
  who is refused (anonymous, a customer token, order readers without the
  capture right, the capture right alone).
