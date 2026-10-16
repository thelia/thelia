# Sale units (multi-shop)

A **sale unit** is a place the shop sells from: a storefront on its own domain, a
pick-up store behind a shared one, a logical outlet an API consumer addresses by a
header. One Thelia instance and one database can sell from many of them over a
catalog that stays national and shared: the products, categories, attributes and
brands are the same everywhere, while each unit carries its own prices, its own
stock, and the orders placed on it.

The whole feature is off until a sale unit is turned on. With no active row in
`sale_unit`, every code path behaves exactly as it did before, with the same queries
and the same caches, and a test counts them (see *A single shop pays nothing*). A
shop that never creates a sale unit is a single shop, unchanged.

## The two ideas that make it safe

**Nullable context.** The sale unit of the current request lives in one service,
`SaleUnitContext`, the way the language, the currency and the customer each have
theirs. Null is a legitimate state: it is every request of a single-shop instance,
and a multi-shop request that resolved no unit. A reader that can answer nationally
reads `getSaleUnit()` and handles null; a writer that must not guess — an order, a
stock movement — calls `requireSaleUnit()` and lets it fail.

**Fallback to national.** Everything a unit owns is an override with the national
value underneath. No price row for a sale element in this unit → the national price
stands. No stock row → the shared national counter moves. No order tag → a national
order. The override is the exception, the national value the rule, and absence is
never an error.

## Resolving the current sale unit

On every front request, a chain of resolvers runs by descending priority and the
first non-null answer wins:

| Priority | Resolver | Reads |
|---|---|---|
| 128 | `DomainSaleUnitResolver` | the request host against `sale_unit.url` |
| 64 | `SessionSaleUnitResolver` | the unit id kept in the session |
| 32 | `ApiHeaderSaleUnitResolver` | the `X-Sale-Unit` header (the unit code) |

The domain outranks the session on purpose: a visitor who moves to another shop's
domain must not be served the previous one from a sticky session. Only the unit id
is kept in the session, and the row is read and its `active` flag checked again on
every request, so a unit turned off in the back office stops being served on the
next click.

A project adds its own strategy — a store picker, a geolocation — by registering one
more `SaleUnitResolverInterface` at the priority where it belongs. The back office is
left alone: an administrator works across units and picks one through a selector of
its own, not through the domain used to reach the admin.

## Prices

A unit that sells a sale element at its own price has a row in
`sale_unit_product_price`, aligned column for column on `product_price` (price,
promo price, `from_default_currency`, per currency). The unit price is resolved
first, inside `EffectivePriceResolver`, and it **is** the price: a national catalog
price rule, computed against the national catalog, has nothing to say about a sale
element the unit priced itself. A sale element the unit never priced keeps the
national price and the national rules, untouched.

The unit price feeds the same single point every reader already consults — the
product loops, the front API, the product page and the cart — so the price shown, the
price in the cart and the price ordered are the one price. It does not strike a
catalog price through: a unit price is the price, not a promotion over another.

## Stock

A unit that keeps its own stock of a sale element has a row in
`sale_unit_product_sale_elements`. The atomic conditional decrement of the checkout
runs against that row instead of the shared counter, with the same single UPDATE and
the same guard against overselling, when the order is placed on the unit. A sale
element with no row for the unit decrements the shared national counter.

Stock goes back where it was taken from. Every later movement of an order follows
the unit stamped on it, by the same rule: a cancellation, a paid transition when the
payment module did not take the stock at creation, an `adjust_stock` status action
and a received return move the unit's row when the unit keeps one for the sale
element, the national counter otherwise. `StockDecrementer` carries the rule, and
`StockIncrementer` (returns) goes through it.

The visitor is offered the stock the checkout will decrement. With a unit resolved,
adding to the cart and changing a quantity (`CART_ADDITEM`, `CART_UPDATEITEM`, and
`POST /api/front/cart_items` before them) are judged against the unit's own row when
it has one: a quantity beyond it is refused even when the national counter would
cover it, and a quantity it holds is accepted even when the national counter is
short. The front read of a sale element serves the unit's quantity in `quantity`.
The template readers follow the same rule: the `product_sale_elements` loop serves
the unit's quantity in `QUANTITY` (its price already came from the unit), the `cart`
loop in `STOCK`, and the legacy `thelia_cart_add` form judges the quantity against
it. In the back office the loops keep the national counter, which is what it edits.
`SaleUnitAvailableStock` is the one place that answers, one query for a page of sale
elements; it queries nothing when no unit is resolved.

That shared fallback is a mode of **compatibility**, not a target for a unit with a
stock of its own: it is right for a dematerialised product or a centralised stock,
wrong for a physical store, whose sale elements are each expected to carry a row.

