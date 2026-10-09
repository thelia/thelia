# Unpaid order reminders

An order still waiting for its payment goes through a schedule the merchant sets:
a mail at a given number of hours after its creation, another later, and,
optionally, its cancellation. A command the host schedules applies it. Nothing
happens until the merchant writes a schedule, so a shop that updates does not
start reminding on its own.

This document is the map for developers who work on the feature. The behavior
itself is specified by the test suites named below.

## The schedule

Two settings of the shop, edited in the back office under Configuration > Unpaid
order reminders:

| Setting | Example | Meaning |
|---|---|---|
| `unpaid_order_reminder_schedule` | `24:order_payment_reminder,72:order_payment_reminder,168:cancel` | steps, in hours from the creation of the order; each sends the message of that code, `cancel` cancels the order |
| `unpaid_order_reminder_excluded_modules` | `Cheque,BankTransfer` | payment modules whose orders are neither reminded nor cancelled |

`UnpaidOrderReminderSchedule::fromSetting()` reads the first one and refuses, as a
whole, a step without a positive whole number of hours, two steps at the same
delay, a message code with other characters than `a-z0-9_`, a step after the
cancellation, or more than ten steps. A schedule stored in a way it cannot read
(only a hand edit of the database gets there) sends nothing and logs why.

## Which orders, which step

An order is due when its status means "waiting for its payment": `not_paid`, or a
custom status declared equivalent to it (`OrderStatus::isNotPaid(true)`). Its
payment module must not be excluded.

An order is only in one step: the last one it reached
(`UnpaidOrderReminderSchedule::stepReachedAfter()`). The steps it is past are not
sent late. This is what keeps a schedule switched on in a shop holding old unpaid
orders from mailing them reminders that no longer make sense: with
`24:...,72:...,168:cancel`, a four-day-old order gets the 72 hour reminder and
never the 24 hour one, and a two-month-old order is cancelled without a mail.

## Once and only once

Each step done is written to the order history (see [order-history.md](order-history.md)):

| Event type | Payload | When |
|---|---|---|
| `payment_reminder_sent` | `{"step": 24, "message": "order_payment_reminder"}` | the mail left |
| `payment_reminder_failed` | `{"step": 24}`, the error as comment | the mail could not leave, or the cancellation was refused |

A step of an order that has either entry is never done again, so a run replayed
sends nothing twice, and a step that keeps failing (an address the mailer
refuses, a transition the status graph forbids) does not take a place in every
run: the next step is its next chance. The mail itself also writes the usual
`email_sent` entry.

The cancellation is `Order::setCancelled()`, the status change any cancellation
goes through: the transition graph is asked, the stock is given back by the
existing rule, and the history gets its `status_changed` entry with the shop as
author.

## The command

```bash
php bin/console order:remind-unpaid [--dry-run] [--limit=200]
```

- `--dry-run` lists what the run would do, sends and changes nothing.
- `--limit` bounds the orders a run acts on, oldest first; the next run goes on.
- Two runs at once: the second finds the lock (`thelia.unpaid_order_reminder`,
  from the shop's `LOCK_DSN`) and stops without doing anything. On several web
  servers, point `LOCK_DSN` at a store they share.
- Exit code 0, or 1 when a step failed (the table says which), so the host's
  scheduler can report it.

Schedule it every fifteen minutes or every hour; the delays of the schedule are
in hours, so a run more frequent than that buys nothing.

## The payment link

The mail carries `payment_url`, a link to the `order_payment_resume` route of the
front theme (Flexy: `/order/pay/{token}`). The token is signed by
`UnpaidOrderPaymentLink` with the application secret, over the order, its expiry,
the customer's address and the cart the order was placed from: nothing is
stored, and the link dies when the order is paid or cancelled, the address
changes, or the time runs out. It expires with the order's cancellation when the
schedule ends with one, thirty days after the mail otherwise.

The theme puts the cart of the order back in the session and opens the payment
step, where paying again goes to the same order (`CheckoutPaymentService`, as for
any payment retry). A guest order needs nothing more. An order placed from an
account asks for that account first: the link never signs anybody in, so a
reminder forwarded to someone else does not hand them the account. A theme
without the route gets a link to the home page, and a warning in the log.

Reminders are part of the order the customer placed, not marketing: they are sent
whatever the customer chose about newsletters.

## Tests

- `tests/Unit/Domain/Order/Reminder/UnpaidOrderReminderScheduleTest.php`: reading the setting.
- `tests/Integration/Domain/Order/Reminder/UnpaidOrderReminderRunnerTest.php`: the steps, once, the window, the exclusions, the equivalent statuses, the dry run, the failures, the limit.
- `tests/Integration/Domain/Order/Reminder/UnpaidOrderPaymentLinkTest.php`: the token.
- `tests/Integration/Command/UnpaidOrderReminderCommandTest.php`: the command and its lock.
- `tests/Http/Flexy/UnpaidOrderPaymentLinkTest.php`, `tests/Http/BackOffice/UnpaidOrderReminderSettingsTest.php`: skipped until the themes carry the feature.
