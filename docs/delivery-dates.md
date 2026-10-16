# Delivery dates

The buyer picks the day, and for a carrier that offers slots the slot of that day, they want to be delivered on or to collect the order. The choice is held on the cart, judged by the server, copied on the order, and shown on the order in the back office, on the delivery note, in the confirmation e-mails and through the front API.

Nothing changes for a shop that sets nothing: no carrier offers a date until the merchant writes a rule for a carrier that accepts dates, and the delivery step stays as it was.

## For a delivery module

A module opts in by implementing `Thelia\Module\DeliveryDateAwareInterface`:

```php
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\DeliveryDateAwareInterface;

final class MyCarrier extends AbstractDeliveryModule implements DeliveryDateAwareInterface
{
    public function getAcceptedDeliveryDateChoiceModes(): array
    {
        return [DeliveryDateChoiceMode::Date, DeliveryDateChoiceMode::Slot];
    }
}
```

The module says which shapes it can honour; the merchant picks one of them per carrier. A module that does not implement the interface loses nothing: a rule written for it is ignored, and so is a rule whose shape the module stops accepting.

The core never knows the rounds of a carrier, only the shape of the choice and the constraints the merchant set. A module that prices or plans by the day reads the picked day off `DeliveryPostageEvent::getDeliveryDate()` (null until the buyer picked one).

A module whose class must keep loading on a core that predates the contract can alias an empty interface of its own to `Thelia\Module\DeliveryDateAwareInterface` when `interface_exists()` says it is missing (see CustomDelivery).

## Settings

Written through `Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings`, which holds every rule a setting must follow (the back office screen is Configuration › Shipping › Delivery dates, permission `admin.configuration.delivery-date`).

| Setting | Where | Notes |
|---|---|---|
| Closed days of the week of the shop | config `delivery_closed_weekdays` | ISO numbers 1 (Monday) to 7 (Sunday), comma separated, empty by default |
| Shape of the choice, minimum delay, horizon | `delivery_date_rule`, one row per carrier | `choice_mode`: `none`, `date`, `slot`; days counted from today; horizon at most 366 days |
| Closed days of the week of a carrier | `delivery_date_rule.closed_weekdays` | NULL follows the shop; an empty string means every day is open |
| Slots | `delivery_slot` (+ `delivery_slot_i18n.title`) | Local start and end hours, the same every open day; `capacity` in orders, NULL for no limit |
| Exceptional closures | `delivery_closure` | Both days included; `module_id` NULL closes the whole shop |
| Cutoff hour of the shop | config `delivery_cutoff_time` | `H:i`; an order at or after it is prepared the next working day; empty for none |
| Preparation and transit days of a carrier | `delivery_date_rule.preparation_days`, `transit_min_days`, `transit_max_days` | Working days; both transit bounds set the estimate on, all three NULL take it off; 0 is a delay. Any carrier, whether it accepts dates or not |
| Availability delay of a product | `product.availability_delay_days` | Working days added to the preparation; NULL adds none |

## How a day is judged

`DeliveryDateCalendar::offerFor($module, $now, $locale)` lists every day from today + delay to today + horizon, each with `open` (the carrier delivers that day) and `available` (open and, for slots, one slot left), and for slots their hours and whether each can still be taken (a slot of today whose hours are over cannot). It reads neither the session nor the cart, and costs the same few queries whatever the length of the window. Days are calendar days in the time zone PHP runs in, which is the shop's; hours are local.

`DeliveryDateGuard` judges a choice against that list, recomputed at the time of asking. It is called by `CartGuard::checkValidDelivery()`, so the Flexy tunnel, `GET .../validation` and `POST .../place` all refuse the same way:

| Code | Raised when |
|---|---|
| `delivery-date-missing` | The carrier offers dates and none, or no slot, was picked |
| `delivery-date-unavailable` | The day or slot is not offered: outside the window, closed, a slot of another carrier, a forged value |
| `delivery-slot-full` | The slot has no place left that day |

The three exceptions extend `InvalidDeliveryException`, so a caller that already sends the buyer back to the delivery step does so for them too.

The choice falls by itself when the carrier of the cart changes (`Cart::preSave()`). `CartFacade::dropDeliveryDateIfNoLongerPossible()` takes off a day that became impossible and says whether it did, for a theme to tell the buyer.

## Estimated delivery

The buyer picks nothing here: each carrier announces the days a parcel is expected between, before the order. `DeliveryTimeEstimator` reads the delays and the calendar, `DeliveryIntervalCalculator` counts:

- The day the order comes in is the first day of preparation, unless the shop is closed that day or the order comes in at or after the cutoff: preparation starts on the next working day of the shop. The parcel leaves at the end of the last day of preparation; zero days of preparation leave the same day, like one.
- The transit counts working days of the carrier from the day after the parcel leaves; zero days deliver that day, or on the next day the carrier works.
- Preparation days follow the shop closed days of the week and the shop closures; transit days follow the carrier closed days of the week (or the shop's) and the closures of the carrier and of the shop.
- The slowest product of the cart adds its `availability_delay_days` to the preparation, counted after the first day: zero or one day of preparation plus two days of the product ship on the third working day. A delay is read as at most 366 days, and a count as at most 732 working days; the calendar is walked for at most a week per working day, so a shop open one day a week still gets a date.
- A carrier that lets the buyer pick a day (`choice_mode` it accepts) shows its calendar and announces no estimate, on the delivery step, on the product page and on the order.

So one day of preparation and two of transit deliver an order of Tuesday morning on Thursday, and on Friday once the cutoff is passed.

| Where | What is shown |
|---|---|
| Delivery options (delivery step, `GET /api/front/delivery_modules`) | `minimumDeliveryDate` and `maximumDeliveryDate`, `Y-m-d`, filled by `DeliveryOptionsProvider` unless the module wrote its own (an empty string counts as none) |
| Order | `order.estimated_delivery_from` / `estimated_delivery_to`, counted again when the order is placed (`OrderFactory`): an order paid after the cutoff hour gets the next day, which is the day it will really be prepared. It then does not move when the delays change. Empty when the buyer picked a day, and when the count fails, which never refuses the order. Order API: `estimatedDeliveryFrom`, `estimatedDeliveryTo` |
| Product page | `ShippingFacade::estimateProductDelivery($product, $cart)`, or `estimateProductDeliveryById($productId, $cart)` for a theme that holds the id only (null for a product the visitor cannot see: unknown, offline, or out of their catalogue by a `ProductVisibility` rule): the soonest of the active carriers delivering to the delivery address of the cart, else the default address of its customer, else the default country of the shop, with that country, for the theme to name it. Null for a virtual product or when no carrier there announces delays |

`DeliveryEstimateCalendar` loads the delays of every carrier, the closures and the shop settings once per request, so listing or comparing several carriers costs no query per carrier; `DeliveryDateSettings` empties it on every write. A failure to count an estimate (a database that does not answer, settings that cannot be read) is logged with its class and a trace without arguments, and leaves the page or the order without one. The order counts its estimate before its transaction opens. The estimate reads neither the session nor the request, and counts days in the time zone PHP runs in, so a command that writes an e-mail gets the same days as the delivery step. Thelia has no time zone setting of its own: set `date.timezone` to the shop's, for the web server and the command line alike. The estimate depends on the visitor's address and on today: Flexy serves the product page as `private`, and a theme or a proxy must not put it in a shared cache.

## Capacity

The place in a slot is taken in the transaction of `OrderFacade::createOrder()`, by a single conditional UPDATE on `delivery_slot_booking` (`DeliverySlotBooker`), the way stock is decremented: two buyers racing for the last place are arbitrated by the database, and the one refused gets `delivery-slot-full` with the order rolled back. A cancelled or refunded order gives its place back; brought back to another status, it takes it again whatever the capacity says. The number of orders a slot holds is never published.

## On the order

`order.delivery_date`, `delivery_slot_id` (NULL once the slot is deleted) and the hours `delivery_slot_start` / `delivery_slot_end`, copied when the order is placed: editing or deleting a slot afterwards changes nothing on placed orders. The date is information of the order, never a state: a day gone by blocks no transition.

- Loop `order`: `DELIVERY_DATE` (Y-m-d), `DELIVERY_SLOT_START`, `DELIVERY_SLOT_END` (H:i).
- Loop `order`: `ESTIMATED_DELIVERY_FROM`, `ESTIMATED_DELIVERY_TO` (Y-m-d), the interval announced when the order was placed; the order e-mails of the default mail template repeat it.
- Customer data export: `delivery_date` and `delivery_slot` per order.

## Front API

| Operation | What it does |
|---|---|
| `GET /api/front/delivery_modules` | Each carrier carries `deliveryDateChoice`: `none`, `date` or `slot` |
| `GET /api/front/delivery_modules/{moduleId}/delivery_dates?locale=` | The window: `choiceMode`, and `days[]` of `{date, open, available, slots[]{id, title, start, end, available}}` |
| `POST /api/front/account/checkout/{cartId}/delivery_date` | Body `{"deliveryDate": "2026-10-09", "deliverySlotId": 4}`; `null` clears. Every refusal, a day written any other way than Y-m-d included, answers 422 in the shape of `.../validation` |
| `GET /api/front/account/orders[/{id}]` | `deliveryDate` (Y-m-d), `deliverySlotStart`, `deliverySlotEnd` (H:i) |

`Cart` carries `deliveryDate` and `deliverySlotId`, read only. The day is published as written, Y-m-d: a client must not turn it into an instant of its own time zone. The availability endpoint takes no address and needs no account; an anonymous caller counts against the per-address limiter of the API (`limiter.api_anonymous`).