## The cart

A cart carries `cart.sale_unit_id`, the unit it was last priced and stocked for: a
new cart is born on the unit of the request, NULL for a national cart. Each time the
session hands the cart out, it dispatches `TheliaEvents::CART_FOLLOW_SALE_UNIT`, and
when the unit of the request is not the cart's (another domain, a store picker, a
unit turned off), `Action\Cart` checks the cart against the new unit before anyone
reads it, in one transaction:

- a line the unit cannot sell any more (no stock left) is removed;
- a quantity beyond the unit's stock is reduced to what it holds;
- every line takes the unit's price, or the national price back when the visitor
  leaves the units;
- the cart is saved on the new unit, by an update conditioned on the unit it was
  read on: when two requests of the visitor find it on the old unit, the second
  finds it moved and leaves it alone.

Offered lines are left to the discount reconciliation, which runs again afterwards.
`TheliaEvents::CART_SALE_UNIT_SWITCHED` then carries a `CartSaleUnitSwitchedEvent`:
the previous and the new unit and the lines that changed (`getRemovedLines()`,
`getReducedLines()`, `getRepricedLines()`, each a `CartLineChange` with the
reference, the quantities and the untaxed unit prices before and after). When
something changed, the core leaves its summary in the session flash bag, for the
theme to tell the visitor on the next page:

```twig
{% for switch in app.session.flashBag.get('thelia.cart.sale_unit_switch') %}
    {% for line in switch.removed %}{{ line.ref }} is no longer available here.{% endfor %}
    {% for line in switch.reduced %}{{ line.ref }}: {{ line.quantity }} left.{% endfor %}
    {% for line in switch.repriced %}{{ line.ref }} now costs {{ line.price }} (excl. tax).{% endfor %}
{% endfor %}
```

The key is `CartSaleUnitSwitchedEvent::FLASH_KEY`. A cart already on the unit of the
request, and every cart of a single shop, compare two ids in memory and stop there:
no query. The back office never moves a cart.

When a customer signs in and the cart is copied, the copy keeps the unit of the cart
it copies and the lines that unit can sell (its stock read in one query); the session
then moves it if needed, as above.

## Orders

Every order carries `order.sale_unit_id`, stamped by the server from the resolved
context at checkout and NULL for a national order. It is never taken from the
client: `OrderFactory` overwrites whatever the incoming order carries, and the API
property belongs to no write group. The stock decremented at checkout, and given
back later, is the one of that unit; the tag is what lets a shop selling from many
units tell whose order is whose.

## Administrators restricted to sale units

An administrator can be restricted to some sale units, by rows of
`admin_sale_unit`. An administrator with no row reaches every unit and the national
orders, which is every administrator of a shop that never restricts anyone: nothing
changes for it. A superadministrator (no profile) is never restricted, whatever rows
it carries. A shop with no active sale unit restricts nobody, and asks nothing about
its administrators.

For now the restriction applies to the admin API only. The back-office screens
apply it through `AdminSaleUnitScope` (below) in the back-office theme, which
follows on its own.

A national order, placed on no sale unit, belongs to none of the units a restricted
administrator works on: it is out of its reach, and a restricted administrator
cannot create an order through `POST /api/admin/orders`, which would be national.
National orders are handled by the administrators who are not restricted.

Through the admin API, a restricted administrator reaches:

| Resource | What it reaches |
|---|---|
| `/api/admin/orders` | the orders of its units |
| `order_products`, `order_product_taxes`, `order_addresses`, `order_coupons`, `order_shipments`, `order_shipment_items`, `order_returns`, `order_return_lines`, `/api/admin/orders/{id}/history` | those of the orders of its units |
| `/api/admin/carts`, `cart_items` | the carts of its units and their lines |
| `/api/admin/sale_unit_product_prices`, `sale_unit_product_sale_elements` | the prices and stocks of its units |
| `/api/admin/sale_units` | its units; it can neither create nor delete one |

A collection leaves the other rows out and an item answers 404, so an item out of
reach can be neither changed nor deleted, and the transitions of a parcel or a
return of another unit (`/order_shipments/{id}/transition`,
`/order_returns/{id}/transition`) answer 404 too. A write naming another unit, or
an order of another unit, is refused: the IRI resolves to nothing for this
administrator (400), the processors that open a parcel or a return check the order
again (422), and the state about to be written is voted on (`SaleUnitScopeVoter`,
403) before it is persisted. The rights of the profile still apply first: the
restriction narrows what a right reaches, it grants nothing.

