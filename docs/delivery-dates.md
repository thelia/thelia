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

## Capacity

The place in a slot is taken in the transaction of `OrderFacade::createOrder()`, by a single conditional UPDATE on `delivery_slot_booking` (`DeliverySlotBooker`), the way stock is decremented: two buyers racing for the last place are arbitrated by the database, and the one refused gets `delivery-slot-full` with the order rolled back. A cancelled or refunded order gives its place back; brought back to another status, it takes it again whatever the capacity says. The number of orders a slot holds is never published.

## On the order

`order.delivery_date`, `delivery_slot_id` (NULL once the slot is deleted) and the hours `delivery_slot_start` / `delivery_slot_end`, copied when the order is placed: editing or deleting a slot afterwards changes nothing on placed orders. The date is information of the order, never a state: a day gone by blocks no transition.

- Loop `order`: `DELIVERY_DATE` (Y-m-d), `DELIVERY_SLOT_START`, `DELIVERY_SLOT_END` (H:i).
- Customer data export: `delivery_date` and `delivery_slot` per order.

## Front API

| Operation | What it does |
|---|---|
| `GET /api/front/delivery_modules` | Each carrier carries `deliveryDateChoice`: `none`, `date` or `slot` |
| `GET /api/front/delivery_modules/{moduleId}/delivery_dates?locale=` | The window: `choiceMode`, and `days[]` of `{date, open, available, slots[]{id, title, start, end, available}}` |
| `POST /api/front/account/checkout/{cartId}/delivery_date` | Body `{"deliveryDate": "2026-10-09", "deliverySlotId": 4}`; `null` clears. Every refusal, a day written any other way than Y-m-d included, answers 422 in the shape of `.../validation` |
| `GET /api/front/account/orders[/{id}]` | `deliveryDate` (Y-m-d), `deliverySlotStart`, `deliverySlotEnd` (H:i) |

`Cart` carries `deliveryDate` and `deliverySlotId`, read only. The day is published as written, Y-m-d: a client must not turn it into an instant of its own time zone. The availability endpoint takes no address and needs no account; an anonymous caller counts against the per-address limiter of the API (`limiter.api_anonymous`).
