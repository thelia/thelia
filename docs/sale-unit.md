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
`SaleUnitAvailableStock` is the one place that answers; it queries nothing when no
unit is resolved.

That shared fallback is a mode of **compatibility**, not a target for a unit with a
stock of its own: it is right for a dematerialised product or a centralised stock,
wrong for a physical store, whose sale elements are each expected to carry a row.

## Orders

Every order carries `order.sale_unit_id`, stamped by the server from the resolved
context at checkout and NULL for a national order. It is never taken from the
client: `OrderFactory` overwrites whatever the incoming order carries, and the API
property belongs to no write group. The stock decremented at checkout, and given
back later, is the one of that unit; the tag is what lets a shop selling from many
units tell whose order is whose.

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
unit stock in the cart and in the front read of a sale element, and the order tag.
Backward compatibility is a condition of every part: with no active sale unit,
nothing changes and no query is added.

Not yet, and deliberately out of this scope: scoped configuration (per-unit store
name, sender, modules), per-unit product visibility, the sales channel axis, the
back-office selector and per-admin restriction, per-unit emails and coupons, and the
cart tag (the durable tag is on the order). These are the next slices.

The stock of a unit is not read yet by the template loops (`product_sale_elements`,
`cart`), by the legacy `thelia_cart_add` form, nor when a cart is copied to a
customer at login: they judge on the national counter, and the checkout still
refuses what the unit cannot sell.