A unit some administrator is restricted to cannot be deleted
(`SaleUnitStillAssignedException`, 409 on the API, a `RESTRICT` key in the
database): the administrator would be left with no row, which reads as every unit.
Detach the administrators first, or turn the unit off.

`Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope` is what the back office uses:

```php
// null: not restricted; otherwise the ids of the units the current administrator reaches
$saleUnitIds = $adminSaleUnitScope->allowedSaleUnitIds();

// whether the current administrator reaches what belongs to a unit (null = national)
$adminSaleUnitScope->allows($order->getSaleUnitId());

// narrows any query by the column holding the unit
$adminSaleUnitScope->restrictQuery($orderQuery, OrderTableMap::COL_SALE_UNIT_ID);

// the rows as stored, for the form that edits them
$adminSaleUnitScope->assignedSaleUnitIds($adminId);
```

The attachment is replaced by an event. An empty list lifts the restriction; an id
naming no unit is ignored, and a list naming no unit that exists is refused
(`InvalidArgumentException`) rather than taken for an empty one:

```php
$dispatcher->dispatch(
    new AdministratorSaleUnitsEvent($adminId, [3, 7]),
    TheliaEvents::ADMINISTRATOR_UPDATE_SALE_UNITS,
);
```

## Configuration

A unit sets some of the store settings for itself and gives its own values to the
settings of a module. Like a price, each is an override: a setting the unit does not
set keeps the national value.

**Which unit.** The configuration read is the one of the unit in scope,
`SaleUnitConfigScope`. `SaleUnitContext` keeps it on the unit of the request, and the
back office, a command and a single shop have none: they read the national values.
What is about an order reads the configuration of the unit the order was placed on,
whatever the request resolved, from a command as well: the e-mails `MailerFactory`
sends with an `order_id` or an `order_ref` among their parameters (sender, store
name, shop notification recipients, and the template that reads them) and the PDF
documents of an order (`pdf:render`, the order PDFs of `BaseController`, the return
document). A theme or a module that renders an order document of its own wraps the
render in `SaleUnitConfigService::runForOrder()`.

**Store settings.** `ConfigQuery::read()` answers with the unit's value for the names
of `ConfigQuery::SALE_UNIT_SCOPED_NAMES`: the name and description of the store, its
e-mail and notification recipients, its address, phone and fax, and the identity
printed on its invoices (`store_siret`, `store_vat_intracom`, `store_ape_code`,
`store_eori`, `store_business_id`, `store_registration_exempt`,
`store_legal_mentions`). Every other setting stays national, the country and the VAT
exemption of the store first among them: the taxes, and therefore the prices, depend
on them. The values live in `sale_unit_config`, and `config('store_name')` in a
template reads them like any other caller.

**Module settings.** `BaseModule::getConfigValue()` keeps its signature and answers
for the unit in scope; a module that does nothing gets the unit's values for free.
The values live in `sale_unit_module_config`, one per unit, module and name, the same
in every locale. A row whose value is null sets nothing: the setting reads the
national value, as it does without a row, and the default the caller passes only
applies when the national value is null as well. Code that works for a given unit,
whatever is in scope, names it:

| Call | Does |
|---|---|
| `MyModule::getConfigValueForSaleUnit(?int $saleUnitId, string $name, mixed $default = null, ?string $locale = null): ?string` | the unit's value, or else the national one; a null unit reads the national value |
| `MyModule::setConfigValueForSaleUnit(int $saleUnitId, string $name, mixed $value, ?bool $secret = null): void` | writes the unit's value; `$secret` keeps the flag when null |
| `MyModule::deleteConfigValueForSaleUnit(int $saleUnitId, string $name): void` | drops it, the national value applies again |
| `SaleUnitConfigService::runForOrder(Order $order, callable $callback): mixed` | runs code that reads `getConfigValue()` or `ConfigQuery::read()` deep inside (an SDK wrapper, a mail) with the configuration of the order's unit |
| `SaleUnitConfigService::runFor(?int $saleUnitId, callable $callback): mixed` | the same for a unit, null for the national configuration |
| `SaleUnitConfigService::getStoreValue()`, `setStoreValue()`, `removeStoreValue()`, `getModuleValue()`, `setModuleValue()`, `removeModuleValue()` | the same reads and writes as a service to inject |

`setConfigValue()` still writes the national value. The notification a payment
provider sends about an order comes from its server, with no visitor and no unit
resolved; the module reads its keys for the unit of the order:

```php
use MyPayment\MyPayment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Model\OrderQuery;

final class NotificationController
{
    #[Route('/my-payment/notify', name: 'my_payment.notify', methods: ['POST'], defaults: ['ignore_thelia_view' => true])]
    public function notify(Request $request): Response
    {
        $order = OrderQuery::create()->findOneByRef((string) $request->request->get('order_ref'));

        if (null === $order) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        // The keys of the unit the order was placed on, the national ones for a national order.
        $secretKey = (string) MyPayment::getConfigValueForSaleUnit($order->getSaleUnitId(), 'secret_key');
        $expected = hash_hmac('sha256', $request->getContent(), $secretKey);

        if (!hash_equals($expected, (string) $request->headers->get('X-Signature'))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        // ... mark the order paid: the confirmation mail leaves with the unit's sender.

        return new Response('OK');
    }
}
```

**Admin API.** Both are written through the admin API:

| Resource | Holds | Filters | Right |
|---|---|---|---|
| `/api/admin/sale_unit_configs` | one value per unit and store setting, of the names above only | `saleUnit.id`, `name` | `admin.sale-unit` |
| `/api/admin/sale_unit_module_configs` | one value per unit, module and setting | `saleUnit.id`, `module.id`, `name` | `admin.module`, like the national module settings |

A module value written with `"secret": true` (a key, a password) is never read back:
the API leaves its `value` out, a write that sends no value keeps the stored one, and
turning a secret back into a readable value is refused (422) unless a new value comes
with it. Deleting the row removes it. A second row for the same key is refused with a
422.

**Cost.** Nothing is read for a single shop, nor for a setting no unit can set. A
request of a unit reads that unit's store settings once, at the first scoped setting
asked for, and the settings of a module once per module. A write through the model
(service, API, module) drops what the request had read; a row changed in SQL is seen
by the next request.

## Isolation of the API

The sale units are managed through the admin API (`/api/admin/sale_units`) and read
through the front API (`/api/front/sale_units`), which only ever serves the active
ones, what a store picker lists. The filtering is a query extension keyed on the
operation, the pattern every isolated resource follows.

What a unit owns of the catalog is managed through the admin API too, under the
`admin.sale-unit` right like the units themselves:

| Resource | Holds | Filters |
|---|---|---|
| `/api/admin/sale_unit_product_prices` | one price per unit, sale element and currency | `saleUnit.id`, `productSaleElements.id`, `currency.id` |
| `/api/admin/sale_unit_product_sale_elements` | one stock per unit and sale element | `saleUnit.id`, `productSaleElements.id` |

Both are paginated like every collection (`itemsPerPage`, `page`) and refuse a
second row for the same key with a 422. A row is written with the IRIs of its unit,
sale element and currency.

## A single shop pays nothing

Whether any unit is active is asked of the database once, then kept in the pool of
the shop configuration (`thelia.cache.config.adapter`), shared by every process and
without a lifetime. A single shop never asks again, and every other part of the
feature asks that flag before touching a sale unit table, so a front request of a
single shop runs no query on them
(`SaleUnitSingleShopQueryCountTest`).

Writing or deleting a sale unit through the model (back office, admin API, module)
drops the entry; the request that did it reads the database for its own remainder
and publishes nothing, so a transaction that may still roll back never reaches the
other processes, and the entry is dropped once more when that request ends. A bulk
update issued on the query or a row changed in SQL bypasses the model: run a cache
clear after one.

## Trust

- The domain resolver reads the request host, as sent in `Host` or, behind a proxy
  Symfony trusts, in `X-Forwarded-Host`. A shop selling from several domains must
  declare them in `framework.trusted_hosts` and list only its own proxies in
  `framework.trusted_proxies`; otherwise a visitor chooses the unit, and its prices
  and stock, by forging the header.
- The `X-Sale-Unit` header is open to any caller. It only ever resolves an active
  unit, and the domain and the session outrank it.
- The sale unit of an order is the server's, never the client's (see *Orders*).

## What this covers, and what it does not yet

Shipped: the entity and its resolution, the admin and front API of the units and the
admin API of their prices and stocks, per-unit prices with national fallback,
per-unit stock with atomic decrement, symmetric restock and national fallback, the
unit stock in the cart, in the front read of a sale element, in the template loops,
the legacy add-to-cart form and the copy of a cart, the cart tag and its check when
the visitor changes unit, the order tag, administrators restricted to some units
through the admin API, and the configuration of a unit (store settings and module
settings). Backward compatibility is a condition of every part: with no active sale
unit, nothing changes and no query is added.

Not yet, and deliberately out of this scope: per-unit product visibility, the sales
channel axis, the back-office selector and the back-office screens of the restriction,
per-unit emails and coupons. These are the next slices.

The `product` loop (its `QUANTITY` and `min_stock`) and the `quantity` ordering of
the `product_sale_elements` loop still read the national counter.
