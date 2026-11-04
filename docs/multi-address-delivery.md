# Multi-address delivery: decision record

This document studies how an order can be delivered to several addresses with one payment and one invoice. It compares three shapes, recommends one (an order that carries several destinations, each shipped in one or more parcels), and lists the tax, shipping, module, security and upgrade consequences with a delivery plan. It decides what to build if the demand is proven. No code changes with this document.

## Status

Proposed (study, no code change).

## Context

Some orders are bought for more than one place. Three cases come up in shops:

- Gifts: one buyer pays once and sends several parcels to several recipients.
- Multi-site B2B: a company orders for its branches and wants one invoice at head office.
- Birth and wedding lists: the buyer of the list pays, the goods go to the address of the person who gets them.

Today an order has one delivery address and one carrier. A buyer who needs two destinations has to place two orders, pay twice and receive two invoices.

How other platforms handle it. Not verified for this study: the lines below summarize how these products are usually described and were not checked against their documentation or a running release. They inform the comparison and decide nothing.

| Platform | What it is described as offering (not verified) |
|---|---|
| Magento | A checkout path to ship one cart to several addresses, one carrier and one rate per address. |
| Shopware | An order can hold several deliveries, each with its own address. The storefront has no screen to build them. |
| OroCommerce | Splits an order into sub-orders. Each sub-order still has one address. |
| PrestaShop | Offered a multi-shipping option in 1.6 and removed it in 1.7. |
| WooCommerce | No core feature. Paid extensions exist. |

If these descriptions hold, the pattern is the same across these products: where the feature exists, the order keeps one payment and one invoice and carries several deliveries.

## Current state

The facts below were read on the `main` branch of thelia/thelia (`2a874f020`). Theme files were read on the `main` branch of their own repositories and are cited with the path they have in a shop:

- `templates/frontOffice/flexy`: `eb90b84`
- `templates/backOffice/default-twig`: `6348a66`

Note on the PDF and e-mail templates: `templates/pdf/default/` and `templates/email/default/` are installed copies, not tracked by the monorepo. Their line numbers are those of the templates as published on the date of this document (PDF template repository `2aea8ef43`, after release 1.4.1; e-mail template 1.3.1, `5362f992d`). A shop that installed an earlier PDF release (1.3.x) has other line numbers, and its delivery note cannot print one parcel.

### Schema

- `order` has one `delivery_order_address_id`, `required="true"` (`local/config/schema.xml:786`) with a foreign key to `order_address`, ON DELETE RESTRICT (`:841`) and an index (`:868`). The generated SQL repeats it: `NOT NULL` at `setup/thelia.sql:900`, constraint at `:974`.
- Everything about the delivery is one value per order: `delivery_ref` (`:791`), `postage` and `postage_tax` (`:795-796`), `postage_tax_rule_title` (`:797`), `delivery_module_id` and `delivery_module_title` (`:800-801`), `cart_id` (`:804`), `cart_fingerprint` (`:809`), `gift_message` (`:813`), `delivery_date` and the delivery slot columns (`:818-821`).
- `order_address` has no `order_id` and no delivery or invoice flag. An address belongs to an order only through the two columns of `order` (`:923-963`). It carries `country_id`, required (`:942`), and the VAT exemption (`:931-932`).
- `order_product` has no address and no country (`:964-996`). `order_product_tax` already freezes the tax of each line (`:2259-2273`).
- `order_postage_tax` splits the postage by tax rule, per order, with no address dimension (`:2274-2288`).
- `order_payment_part.order_id` is required, with a foreign key to `order` ON DELETE CASCADE (`:3482-3494`).
- `order.invoice_ref` is unique (`:792`, `:877-879`).
- `cart` also has a single `address_delivery_id`, a single `delivery_module_id` and a single postage (`:1715`, `:1719`, `:1725-1727`). The delivery address is a foreign key to `cart_address`, ON DELETE SET NULL (`:1742-1743`). Cart lines have no address.

### Order creation

- `OrderFacade::createOrder()` runs one transaction per order (`core/lib/Thelia/Domain/Order/OrderFacade.php:147`, commit at `:336`).
- `OrderAddressPersister::prepareOrderAddresses()` reads the two cart address ids (`core/lib/Thelia/Domain/Order/Service/OrderAddressPersister.php:41-42`), freezes them (`:44-48`) and returns the country of the delivery address (`:50`). For a manual back-office order it reads the country from the order instead (`:35-38`).
- The delivery country is one value per order. It decides the tax of every line, of the gift wrapping and of the postage, and the price of a cart line already follows the delivery address of the cart. Details and citations: [How the tax country is chosen today](#how-the-tax-country-is-chosen-today).
- `CartGuard::checkDeliveryAddress()` refuses a cart without a delivery address (`core/lib/Thelia/Domain/Cart/Service/CartGuard.php:126-135`, called at `:186`).
- `CheckoutPaymentService` builds the session order with `setDeliveryOrderAddressId($request->deliveryAddressId)` (`core/lib/Thelia/Domain/Checkout/Service/CheckoutPaymentService.php:164`). The request field is a required `int` (`core/lib/Thelia/Domain/Checkout/DTO/OrderPaymentRequest.php:41`).
- A pickup-point module may rewrite the delivery address of the order after creation. The code says so (`CheckoutPaymentService.php:173-175`, `local/config/schema.xml:805-808`). How this is handled per destination: [Delivery modules](#delivery-modules).
- `Action/Order.php:153` and `:193` are the two callers of `createOrder()` (back office and front). The express checkout places the cart through `CheckoutPlacementService` with the one carrier and the addresses the checkout wrote on the cart (`core/lib/Thelia/Domain/Checkout/Service/ExpressCheckoutPlacementService.php:60-78`, `:95-100`), and writes a delivery address itself only for a cart with nothing to ship (`:69`, `:110-117`).

### Parcels after the order

- `order_shipment` is a parcel the merchant composes after the order is placed: a draft, then shipped or cancelled (`local/config/schema.xml:1126`), with a carrier module and its title (`:1127-1128`), a tracking number and a shipping date (`:1129-1130`).
- `order_shipment_item` carries part of an order line: "how much of the order line this shipment carries" (`:1152`). Uniqueness is per parcel and line only (`:1165-1168`), so one line can already be split between parcels.
- `OrderShipmentManager::open()` defaults the carrier of a new parcel to the carrier of the order (`core/lib/Thelia/Domain/Shipping/Service/OrderShipmentManager.php:70-90`, default at `:81`). `cancel()` gives up a draft and puts its lines back in what is left to ship (`:185`). `shipEverythingLeft()` ships the drafts, then opens one parcel for everything left on the order (`:226-247`). The order carries the tracking number of its first parcel that was not cancelled (`:163-169`, `:283-288`).
- `ORDER_SHIPMENT_CREATE`, `_SHIP` and `_CANCEL` are dispatched by the manager (`core/lib/Thelia/Core/Event/TheliaEvents.php:371-373`).
- No parcel has an address, a postage or a tax. Every parcel of an order goes to `order.delivery_order_address_id`.

### Storefront (Flexy)

- The delivery step keeps one `deliveryAddressId` (`templates/frontOffice/flexy/components/Organisms/Delivery/Base.php:67`), reads it from the cart (`:114`) and sets it with `selectDeliveryAddress`, which first checks that the address is visible to the buyer, guest included (`:348-360`, check at `:351`).
- `templates/frontOffice/flexy/src/Controller/CheckoutController.php:354` passes that one id to the payment request. `templates/frontOffice/flexy/src/Event/CheckoutEvents.php:51` and `templates/frontOffice/flexy/components/Organisms/CheckoutOnePage/Base.php:82` carry the choice between components.
- The next button is disabled while the cart has no delivery address (`templates/frontOffice/flexy/components/Organisms/NextButton/Base.php:228`).
- Confirmation, account and guest pages render one address card from `deliveryOrderAddress.id` (`templates/frontOffice/flexy/checkout-confirm.html.twig:62`, `templates/frontOffice/flexy/account-order.html.twig:113`, `templates/frontOffice/flexy/guest-order.html.twig:58`). `templates/frontOffice/flexy/src/Service/GuestOrderView.php:60` builds the same single entry.
- `templates/frontOffice/flexy/account-order.html.twig:33-39` already lists several parcels with a delivery note each. The cards have no address.

### Documents and e-mails

- The invoice prints the delivery address, with a hook override that receives the order carrier (`templates/pdf/default/invoice.html.twig:201-202`). So does the delivery note (`templates/pdf/default/delivery.html.twig:205-206`).
- The delivery note can already print one parcel (`shipment_id`, `:62-64`, `:150-153`).
- The credit note prints the invoice address only (`templates/pdf/default/credit-note.html.twig:238`).
- `order_confirmation` and `order_notification` print `order.DELIVERY_ADDRESS` (`templates/email/default/order_confirmation.html.twig:33`, `.txt.twig:8`, `templates/email/default/order_notification.html.twig:37`, `.txt.twig:8`).

### API

- `Order` exposes `invoiceOrderAddress` and `deliveryOrderAddress` as non-null properties, required on admin write (`core/lib/Thelia/Api/Resource/Order.php:303-313`). Front ownership is checked on the order (`:82`; guest orders `:98`).
- `OrderAddress` has admin operations only (`core/lib/Thelia/Api/Resource/OrderAddress.php:33-40`). A customer reads an address only through the order.
- `OrderShipment` has admin and front operations (`core/lib/Thelia/Api/Resource/OrderShipment.php:45-50`, `:76-81`) and no address field.
- Sale-unit scoping lists both address columns of `order` in a closed list (`core/lib/Thelia/Api/Bridge/Propel/Extension/AdminSaleUnitScopeExtension.php:84`).

### Back office

- The order page shows one invoice card and one delivery card, with a module hook on the delivery card that receives the order carrier (`templates/backOffice/default-twig/order/detail.html.twig:382`, `:392`, `:398`; edit modal `:689`). `OrderController::orderAddresses()` returns one of each (`templates/backOffice/default-twig/src/Controller/Order/OrderController.php:598-606`).
- Editing an address checks that it is one of the two columns (`templates/backOffice/default-twig/src/Controller/Order/OrderController.php:425-426`).
- The parcel tab lists parcels and the lines left to ship, with no address per parcel (`templates/backOffice/default-twig/order/_shipments_tab.html.twig:7-10`).
- Lists and filters read the delivery country and the carrier of the order: `templates/backOffice/default-twig/src/Service/Order/OrderListRowPresenter.php:205` (null-safe), `templates/backOffice/default-twig/src/Service/Order/OrderFilters.php:306-313` (carrier and country filters), `templates/backOffice/default-twig/src/Repository/OrderRepository.php:103` and `:666-673` (countries offered in the filter), `templates/backOffice/default-twig/src/Repository/OrderReturnRepository.php:324`.
- `templates/backOffice/default-twig/src/Service/OrderReturn/OrderReturnDetailPresenter.php:210-211` reads the country without a null check, on the ground that the column is required.

### Readers outside the checkout

Pattern searched in the monorepo and the four template repositories: `getDeliveryOrderAddress`, `DeliveryOrderAddressId`, `delivery_order_address_id`, `delivery_order_address`, `deliveryOrderAddress`, `DELIVERY_ADDRESS`. Lines found, tests included:

| Place | Lines |
|---|---|
| `core/lib` | 39 in 19 files, 1 line in `Test/FixtureFactory.php` |
| `setup` | 8 |
| `tests` | 37 |
| `templates/frontOffice/flexy` | 16 |
| `templates/backOffice/default-twig` | 11 |
| `templates/pdf` | 2 |
| `templates/email` | 4 |
| `local/config/schema.xml` | 5 |
| `local/modules`, `vendor/thelia/modules`, `src` | 0 |

The carrier of the order is read even more widely: `getDeliveryModuleId()` appears on 44 lines in 28 files of `core/lib` (generated `Base` and `Map` classes excluded), cart and order together. The readers that act after the order is placed are listed in [After the order is placed](#after-the-order-is-placed).

Production readers in the core that assume one address per order. What each one needs under option 1 is in [Other consumers](#other-consumers) and [Security and personal data](#security-and-personal-data).

- Order export: `core/lib/Thelia/Domain/DataTransfer/Export/Type/OrderExport.php:149-153` joins `order_product`, the carrier of the order (`:150`) and the delivery address, so the export has one row per line with the order address and the order carrier.
- Personal data export: `core/lib/Thelia/Domain/Customer/Service/CustomerPersonalDataExporter.php:201`.
- Anonymisation collects the two address ids of each order and nothing else (`core/lib/Thelia/Domain/Customer/Service/CustomerAnonymizer.php:135-139`). An address stored anywhere else would be missed.
- Order history types an address by comparing it with the two columns, and returns null for any other (`core/lib/Thelia/Domain/Order/EventListener/RecordOrderHistoryListener.php:276-283`).
- Loop `order`: search on the delivery or invoice address (`core/lib/Thelia/Core/Template/Loop/Order.php:168-181`), joins (`:290`, `:296`) and the `DELIVERY_ADDRESS` output (`:375`).
- Import and fixtures: `core/lib/Thelia/Command/Import/Importer/OrdersImporter.php:229`, `core/lib/Thelia/Test/FixtureFactory.php:1083`.
- Statuses read no address (searched `Address` in `Domain/Order/Service/OrderStatus*`: no match).
- Statistics: no reader of the delivery country or the carrier was found. Searched `delivery_order_address`, `DeliveryOrderAddress`, `delivery_module`, `DeliveryModule`, `country` and `postage` in the files of `core/lib` and of `templates/backOffice/default-twig/src` that mention `statist` or `stats`. The sales statistics read only the sum of `order.postage` and `order.postage_tax` (`core/lib/Thelia/Model/OrderQuery.php:139-148`); the other matches are the order list query of `templates/backOffice/default-twig/src/Repository/OrderRepository.php:103-110`, already listed above, configuration names and `BaseModule.php`, which are not statistics. The back-office dashboard (`templates/backOffice/default-twig/src/Service/Dashboard/DashboardStatsProvider.php`) reads none of them.

Not read: how external pickup-point modules rewrite the address, since none is in the repository.

### What can be reused

- `order_shipment` and `order_shipment_item` stay what they are: parcels composed, shipped and tracked by the merchant ([Parcels after the order](#parcels-after-the-order)). Option 1 attaches each parcel to a destination; it does not turn the parcel into the destination.
- `ShippingCartScope` (`core/lib/Thelia/Domain/Shipping/ProductRule/ShippingCartScope.php:28-55`) already lets the core quote a carrier on a reduced cart. See [Quoting one destination](#quoting-one-destination).

## Options

Three shapes can carry "one checkout, several delivery addresses, one invoice". They differ in what an order is.

### Option 1: one order, several destinations

The order keeps its lines, its payment and its invoice. It gains a list of destinations. A destination is a new entity, called `order_delivery` here (indicative name), frozen at checkout:

- the frozen delivery address (an `order_address` row);
- the delivery module and its title;
- the postage, the postage tax and the postage tax rule title, with its own postage tax breakdown;
- the VAT exemption of that destination (see [What changes with several destinations](#what-changes-with-several-destinations));
- the gift message for its recipient. The requested delivery date and slot stay on the order and are offered only to an order with one destination in the first version ([Open questions](#open-questions)).

Each order line belongs to exactly one destination, which keeps one tax country per line. Parcels (`order_shipment`) stay the merchant's shipping gesture and each parcel belongs to one destination. The invariant is: the lines of a parcel are lines of its destination. A line can still be split between several parcels of the same destination (`order_shipment_item.quantity`), never between two destinations.

Consequences on the parcel code ([Parcels after the order](#parcels-after-the-order)):

- `open()` takes the carrier of the destination of its lines, not of the order, and refuses lines of two destinations.
- `shipEverythingLeft()` works per destination: for each destination, it ships its drafts and opens one parcel for what is left of that destination, with the destination carrier. No parcel ever mixes destinations, and no parcel falls back to the order address.
- `cancel()` cancels a parcel only. The address, carrier, postage and tax live on the destination and are not lost.
- A destination shipped in two cartons has two parcels and one postage, the one of the destination.

What the buyer sees:

- In the checkout, the buyer assigns each cart line to one of their addresses, then picks a carrier per destination. Each destination shows its own postage.
- One payment for the whole order.
- One confirmation e-mail, listing each destination with its address, carrier and lines.
- In the account, one order. The order page already lists parcels as cards, with a delivery note per parcel; the cards are grouped by destination and show its address.

What the merchant sees:

- One order in the list, one order status, one reference.
- The parcels tab groups the lines left to ship by destination. Each destination is shipped in one or more parcels, each tracked on its own.
- The order columns that describe one delivery hold the values of the main destination, defined below, so every screen that shows one address or one carrier keeps working for single-destination orders and shows the main destination of the others.

What the invoice carries:

- One invoice reference and one invoice address.
- Lines grouped by destination, each group with its address.
- One postage line per destination, and a tax breakdown by rate and country.

#### Source of truth

The destinations are the source of truth. The delivery columns of `order` are derived, maintained by one core service on every write to a destination (checkout, back-office edit, pickup rewrite), and documented as derived:

| `order` column | Value |
|---|---|
| `delivery_order_address_id`, `delivery_module_id`, `delivery_module_title`, `postage_tax_rule_title` | Those of the main destination |
| `postage`, `postage_tax` | Sums over the destinations, because `Order::getTotalAmount()` adds `order.postage` to the total and `order.postage_tax` to the tax it returns (`core/lib/Thelia/Model/Order.php:276-277`) |
| `delivery_ref` | Tracking number of the first parcel of the main destination, as today for the first parcel of the order |
| `gift_message` | That of the main destination |

The main destination is the destination with the smallest id. Destinations are created in checkout order and never deleted while they carry lines, so the main destination is stable. It is never a parcel: cancelling a parcel does not move it, unlike the first-parcel rule of `OrderShipmentManager` (`:283-288`).

### Option 2: a parent order and one child order per address

The checkout produces a parent order that holds the payment, and one child order per address that holds the lines, the carrier and the postage of that destination.

What the buyer sees:

- The same checkout as option 1.
- One payment, on the parent.
- Either one confirmation for the parent or one per child; both mean new templates.
- In the account, a parent and its children. Either the account learns to nest them, or the buyer sees several orders for one purchase.

What the merchant sees:

- A parent with no parcel of its own, and children that ship like ordinary orders.
- Two layers of status: each child has its own, and the parent's status has to be derived from them (paid when the parent is paid, sent when every child is sent).
- Lists, exports and statistics must skip either the parents or the children, or they count the sale twice.

What the invoice carries:

- Either the parent is invoiced and the children are not, which means suppressing the invoice reference the core allocates to every paid order;
- or each child is invoiced, which gives several invoices for one purchase.

### Option 3: several independent orders, one payment

The checkout produces one complete order per address and charges them together. This is how Magento's multiple-address checkout is described (not verified, see [Context](#context)).

What the buyer sees:

- The same checkout as option 1.
- One payment covering several orders.
- One confirmation per order, each with its own reference.
- In the account, several unrelated orders. A return or a cancellation is made order by order.

What the merchant sees:

- Several orders, each with its own status, carrier and tracking.
- Nothing in an order tells that it was paid with others, unless a grouping key is added.
- Cancelling one order of the group means a partial refund of a payment shared with orders that stay.

What the invoice carries:

- One invoice per order, each with its own reference, lines, postage and tax.
- There is no single invoice. This contradicts the requirement.

## Comparison

| Criterion | 1. One order, N destinations | 2. Parent + children | 3. N orders, one payment |
|---|---|---|---|
| Schema change | New destination table; destination link on order lines, parcels and postage tax; planned destinations on the cart | Parent link on `order` | Grouping table; payment linked to N orders |
| Order creation | One order per cart, as today | One cart gives N orders, refused by the core today (`local/config/schema.xml:804`, `core/lib/Thelia/Domain/Order/OrderFacade.php:150`, `:431-444`) | Same as 2 |
| Delivery modules | No signature change; quoted per destination on a scoped sub-cart; opt-in per module ([Delivery modules](#delivery-modules)) | Same as 1 | Same as 1 |
| Payment modules | No change | Pay the parent; children carry no payment | New multi-order contract |
| Invoice | One reference, unchanged allocation | One or N, invoice allocation must be bypassed | N invoices |
| Tax | Tax country per line instead of per order; exemption per destination | One country per child, unchanged | One country per order, unchanged |
| Order statuses | Unchanged; parcel status already exists | Parent status derived from children | N independent statuses |
| Documents (PDF, e-mails) | Address per destination on invoice and e-mails; delivery note already per parcel | New parent documents, or N sets | N sets, unchanged templates |
| API | Destinations readable on `Order`; destination on `OrderShipment`; `Order.deliveryOrderAddress` becomes the main destination (declared change) | Parent/child relation on `Order` | New group resource |
| Back-office | Destinations and their parcels in the parcels tab; list filters on every destination | Parent/child display, list filtering | Grouping display, partial refunds |
| Upgrade of an existing shop | New table and nullable columns; back-fill of one destination per existing order | Every reader of `order` must skip parents | Grouping key on existing orders not needed |

Notes (code read on `main`; the order and parcel structure is in [Current state](#current-state)):

- One invoice reference per order, unique, allocated when the order turns paid (`local/config/schema.xml:792`, `:877-879`; see [One invoice per order](#one-invoice-per-order)).
- A payment belongs to one order (`order_payment_part.order_id` NOT NULL, `local/config/schema.xml:3482-3494`). The amount is composed from one order total (`core/lib/Thelia/Domain/Payment/OrderPaymentComposer.php:76-78`). 17 payment modules declare Thelia 3 on Packagist. `BC-PROMISE.md:10-12` forbids adding a required parameter to a public method in a minor release. The current contract is in [Payment modules](#payment-modules).
- Order creation: an order carries a NOT NULL `cart_id` (`local/config/schema.xml:804`), and `createOrder()` refuses a cart that already has a live order (`core/lib/Thelia/Domain/Order/OrderFacade.php:150`, `:431-444`). Options 2 and 3 create several orders from one cart.

Why options 2 and 3 are set aside:

- Both produce several orders, so several invoice references. The single invoice the requirement asks for would need the reference allocation to be bypassed or split.
- A payment part belongs to one order. Option 3 needs a payment contract over several orders, which the 17 Thelia 3 payment modules do not implement and which a minor release cannot impose.
- Option 2 doubles every order reader (lists, exports, statistics, account) and adds a status layer.
- Option 1 changes the tax step and the documents, but keeps one order, one invoice and one payment, and keeps a parcel a parcel, now scoped to one destination.

## Alternatives

SubOrderGenerator is sometimes cited as a way to split an order. The code says otherwise. `SubOrderService::createSubOrderFromParent()` copies the whole parent order: every line, tax and attribute, the same delivery and invoice addresses, the same discount (`Service/SubOrderService.php:67-85`, `:87-116`). The child order gets a token, and the buyer pays it through an e-mailed link. Its purpose is partial or deferred payment of an existing order. It splits neither lines nor addresses. The published version is 2.0.4, declared for Thelia 2.5 (`Config/module.xml:16`, `:24`), with no Thelia 3 release. It relies on the Thelia 2 OpenApi module and on Smarty back-office templates, which Thelia 3 does not ship. Source: https://github.com/thelia-modules/SubOrderGenerator (branch `main`).

The real alternatives to a core change are these.

### (a) Do nothing

The buyer places one order per address. Each order has its own payment, invoice, carrier and postage, and the code paths are those every shop runs today.

Prefer it when:

- few projects ask for the feature;
- buyers are mostly consumers sending an occasional gift, for whom a second checkout is acceptable;
- nobody is ready to maintain a new checkout path in the core.

Cost: several payments and several invoices for the buyer. No maintenance.

### (b) Option 3 as a third-party module

A module splits the cart into one order per address and charges them together, without touching the core. It owns its grouping table and its documents.

Prefer it when:

- a few projects need the feature now, and can accept one invoice per order;
- the buyers are businesses that order for several sites and already reconcile several invoices;
- the shop uses one payment provider the module can drive for several orders.

Limits: the module must work around the one-cart-one-order guard and the one-order payment contract, so it is tied to internals the backward compatibility promise does not cover. It cannot deliver a single invoice. Its maintenance stays with its authors.

### (c) Port SubOrderGenerator for what it does

A Thelia 3 version of the module would serve partial payment by tokenized link: a deposit, a balance, or a payment by a third party. It is unrelated to delivery addresses.

Prefer it when:

- the need behind the request turns out to be paying an order in several times, or by someone else;
- a project already used the Thelia 2 module and migrates to Thelia 3.

Cost: a rewrite of the API and back-office parts, plus a review of its checkout detour against the Thelia 3 payment parts, which already split one order's payment (`local/config/schema.xml:3482-3494`).

### Choosing

- No measured demand: (a).
- Demand from business buyers who accept several invoices, and no appetite for core maintenance: (b).
- The need is a split payment, not a split delivery: (c).
- Repeated demand for one invoice over several destinations: option 1 in the core.

## Invoicing and tax

### One invoice per order

An invoice reference belongs to an order. `order.invoice_ref` is a single column with a unique index (`local/config/schema.xml:792`, `:877`). `InvoiceRefAllocator::allocate()` numbers it from a gapless yearly sequence and locks the `order` row with `SELECT ... FOR UPDATE` to serialize concurrent allocations (`core/lib/Thelia/Domain/Invoice/InvoiceRefAllocator.php:58`, `:98`). `AllocateInvoiceRefListener` runs it once, when `ORDER_UPDATE_STATUS` moves the order to a paid status and no reference exists yet (`core/lib/Thelia/Domain/Invoice/EventListener/AllocateInvoiceRefListener.php:53-70`).

Option 1 keeps this unchanged: one order, one paid status, one reference, one invoice. The destinations are sections of that invoice, not documents of their own.

### How the tax country is chosen today

The tax country is the country of the delivery address, never the billing one, and it is one country per order.

- Before the order exists, `TaxEngine::getDeliveryCountry()` returns the country of the delivery address of the session cart, then the default address of the customer, then the visitor country or the shop default (`core/lib/Thelia/Domain/Taxation/TaxEngine/TaxEngine.php:51-89`). It reads the cart delivery address on every call (`:54-58`) and keeps the last answer on the service.
- At order creation, `OrderAddressPersister::prepareOrderAddresses()` returns the country of the cart delivery address (`core/lib/Thelia/Domain/Order/Service/OrderAddressPersister.php:50`). `OrderFacade::createOrder()` stores it as `$taxCountry` and uses it for every order line (`core/lib/Thelia/Domain/Order/OrderFacade.php:165`, `:258-264`), for the gift wrapping line (`:304-307`), for the postage tax breakdown (`:187`, `:547-559`) and for the VAT an exempted order would have carried (`:184-185`).
- The postage tax breakdown is built from all the lines of the cart with a single country and state (`core/lib/Thelia/Domain/Shipping/Service/PostageTaxBreakdownCalculator.php:181-210`). Its result goes to `order_postage_tax`, which is attached to `order_id` (`local/config/schema.xml:2274-2287`).
- Cart totals follow the same rule: `Cart::getTaxedAmount()` takes one `Country` (`core/lib/Thelia/Model/Cart.php:299-306`), and so does the total a payment module reads before the order exists (`BaseModule::getCurrentOrderTotalAmount()`, `core/lib/Thelia/Module/BaseModule.php:495-509`).

The billing address matters only for the reverse-charge exemption. `VatExemptionResolver` reads the invoice address and compares it with the shop country (`core/lib/Thelia/Domain/Taxation/Service/VatExemptionResolver.php:78`). The result is one `vatExempted` flag for the whole order (`core/lib/Thelia/Domain/Order/OrderFacade.php:163`), frozen on the invoice address with the VAT it waived (`local/config/schema.xml:931-932`).

### What changes with several destinations

- The tax country of an order line is the country of its destination. `OrderFacade` stops using one `$taxCountry` and resolves one per destination.
- Postage is taxed per destination, with the country and state of that destination. A module that builds its quote with `buildOrderPostage($untaxed, $country, ...)` already taxes in the country it is given (`core/lib/Thelia/Module/OrderPostageBuilderTrait.php:50`, `:65`), so the destination country flows through. The state is not passed to that helper today (`:65`).
- The breakdown strategies `pro_rata` and `highest_rate` run on the lines of the destination, not on the whole cart (`PostageTaxBreakdownCalculator.php:186`). The default `single_rule` returns no breakdown (`:130-132`), so the postage and tax amounts of each destination are stored on the destination itself, and `order_postage_tax` rows get a destination reference.
- `order.postage` and `order.postage_tax` are the sums over the destinations ([Source of truth](#source-of-truth)).
- The reverse-charge exemption is resolved per destination. An intra-community supply is exempt only when the goods leave the seller's member state: a business buyer with a valid VAT number who has one destination in the shop country owes the VAT of that country on it. Proposal, to be confirmed by a chartered accountant: a destination is exempted when the invoice address carries a verified VAT number and the destination country is another member state than the shop's. The exemption flag and the waived VAT are frozen per destination; the columns of the invoice address keep an aggregate (flag set when at least one destination is exempted, sum of the waived VAT), documented as derived.
- Totals read before the order exists (cart total, total given to a payment module) are computed per destination, then added.

### Worked example

Shop in France, private customer, no exemption. Destination A is Lyon (FR), destination B is Berlin (DE). Amounts in euros.

| | Destination A (FR, 20 %) | Destination B (DE, 19 %) | Invoice |
|---|---|---|---|
| Goods excl. tax | 100.00 | 50.00 | 150.00 |
| Goods tax | 20.00 | 9.50 | 29.50 |
| Postage excl. tax | 6.00 | 12.00 | 18.00 |
| Postage tax | 1.20 | 2.28 | 3.48 |
| Total excl. tax | 106.00 | 62.00 | 168.00 |
| Total tax | 21.20 | 11.78 | 32.98 |
| Total incl. tax | 127.20 | 73.78 | 200.98 |

Tax by rate on the invoice: FR 20 % is 21.20 (20.00 goods, 1.20 postage), DE 19 % is 11.78 (9.50 goods, 2.28 postage). `order.postage` holds the tax-inclusive postage, 7.20 + 14.28 = 21.48, and `order.postage_tax` holds 3.48.

Business variant: the same order placed by a German company with a verified VAT number, for its Lyon and Berlin sites. Destination A stays in France and carries French VAT: 127.20, as above. Destination B leaves France for another member state and is exempted: 62.00 with no tax, and 11.78 recorded as waived VAT. The invoice totals 168.00 excluding tax, 21.20 of tax, 189.20 including tax.

The example has no discount. An order-level discount has to be split between destinations, and so between tax rates and countries, before the tax is computed. Proposal: split it pro rata of the goods excluding tax of each destination. To be confirmed by the chartered accountant ([Open questions](#open-questions)).

Rounding is done per destination and per quote, as it is today for one postage. Summing rounded destination amounts can differ by a cent from rounding the total once. The rule must be fixed and documented before the build; see [Open questions](#open-questions).

### Assumption and open question

Assumption: the shop sells to consumers in other member states above the EU distance-selling threshold of 10,000 euros, so VAT is due at the destination rate. The consumer example above relies on it. Below the threshold the origin rate could apply to the whole order, and a shop in that situation would not want the per-destination country.

Whether one invoice may carry VAT of two member states, which mentions it must show, and how a business order mixing an exempted and a taxed destination is presented, have not been validated. They are open questions for a chartered accountant ([Open questions](#open-questions)). The project does not state a tax rule as settled.

### Prices shown before the buyer splits the lines

Displayed prices depend on a country. The catalog loops, the cart loop and the API cart normalizers all take it from `TaxEngine::getDeliveryCountry()` or from the cart delivery address (`core/lib/Thelia/Core/Template/Loop/Product.php:266`, `core/lib/Thelia/Core/Template/Loop/Cart.php:91`, `core/lib/Thelia/Api/Bridge/Propel/Serializer/CartNormalizer.php:118-124`, `core/lib/Thelia/Api/Bridge/Propel/Serializer/CartItemNormalizer.php:96-98`).

Proposal, to be confirmed: until the buyer has split the lines, the country in use is the one used today, the delivery address of the cart if there is one, otherwise the default address of the customer, otherwise the visitor or shop country. That address is also the main destination of an order that stays single. Prices excluding tax do not move. A tax-inclusive price can change when a line is assigned to a destination in another country, and the checkout must say so. Once lines are assigned, each line and the cart total follow the country of its destination.

## Shipping costs and free-shipping threshold

### The contract the modules implement

A delivery module answers two questions: `isValidDelivery(Country)` and `getPostage(Country)`, or `isValidDelivery(Country, ?State)` and `getPostage(Country, ?State)` for a module that handles states. The interfaces do not receive a cart, a weight or an address (`core/lib/Thelia/Module/DeliveryModuleInterface.php:32`, `:43`, `core/lib/Thelia/Module/DeliveryModuleWithStateInterface.php:33`, `:44`). The core calls them through `Action\Delivery::getPostage()` with the country and state of the event (`core/lib/Thelia/Action/Delivery.php:60-72`). The event also carries a cart and an address (`core/lib/Thelia/Core/Event/Delivery/DeliveryPostageEvent.php`), which a module can read if it listens to `MODULE_DELIVERY_GET_POSTAGE` for its own code (`core/lib/Thelia/Action/Delivery.php:50`).

The two delivery modules shipped with the core read the cart from the session. `CustomDelivery` calls `getSessionCart()` in both methods and reads weight, total and currency from it (`vendor/thelia/modules/CustomDelivery/CustomDelivery.php:327`, `:346`, `:397`, `:437`, `:442`). `VirtualProductDelivery` reads `isVirtual()` from it (`vendor/thelia/modules/VirtualProductDelivery/VirtualProductDelivery.php:51`). The other modules are not in the repository; they were read at their published tag, see [Delivery modules](#delivery-modules).

### Quoting one destination

`ShippingCartScope` makes `Session::getSessionCart()` return a substitute cart while a callback runs (`core/lib/Thelia/Domain/Shipping/ProductRule/ShippingCartScope.php:39-54`, `core/lib/Thelia/Core/HttpFoundation/Session/Session.php:334`). `ProductShippingRuleQuoter` already uses it to quote an unsaved copy of the cart without the free lines (`core/lib/Thelia/Domain/Shipping/ProductRule/ProductShippingRuleQuoter.php:146-156`). No module signature changes for that.

The proposal reuses it. For each destination the core builds a sub-cart with the lines of the destination, opens the scope, and passes the country and state of the destination. A module that reads the session cart sees only the destination, with no change on its side.

The sub-cart has to carry the destination address. Today the quoter's copy is an unsaved `copy()` of the cart, with no id, that keeps the cart's own delivery address (`ProductShippingRuleQuoter.php:164-175`). Anything that reads the address of the session cart under the scope would see the address of the whole cart, not the destination: `TaxEngine::getDeliveryCountry()` (`core/lib/Thelia/Domain/Taxation/TaxEngine/TaxEngine.php:54-58`), the free-shipping coupon check (`core/lib/Thelia/Domain/Promotion/Coupon/Service/CouponManager.php:376`), or a module that reads a postal code or a zone on the cart. So the sub-cart points to the `cart_address` of its destination.

Which modules can be quoted that way, and the optional interface a module declares, are in [Delivery modules](#delivery-modules). Existing modules keep working and keep their behavior.

### Computing and adding

Postage is computed per destination, then added.

1. For each destination and each active module, the core asks the module through the scope and applies the product shipping rules to the sub-cart (`ProductShippingRuleQuoter::quote()`, `core/lib/Thelia/Domain/Shipping/ProductRule/ProductShippingRuleQuoter.php:50`).
2. The postage tax breakdown is applied to the destination (`core/lib/Thelia/Domain/Shipping/Service/PostageEstimator.php:220`).
3. The cheapest eligible module is the best estimate for a destination, as it is for a cart today (`core/lib/Thelia/Domain/Shipping/Service/PostageEstimator.php:120`). In the checkout the buyer picks one module per destination.
4. The order postage is the sum, the tax is the sum.

Today a cart holds one `delivery_module_id`, one `postage` and one `postage_tax` (`local/config/schema.xml:1725-1727`). In option 1 each planned destination of the cart holds them, and the order destination freezes them ([Option 1](#option-1-one-order-several-destinations)).

### Free-shipping threshold

The core has no threshold. Searching `core/lib/Thelia` for `shipping_from`, `franco`, `free_postage`, `shipping_threshold` and `free_shipping_from` in PHP files, and the same words in `schema.xml`, returns nothing. Free shipping comes from four places.

- Module slices. `CustomDelivery` selects the first slice whose price ceiling is above the cart total, read from the session cart (`CustomDelivery.php:442-446`). A threshold is a slice with a zero price, a setting of the module. With the scope, the total is the total of the destination.
- Product flag. `product.free_shipping` leaves the product out of the quote, and a surcharge can be added (`local/config/schema.xml:61-63`). The quoter drops the free lines from the sub-cart and forces zero when everything is free (`ProductShippingRuleQuoter.php:50-77`). This works on the sub-cart of a destination as it does on a cart.
- Free-shipping coupons. A coupon has an `isRemovingPostage` flag and lists of countries and modules (`core/lib/Thelia/Domain/Promotion/Coupon/Type/CouponAbstract.php:73`). The check reads the country of the one cart delivery address and the one cart module (`core/lib/Thelia/Domain/Promotion/Coupon/Service/CouponManager.php:376`, `:404`), and the estimate version takes one country and one module (`core/lib/Thelia/Domain/Promotion/Coupon/Service/CouponFreeShippingEvaluator.php:27`). An empty country list means all countries. With destinations the coupon is evaluated per destination, with the country and module of the destination, and applies to every eligible destination. The product surcharges stay due, as today (`core/lib/Thelia/Action/Coupon.php:408-421`).
- Zones. Eligibility of a module for a country, state and postal code is unchanged (`core/lib/Thelia/Domain/Shipping/Service/DeliveryModuleEligibilityChecker.php`).

The coupon condition "delivery module is one of" reads the single module of the cart (`core/lib/Thelia/Condition/Implementation/MatchDeliveryModules.php:76`). With several destinations it has no single module to read; whether it requires every destination or one of them to match is an [open question](#open-questions).

### Example

A module is free from 100 euros of goods. The cart holds 150 euros: destination A has 100, destination B has 50.

- A is free. The module sees a sub-cart of 100.
- B pays its slice. The module sees a sub-cart of 50.
- The same cart sent to one address would be free, because the module sees 150.

This is intended: the carrier invoices each destination. The documentation and the checkout say it. A shop that wants the threshold on the whole order has to use a coupon or a rule at order level, which is a separate decision ([Open questions](#open-questions)).

## Module impact

Option 1 keeps one order, one invoice reference and one payment. It adds destinations, each with a delivery address, a delivery module and a postage ([Option 1](#option-1-one-order-several-destinations)). Nothing changes for a module while the order has a single destination. The sections below describe what each family sees once an order has two or more.

Modules are read at the tag of their latest stable version on Packagist (survey date 2026-11-04, sources listed at the end of this section).

### Delivery modules

Inventory criterion: a package of type `thelia-module` on Packagist whose main class extends `AbstractDeliveryModule` or `AbstractDeliveryModuleWithState` and whose `Config/module.xml` at the latest stable tag declares `<thelia>` 3.0.0 or later. A `<thelia>` value is a declaration, not proof of compatibility, and none of these modules was run for this study. Modules still declared for 2.x (Colissimo, Chronopost, Mondial Relay, So Colissimo, Canada Post) and modules that do not quote a postage (labels, free-shipping rules) are left out of this table; modules that act after the order, labels included, are covered in [After the order is placed](#after-the-order-is-placed). Packagist lists `thelia-modules/carriers-delivery` and `thelia/carriers-delivery-module` at the same tag, so they count once: 13 modules.

How the core quotes a destination is described in [Quoting one destination](#quoting-one-destination): a scope restricted to the lines of the destination, with the country, state and address of the destination, and no signature change on `DeliveryModuleInterface::getPostage(Country, ?State)`. The postage event already carries the quoted cart and the address (`core/lib/Thelia/Domain/Shipping/Service/DeliveryPostageQuerier.php:56`).

Two optional interfaces, one per capability (working names):

- `MultiDestinationQuoteInterface`: the module states that it quotes from the scoped cart, the country, the state and the cart address only. A module is offered on an order with several destinations only if it implements it. Without it the module is offered for single-destination orders only.
- `DestinationPickupInterface`: the module chooses a relay and rewrites the address of a destination after the order exists ([Pickup modules](#pickup-modules)).

Opt-in criterion for the first interface: in `getPostage`, `isValidDelivery` and its postage listeners, the module reads the session cart or the event cart and address, and nothing else that the scope does not replace. It does not read the request, a business key of the session, the session order, the address of the cart through its id, or rows queried by cart id. A declaration cannot be checked by reading, so the core ships a contract test that module authors run: the same module quoted on two destinations of one cart must return the quote of each destination alone.

Impact values:

- opt-in to add: the module reads only the cart, the country and the cart address, so the scoped cart is enough; it adds the interface.
- single-destination: the module also reads the request, a session key, or the order address, which a destination quote does not replace. It stays single-destination until its author changes it.

| Module (version) | Reads the cart in `getPostage` | What else it reads or writes | Impact |
|---|---|---|---|
| ColissimoHomeDelivery 3.0.3 | session cart, `ColissimoHomeDelivery.php:311-312` | needs a session (`:299`) | opt-in to add |
| CustomDelivery 4.2.0 | session cart, `CustomDelivery.php:327,346` | none | opt-in to add |
| DpdClassic 3.1.0 | session cart, `DpdClassic.php:96,149` | session locale | opt-in to add |
| Predict 3.0.1 | session cart, `Predict.php:97-99` | session locale | opt-in to add |
| GeolocatedDelivery 2.0.0 | event cart and address, `EventListeners/PostageListener.php:77` | none | opt-in to add |
| VirtualProductDelivery 3.0.3 | none in `getPostage`; `isValidDelivery` reads the session cart, `VirtualProductDelivery.php:51` | none | opt-in to add |
| ChronopostHomeDelivery 3.2.1 | session cart, `ChronopostHomeDelivery.php:424-425` | delivery type from the request (`:429`) | single-destination |
| ChronopostPickupPoint 3.2.0 | session cart, `ChronopostPickupPoint.php:419-420` | option code from the request (`:245-249`); table keyed on the order address (`Config/schema.xml:62`) | single-destination |
| ColissimoPickupPoint 3.1.0 | session cart, `ColissimoPickupPoint.php:217-218` | relay kept in the session (`Listener/SetDeliveryModule.php:178`), saved on `ORDER_BEFORE_PAYMENT` (`:272`) | single-destination |
| DpdPickup 3.0.1 | session cart, `DpdPickup.php:282-283` | relay code from the request (`Listener/SetDeliveryModule.php:73-75`) | single-destination |
| CarriersDelivery 1.0.2 | session cart, `CarriersDelivery.php:130` | writes `CarrierDeliveryPostageResult` into the session (`:134`); reads the chosen address from the session order (`:283-285`) | single-destination |
| DeliveryRound 3.0.0 | none (fixed price) | `isValidDelivery` reads `address_id` from the request or the default address of the customer (`DeliveryRound.php:56-66`) | single-destination |
| LocalPickup 3.0.2 | none (fixed price, `LocalPickup.php:61-68`) | rewrites the order delivery address after creation (`EventListeners/UpdateDeliveryAddress.php:54-55,87`) | single-destination |

Nine of the 13 read the session cart in `getPostage` (all rows with a session cart except VirtualProductDelivery). Five of them also read the request or business data kept in the session. Three more read the session locale or need a session, which the scope does not affect. In total, 6 of the 13 are opt-in candidates and 7 stay single-destination. The table was built from a reading of `getPostage` and `isValidDelivery`; the stricter criterion above (no read of the cart address by id, no query by cart id) was not checked module by module and is part of the contract test.

#### Pickup modules

A pickup module rewrites the delivery address after the order exists: the core says so at `core/lib/Thelia/Domain/Checkout/Service/CheckoutPaymentService.php:173-175` and `local/config/schema.xml:805-808`. With destinations the rewrite targets the address of one destination, never the others, and `order.delivery_order_address_id` follows only when that destination is the main one ([Source of truth](#source-of-truth)). This needs an optional destination id on the address event (`ORDER_UPDATE_ADDRESS`, `core/lib/Thelia/Core/Event/TheliaEvents.php:375`); the event names and getters stay frozen by the compatibility promise. Whether a relay choice per destination is wanted at all is an [open question](#open-questions).

#### After the order is placed

Delivery modules also act once the order exists: labels, tracking, saving the chosen relay at `ORDER_BEFORE_PAYMENT`, tracking e-mails. They recognize their orders by `order.delivery_module_id`. On an order with several destinations that column holds the carrier of the main destination, so the carrier of another destination would never see the order as its own. The core and the themes use the same single field:

- the tracking link of a parcel is resolved from the order carrier and tracking number (`core/lib/Thelia/Domain/Order/Service/OrderTrackingUrlResolver.php:61`), and a parcel whose carrier differs from the order's gets no link (`:99`);
- `Order::getDeliveryModuleInstance()` returns one module (`core/lib/Thelia/Model/Order.php:923-929`);
- the back-office delivery card passes the order carrier to its hook (`templates/backOffice/default-twig/order/detail.html.twig:398`), and so does the invoice (`templates/pdf/default/invoice.html.twig:201`).

Proposed contract: a module reacts per parcel and per destination, not per order. The parcel events already exist (`ORDER_SHIPMENT_CREATE`, `_SHIP`, `_CANCEL`, `core/lib/Thelia/Core/Event/TheliaEvents.php:371-373`); they gain access to the destination of the parcel and its carrier. Hooks that today receive the order carrier receive the destination and its carrier. The tracking link of a parcel uses the carrier of its destination. A module that keeps reading `order.delivery_module_id` keeps working on single-destination orders.

Label modules published for Thelia 3 (`<thelia>3.0.0` in `Config/module.xml`): ChronopostLabel 3.0.2, ColissimoLabel 3.1.1 and DpdLabel 3.1.1. Each builds its label from the delivery address of the order (`Controller/ChronopostLabelController.php:136`, `Request/LabelRequest.php:33`, `Controller/LabelController.php:274`), so on an order with several destinations every label would carry the main address until they read the destination of the parcel (L6). Tracking modules were not inventoried; they are listed in [Open questions](#open-questions).

### Payment modules

The contract does not change: `PaymentModuleInterface::pay(Order $order)` (`core/lib/Thelia/Module/PaymentModuleInterface.php:33`) receives one order, and a payment covers one order (`order_payment_part.order_id` is NOT NULL, `local/config/schema.xml:3482-3494`). The amount stays `Order::getTotalAmount()` (`core/lib/Thelia/Model/Order.php:232`). `order.postage` and `order.postage_tax` keep the sum of the destination postages ([Source of truth](#source-of-truth)), so a module that reads them (for example PayPal at `Service/PayPalAgreementService.php:155`, PayPlugOney at `Service/OrderPayPlugDataPresenter.php:79`) sees the same numbers as today.

Modules that send a single delivery address to the provider read `order.delivery_order_address_id`, which holds the address of the main destination:

- PayPal 6.2.0 builds the shipping address of the payment from `getDeliveryOrderAddressId()` (`Service/Base/PayPalBaseService.php:213`, in `generateShippingAddress`, declared at `:212`) and copies it to the agreement (`Service/PayPalAgreementService.php:762`). The provider receives one address, so the main address is a partial truth on a multi-address order. Which behaviour to choose is an [open question](#open-questions).
- CmCIC 3.1.0 sends a billing and a shipping context built from the invoice and the delivery address (`Cmcic/CmCIC.php:323-336`). Same decision as PayPal.
- PayPlugModule 3.2.1 sends the delivery address as the shipping address of the payment (`Event/PayPlugPaymentEvent.php:599`, fields at `:139-221`). PayPlugOney 3.0.1 builds its payment from the same event (`Service/OneyService.php:48-49`) and checks the delivery address before offering Oney (`Controller/Front/OneyController.php:91`). Same decision as PayPal.
- Not found reading a delivery address in the code read: Payzen 3.2.0, PayzenEmbedded 3.7.3, Stripe 5.1.0, Paybox 3.0.2 and BridgePayment 3.0.1 (grep for `DeliveryOrderAddress`, `getDeliveryAddress` and `shipping`; Paybox and BridgePayment read the invoice address only, `Paybox.php:211`, `Request/PaymentRequest.php:54`).

The express checkout of the core stays single-destination: it places the one carrier and address written on the cart ([Order creation](#order-creation)), so it has to refuse a cart that holds planned destinations (part of L2).

### Invoicing and PDF

The invoice reference allocator numbers an order when it becomes paid. One order still gives one reference, so the numbering and its gap-free sequence do not change ([One invoice per order](#one-invoice-per-order)).

- InvoiceRef 3.1.0 numbers on `ORDER_UPDATE_STATUS` (`EventListeners/OrderListener.php:43`): one reference per order, no change.
- ProcessAndInvoice 3.0.1 marks each order processed on its own (`Service/BatchProcessor.php:251`): one invoice per order, no change. Whether its delivery-note step lists parcels was not read: to be confirmed.
- CreditNote 4.4.0 ties a credit note to the order and to an invoice address (`Config/schema.xml:12,15`). The module allows several credit notes per order and caps their sum at the order total (`Service/CreditNoteOrderCeiling.php:14-18`), so the order stays the unit. Postage is credited from the order scalars (`Service/CreditNoteModalPresenter.php:357-361`). How a postage credit splits two tax rates is an [open question](#open-questions).
- FacturX 2.0.0 reads one postage rate and one delivery country. The postage category is derived from a single net and tax pair (`Service/InvoiceDataBuilder.php:60-66`) and the delivery country is the one of `order.delivery_order_address_id` (`:115`, written into one `ShipToTradeParty` at `Service/CiiXmlBuilder.php:66-67`). Product taxes are read per line (`:132`), so they follow the country of each destination. In the worked example (postage 6.00 at 20 % and 12.00 at 19 %) the blended rate is 19.33 %, which is not a rate that exists. Not verified: how `snapRate` behaves on it and whether the EN 16931 profile accepts several ship-to parties. To be confirmed by the module maintainers and the chartered accountant ([Open questions](#open-questions)).
- PDF templates print one delivery address: the invoice at `templates/pdf/default/invoice.html.twig:201-202` and the delivery note at `templates/pdf/default/delivery.html.twig:205-206`, both with a hook that a module can fill. The delivery note already prints one parcel (`shipment_id`); it needs the address of the parcel's destination. The invoice lists the address of each destination.

### Other consumers

- Export: `core/lib/Thelia/Domain/DataTransfer/Export/Type/OrderExport.php:149-153` joins one delivery address and one carrier per order (`:150`, `:153`) on each line. The join moves to the destination of the line, so each row carries its own address and carrier.
- Back-office lists: the carrier and country filters (`templates/backOffice/default-twig/src/Service/Order/OrderFilters.php:306-313`) and the list of countries offered (`templates/backOffice/default-twig/src/Repository/OrderRepository.php:666-673`) read the order columns, so an order with a French and a German destination would not appear under Germany. They filter with an `EXISTS` on the destinations, and the countries offered are read from the destinations.
- Statistics: no reader of the delivery country or carrier was found ([Readers outside the checkout](#readers-outside-the-checkout)). The sales statistics read `order.postage` and `order.postage_tax`, which keep the sum of the destinations, so they need no change.
- Personal data export: one `delivery_address` key (`core/lib/Thelia/Domain/Customer/Service/CustomerPersonalDataExporter.php:201`). Keep it, add the list of destinations.
- History: an address is classified as delivery or invoice by comparison with the two order columns, anything else gets no type (`core/lib/Thelia/Domain/Order/EventListener/RecordOrderHistoryListener.php:276-283`). A destination address needs its own type.
- Returns: the domain code does not read an address (no match for `Address` under `core/lib/Thelia/Domain/OrderReturn`); the back-office reads the delivery country of the order (`templates/backOffice/default-twig/src/Repository/OrderReturnRepository.php:324`, `templates/backOffice/default-twig/src/Service/OrderReturn/OrderReturnDetailPresenter.php:211`). A return of a line shipped abroad should show the country of its destination: to be confirmed by the product maintainer.
- Order loop: `core/lib/Thelia/Core/Template/Loop/Order.php:168-181,290,296,375` searches and exposes the main delivery address only. It stays correct for single-destination orders; the search on the delivery address gains an `EXISTS` on the destinations, like the back-office filters.

Sources (tag read, survey 2026-11-04):
- https://github.com/thelia-modules/PayPal/blob/6.2.0/Service/Base/PayPalBaseService.php#L213
- https://github.com/thelia-modules/PayPal/blob/6.2.0/Service/PayPalAgreementService.php#L762
- https://github.com/thelia-modules/Cmcic/tree/3.1.0, https://github.com/thelia-modules/PayPlugModule/tree/3.2.1 and https://github.com/thelia-modules/PayPlugOney/tree/3.0.1
- https://github.com/thelia-modules/ChronopostLabel/tree/3.0.2, https://github.com/thelia-modules/ColissimoLabel/tree/3.1.1 and https://github.com/thelia-modules/DpdLabel/tree/3.1.1
- https://github.com/thelia-modules/ColissimoHomeDelivery/tree/3.0.3, https://github.com/thelia-modules/ColissimoPickupPoint/tree/3.1.0
- https://github.com/thelia-modules/ChronopostHomeDelivery/tree/3.2.1, https://github.com/thelia-modules/ChronopostPickupPoint/tree/3.2.0
- https://github.com/thelia-modules/DpdClassic/tree/3.1.0, https://github.com/thelia-modules/DpdPickup/tree/3.0.1
- https://github.com/thelia-modules/CustomDelivery/tree/4.2.0, https://github.com/thelia-modules/CarriersDelivery/tree/1.0.2
- https://github.com/thelia-modules/DeliveryRound/tree/3.0.0, https://github.com/thelia-modules/GeolocatedDelivery/tree/2.0.0
- https://github.com/thelia-modules/LocalPickup/tree/3.0.2, https://github.com/thelia-modules/Predict/tree/3.0.1, https://github.com/thelia-modules/VirtualProductDelivery/tree/3.0.3
- https://github.com/thelia-modules/FacturX/tree/2.0.0, https://github.com/thelia-modules/CreditNote/tree/4.4.0
- https://github.com/thelia-modules/InvoiceRef/tree/3.1.0, https://github.com/thelia-modules/ProcessAndInvoice/tree/3.0.1
- https://packagist.org/packages/thelia/paypal-module and the Packagist pages of the other packages named above

## Upgrade of existing shops

Option 1 adds a destination table and links to it from tables every existing shop already has.

- Schema: a new `order_delivery` table (address, carrier and its title, postage, postage tax, postage tax rule title, exemption, gift message). A nullable destination reference on `order_product`, `order_shipment` and `order_postage_tax`. On the cart side, the planned destinations of a cart and the destination of each cart line. They go in `local/config/schema.xml` and `setup/thelia.sql`. The references are nullable so that code writing these rows without a destination keeps working: on insert, the core gives a line without one the main destination of its order, and gives a parcel the destination of its lines.
- Settings: two rows in the `config` table, the switch that hides the feature and the maximum number of destinations per order ([Performance](#performance)). They go in `setup/insert.sql.tpl` and in the update script; configuration variables are covered by the compatibility promise (`BC-PROMISE.md:23-24`).
- Migration: a script in `setup/update/sql/` for the version being prepared (`3.4.0.sql` today, `core/lib/Thelia/Core/TheliaKernel.php:110`, `CHANGELOG.md:1`). It must be replayable. The file already adds columns only when `information_schema` shows them missing (`setup/update/sql/3.4.0.sql:114`) and inserts settings with `INSERT IGNORE` (`:72`, `:83`, `:91`). A shop runs it with `php local/setup/update.php` (`UPDATE.md:28-34`).
- CI: the Upgrade workflow moves a shop from the last release and compares its schema with a fresh install. It catches a column present in `schema.xml` and missing from the update script. It is a signal, not a required check (`.github/workflows/upgrade.yml:1-10`, `.github/scripts/upgrade/compare-schema.sh:1-8`).
- Publication: `.github/scripts/split-downstream.sh:36-38` publishes `core` and `setup` to `thelia/core` and `thelia/setup` on `main`, `3.1`, `3.2` and `3.3`, and `local/config` to `thelia/config` on `main` only. The schema change reaches shops through `main` after a green CI. It cannot be back-ported to the 3.3 line of `thelia/config`.
- Existing orders: the script back-fills one destination per existing order, from the order columns (delivery address, carrier and title, postage, postage tax, rule title, gift message, and the exemption frozen on the invoice address). Every line of the order, every existing parcel and every `order_postage_tax` row are attached to it. The 3.3 update created a parcel only for orders that were sent or had a tracking number (`setup/update/sql/3.3.0.sql:700-721`); the back-fill creates no parcel, so that history is unchanged. After the upgrade every order has at least one destination and no reader needs a fallback to the order address.
- Promise to readers: `order.delivery_order_address_id` stays `NOT NULL` and holds the address of the main destination; for single-destination orders, which are all existing orders, nothing changes. Order events, `PaymentModuleInterface` and `DeliveryModuleInterface` do not change. `BC-PROMISE.md:25-26` requires a migration with every schema change.
- Declared change of meaning: the promise also covers "the meaning of each field" of the API resources and the columns of the schema (`BC-PROMISE.md:20-21`, `:25-26`). On an order with several destinations, `Order.deliveryOrderAddress` (`core/lib/Thelia/Api/Resource/Order.php:309-313`) and the order columns listed in [Source of truth](#source-of-truth) describe the main destination, not the whole delivery. The change is limited to multi-destination orders, is written in the CHANGELOG and in the upgrade notes, and the list of destinations is exposed on `Order` in read so that a client can read the whole delivery.
- Documentation gap: `BC-PROMISE.md:61` tells contributors to add each deprecation to `UPGRADE-3.x.md` at the repository root. That file does not exist (`git ls-files` finds no such file). The upgrade notes of the repository are in `UPDATE.md` (release notes from `:130`). This study uses `UPDATE.md`; which name is corrected is an [open question](#open-questions) for the core maintainers.

## Security and personal data

### Who reads an order address today

- `/api` has no default refusal outside these prefixes: only `^/api/admin` and `^/api/mcp` (administrator) and `^/api/front/account` (customer) are protected by the firewall, next to the public login, token refresh and docs paths (`core/lib/Thelia/Core/TheliaKernel.php:995-1001`). Every other front operation needs its own `security` expression.
- `OrderAddress` has administrator operations only (`core/lib/Thelia/Api/Resource/OrderAddress.php:33-53`) and none for the front. A customer reads an address through `Order`, in the `FRONT_READ_SINGLE` group (`core/lib/Thelia/Api/Resource/Order.php:303-313`), behind `object.customer.getId() == user.getId()` (`Order.php:82`). A buyer without an account reaches the order by a signed token on `/front/guest-orders/{token}`, where `security: 'true'` is intentional and the provider holds the check (`Order.php:91-98`).
- `OrderShipment` follows the same model: front reads behind a customer check (`core/lib/Thelia/Api/Resource/OrderShipment.php:72-83`).

### Rules for the new data

Reading: a destination address is exposed only through the order or the parcel resource, in the front read group of a resource that already carries the owner check. A new resource that exposes an address by itself carries `security` and an ownership check, two barriers, as for every front operation outside `/api/front/account`. A parcel list filtered by customer uses the same extension as today (`usesForCustomer`, `OrderShipment.php:76`).

Writing the plan: every address id sent to build the destinations of a cart is checked before use. For a customer, the address belongs to that customer; for a guest, it is an address of the guest session. The Flexy delivery step already does this for its one address (`templates/frontOffice/flexy/components/Organisms/Delivery/Base.php:351`), and the core refuses a delivery address moved since it was chosen (`core/lib/Thelia/Domain/Cart/Service/CartGuard.php:187`). Without the check, a buyer could freeze another customer's address into an order and read it back through that order.

Guests: the feature is open to guest checkout, with the same visibility check as today, since gift buyers often order without an account.

Tests: a plan that names another customer's address is refused; a customer reading the order of another customer gets 403 or 404 on the destination addresses; the same for a guest token that belongs to another order. Each test is seen failing with the check removed.

### Back-office and sale units

`AdminSaleUnitScopeExtension` lists the address columns of `order` by hand: the invoice column and the delivery column in a `UNION` (`core/lib/Thelia/Api/Bridge/Propel/Extension/AdminSaleUnitScopeExtension.php:84`). An address stored on a destination is not in that list. An administrator limited to one sale unit would then either see no destination address or, if the query is loosened to compensate, addresses of other units. The destination address column goes into the `UNION`. Test: an administrator of unit A reading `/admin/order_addresses` must not receive a destination address of an order of unit B.

The same closed list exists in the back-office address edition, which checks that the address belongs to the order by comparing the two columns (`templates/backOffice/default-twig/src/Controller/Order/OrderController.php:426`): editing a destination address is refused today unless that check follows.

### Personal data

- More people are concerned. An order with several recipients holds the name, address and often the phone number of people who are not the buyer and have no account.
- Erasure: `core/lib/Thelia/Domain/Customer/Service/CustomerAnonymizer.php:135-139` collects the ids of the two address columns of the customer orders, then neutralizes the rows in place (`:128-131`). The destination addresses are added to that collection, otherwise they stay readable after the customer is anonymized.
- Access request: the export writes one `delivery_address` (`CustomerPersonalDataExporter.php:201`). It adds one entry per destination address. The buyer sees the data of the recipients he gave. Whether a recipient can ask the shop directly for the data is to be confirmed by the data protection officer of each shop; the project does not decide it.
- Retention: the same as the order. Anonymization is measured from the last order of the account because the order is backed by an accounting obligation (`core/lib/Thelia/Domain/Customer/Service/CustomerPurger.php:23-35`). A destination address is a frozen `order_address` row, referenced with ON DELETE RESTRICT as the order's delivery address is (`local/config/schema.xml:841`); the destination row itself belongs to its order. The address lives and is neutralized with the order. No separate duration.
- E-mail: the confirmation goes to the buyer only (`core/lib/Thelia/Action/Order.php:300-313`, sent to the customer of the order). Recipients receive nothing, unless a merchant chooses to notify them, which would need their consent and an explicit setting. The confirmation shows every destination address instead of one (`templates/email/default/order_confirmation.html.twig:33`). Carrier tracking messages are sent per order today (for example `DpdClassic/EventListeners/SendEMail.php:71-77`). Who is notified, and who receives the tracking messages per destination, is an [open question](#open-questions).

### Pickup modules and payment retries

The mechanism, one rewrite per destination, is in [Pickup modules](#pickup-modules). Two points remain for personal data and payment retries:

- a relay address is a business address, not personal data of the recipient. The recipient name still sits in the same row and follows the rules above;
- the frozen cart fingerprint (`CheckoutPaymentService.php:173-175`) already protects a payment retry against a changed cart. It has to include the destination addresses and modules, otherwise an address change between two payment attempts goes unnoticed.

## Performance

Nothing was measured. What follows is read from the code. The measurement to run is described at the end.

### Cost found today

One render of the Flexy delivery step lists the valid methods (`templates/frontOffice/flexy/components/Organisms/Delivery/Base.php:206-209`, which calls `ShippingFacade::listValidMethods()`, `core/lib/Thelia/Domain/Shipping/ShippingFacade.php:234`). For each active module the loop does the following.

- `DeliveryModuleResourceBuilder::build()` checks eligibility (`core/lib/Thelia/Domain/Shipping/Service/DeliveryModuleResourceBuilder.php:49`), quotes the postage (`:56`) and lists the options (`:62`).
- The quote dispatches `MODULE_DELIVERY_GET_POSTAGE` (`core/lib/Thelia/Domain/Shipping/Service/DeliveryPostageQuerier.php:57`). That event calls `isValidDelivery()` and `getPostage()` on the module, and each one reads the session cart again (`core/lib/Thelia/Action/Delivery.php:62-72`, `CustomDelivery.php:327`, `:346`).
- The loop then calls `getOptions()` a second time for the same module (`core/lib/Thelia/Domain/Shipping/ShippingFacade.php:262`). Each call dispatches `MODULE_DELIVERY_GET_OPTIONS` (`core/lib/Thelia/Domain/Shipping/Service/DeliveryOptionsProvider.php:42`).

That is one postage dispatch and two options dispatches per module and per render, plus a surcharge quote per option when a product rule changes the postage (`ProductShippingRuleQuoter::quoteOptions()`, `:85`).

Clicks add to it. `selectDeliveryModuleOption` lists all the methods to check the choice (`templates/frontOffice/flexy/components/Organisms/Delivery/Base.php:318`), then writes the module, which quotes the postage once more (`:328`, `Action\Cart::calculatePostage()` at `core/lib/Thelia/Action/Cart.php:173`). `selectDeliveryAddress` calls `offersModule()`, which lists all the methods again to look for one id (`Base.php:374`, `:409`). The template reads the list from `this.deliveryModulesOptions` (`templates/frontOffice/flexy/components/Organisms/Delivery/Base.html.twig:66`).

There is no cache of quotes. The only memo in the shipping code is the one of the product rules, kept for the request and keyed on the lines of the cart (`core/lib/Thelia/Domain/Shipping/ProductRule/CartShippingRules.php:29-54`). A search for `static`, `cache` and `memo` in `Domain/Shipping` finds nothing else, apart from `OrderShipmentManager`, which does not quote.

### With several destinations

With N destinations and M active modules the step runs N x M quotes per render instead of M, each with its two options calls, and every click repeats it. Each quote opens a scope and builds a sub-cart. Carriers that call a web service multiply remote calls and use up their quotas. Parcels add no quote: they are composed after the order.

### Proposed measures

1. Cap the number of destinations. A shop setting, "maximum number of destinations per order", with a low default (3 is proposed), enforced by the API that builds the plan and by the checkout component. Measures 2 to 6 reduce the cost; only this one bounds it.
2. Group destinations that cannot be told apart for a module: same country, state and postal code, and same sub-cart lines and quantities. One quote serves the group.
3. Keep a quote for the length of the request, keyed on module, country, state, postal code, currency, customer group, sale unit, language and a fingerprint of the sub-cart. Modules read the currency, the group and the language from the session, so a key without them could serve a quote computed for another context. `core/lib/Thelia/Domain/Shipping/ProductRule/CartShippingRules.php:54` is the precedent for the key. The memo is dropped when the cart or a rule is written.
4. Remove the second `getOptions()` call: `build()` already holds the options in the resource, so `ShippingFacade` can reuse them (`DeliveryModuleResourceBuilder.php:62`, `ShippingFacade.php:262`).
5. In the live component, build the list once per request and reuse it in `selectDeliveryModuleOption` and `offersModule()` (`Base.php:318`, `:409`).
6. Quote only the destination whose address or module changed, not all of them.

Measures 4 and 5 also help a single-address shop and can ship before any of the rest (lot L0 of the [Delivery plan](#delivery-plan)).

### Measurement to make

On a shop with the shipped modules and a few third-party ones, count module calls and database queries for one render and one click of the delivery step, with 1, 2, 3 and 5 destinations, before and after the measures. Record wall time on a warm cache. The figures set the default of the cap and decide whether measure 6 is needed.

## Decision

Option 1 is the recommended shape: one order that carries several destinations. Each destination freezes its address, carrier, postage, postage tax and exemption; each order line belongs to one destination; parcels stay the merchant's shipping gesture and each belongs to one destination. The order keeps one invoice reference and one payment; its delivery columns are derived from the destinations, with the address and carrier of the main destination ([Source of truth](#source-of-truth)). No payment or delivery module signature changes; delivery modules opt in through optional interfaces.

The recommendation is conditional. Nothing goes into the core until the demand is proven: a number of distinct projects asking for one invoice over several destinations, the number to be set by the product maintainer before the study is accepted. Until then the answer to a request is alternative (a) ([Alternatives](#alternatives)). Lot L0 of the plan does not depend on this decision.

What would move the decision:

- Toward (a), do nothing: the threshold is not reached, or the requests come from consumer shops where a gift is an occasional second checkout.
- Toward (b), option 3 as a third-party module: the demand is real but comes from business buyers who accept one invoice per destination, and is needed before a core minor release can carry option 1.
- Toward a reduced option 1: the chartered accountant does not accept VAT of two member states on one invoice. Option 1 then ships with every destination of an order restricted to one country, which removes the tax-per-line and per-destination exemption work of L2 but keeps the rest.
- Toward (c): the need behind the request is a split payment, not a split delivery.

## Delivery plan

L0 is independent of the decision and is costed apart. L1 to L6 are the feature.

| Lot | Content | Depends on | Released in | Days |
|---|---|---|---|---|
| L0 Delivery step performance | Measures 4 and 5 of [Performance](#performance): reuse the options already built by `DeliveryModuleResourceBuilder` instead of the second `getOptions()` call (`core/lib/Thelia/Domain/Shipping/ShippingFacade.php:262`), and build the method list once per request in the Flexy delivery component. Measurement before and after, as described in Performance. | none | thelia/core, Flexy theme | 0.75 - 1.5 |

| Lot | Content | Depends on | Released in | Days |
|---|---|---|---|---|
| L1 Schema and migration | `order_delivery` table: frozen address (foreign key to `order_address`), carrier and title, postage, postage tax, rule title, exemption and waived VAT, gift message. Nullable destination reference on `order_product`, `order_shipment` and `order_postage_tax` (`schema.xml:2274-2287`). Cart side: the planned destinations of a cart (address, module, postage) and the destination of each cart line, since the cart holds one address, one module and one postage today (`schema.xml:1725-1727`). The two settings (switch, off by default, and maximum number of destinations) in `setup/insert.sql.tpl`. Replayable update script for the unreleased version (`setup/update/sql/3.4.0.sql`, version at `core/lib/Thelia/Core/TheliaKernel.php:110`) with the back-fill: one destination per existing order, its lines, parcels and postage tax rows attached. | none | thelia/config, thelia/setup, thelia/core | 3.5 - 5.5 |
| L2 Quoting, tax, order creation and parcels | Quote each destination through `ShippingCartScope` on a sub-cart that carries the destination address, with the country and state of the destination. The optional quote interface and the rule that offers a module without it only on a single-destination cart. Tax country per line in `OrderFacade::createOrder()`, exemption and waived VAT per destination, postage tax breakdown per destination, destinations and line links created from the cart plan. One core service that maintains the derived order columns ([Source of truth](#source-of-truth)), `delivery_ref` included: it is taken from the first parcel of the main destination, where the first-parcel rule of `OrderShipmentManager` works per order today (`:283-288`). `OrderShipmentManager`: carrier of a new parcel taken from its destination, lines of two destinations refused, `shipEverythingLeft()` per destination. Cart totals and the total given to a payment module computed per destination and added: `BaseModule::getCurrentOrderTotalAmount()` resolves one taxation place for the whole cart today (`core/lib/Thelia/Module/BaseModule.php:495-509`, place at `:503`). Free-shipping coupons evaluated per destination. Cart fingerprint extended to destination addresses and modules. Express checkout refuses a cart with planned destinations. Cap on the number of destinations and performance measures 2 and 3 (grouping and request memo of quotes). | L1 | thelia/core | 8 - 12 |
| L3 Flexy checkout | Assign each cart line to an address of the buyer, pick a carrier per destination, show the postage per destination and the notices required by the study (tax-inclusive price that changes with the destination country, free-shipping threshold read per destination). Every address checked as the buyer's, guest included. Cap on the number of destinations in the component. Confirmation, account and guest order pages: parcels grouped by destination, with its address. | L2 | Flexy theme | 5.5 - 8.5 |
| L4 Back office, PDF, e-mails and core readers | Parcels tab: destinations with address, carrier and postage, and their parcels; editing a destination address (the ownership check compares two columns today, see [Security and personal data](#security-and-personal-data)). List filters and countries offered read the destinations. Delivery card and module hooks per destination. Invoice: lines grouped by destination, one postage line per destination, tax by rate and country. Delivery note: address of its parcel's destination. Confirmation and notification e-mails: every destination with its address. Core readers: tracking link of a parcel from its destination carrier, order export joined on the destination of each line, history type for a destination address, order loop search. | L2 | default-twig theme, thelia-templates/pdf, thelia-templates/email, thelia/core | 5 - 7.5 |
| L5 API, security and personal data | `Order` exposes its destinations in read, in the groups of the order owner; `OrderShipment` exposes its destination; admin write. Front API to build the destination plan of a cart, each operation with `security`, an ownership check on every address id and the cap. Destination address added to `AdminSaleUnitScopeExtension`, `CustomerAnonymizer` and the personal data export. Tests: another customer's address in a plan is refused; another customer, another guest token and an administrator of another sale unit receive no destination address. | L1, L2 | thelia/core | 3.5 - 5.5 |
| L6 Module contract and documentation | Pickup interface and optional destination id on the address update event. Parcel events give access to the destination and its carrier. Contract test for the quote interface. Developer documentation of both interfaces, of the pickup rewrite per destination and of the reaction per parcel. Inventory of the label and tracking modules published for Thelia 3. Quote interface added to the two modules shipped with the core (`CustomDelivery`, `VirtualProductDelivery`); pull requests proposed to the four other opt-in candidates. Merchant documentation: free shipping per destination, address sent to PayPal and CmCIC, change of meaning of the order delivery address. | L2 for the interfaces, L3 for the documentation | thelia/core, thelia/docs, each module | 2.5 - 4 |

Sum of the feature lots (L1 to L6): 28 to 43 days.

Contingency, held at the level of the whole feature: 6 days. It is not spread over the lots. Its reasons:

- The chartered accountant may require mentions or a layout on the multi-country invoice, or a presentation of mixed exempted and taxed destinations, that the study cannot foresee (L4, possibly L2 for rounding and the discount split).
- No third-party module was run. The opt-in list rests on reading their code at one tag, and the tracking modules were not inventoried ([Module impact](#module-impact)).
- FacturX and CreditNote handle one postage rate; their maintainers may ask for core data the plan does not include.

Total of the feature with contingency: 34 to 49 days. With L0: 34.75 to 50.5 days.

Assumptions that bound the ranges:

- L0: no change to the module contract; the two measures are refactorings with tests on the existing step.
- L1: the back-fill creates destinations and links only, no parcel; the history of parcels is unchanged.
- L2: a line belongs to one destination (no split of a line between destinations); rounding per destination and per quote; the discount split pro rata of the goods excluding tax; the gift wrapping line keeps one tax country (the main destination), pending the open question; delivery date and slot offered only to single-destination orders.
- L3: the buyer picks from the addresses already in the account, or of the guest session, and adds one with the existing address form; no relay choice per destination; one checkout layout, the one-page variant follows the same components.
- L4: the existing documents are extended, no new document type.
- L5: no new resource for addresses alone; the destination address is read through `Order` and `OrderShipment`.
- L6: the four other opt-in candidates receive a pull request each; the seven single-destination modules and the label and tracking modules are left to their maintainers, with the documentation.

Why the plan is cut this way:

- L0 stands apart. Measures 4 and 5 of Performance help every shop, carry no schema change and can ship at any time, whatever the decision.
- The quote interface sits in L2. Without it no module is offered on a multi-destination cart, so L2 cannot be tested without it. L6 keeps the pickup interface, the event changes, the documentation and the module pull requests.
- L1 also covers the cart side and the settings. The cart holds one delivery address and one carrier, and its lines have no destination, so the plan of destinations needs storage before the order exists; the settings are `config` rows and travel with the update script.

Releases of the split packages:

- `thelia/config`: 1 release, with L1.
- `thelia/setup`: 1 release, with L1, since the two settings are inserted by the same update script. If the settings were moved to a later lot, it would be 2.
- `thelia/core`: L0 whenever it is ready, then the lots L1, L2, L4, L5 and L6 through `main`.

Order of delivery and what ships alone:

1. L0 ships alone, in any release.
2. L1 ships alone without visible effect: every existing order has one destination after the back-fill and the switch is off. It reaches shops only through `main`: `thelia/config` is published from `main` only (`.github/scripts/split-downstream.sh:36-38`), so the feature belongs to the next minor version and cannot be back-ported to 3.3.
3. L2 ships behind the switch, off by default.
4. L3, L4 and L5 can run side by side after L2. The switch is turned on for a shop only when all three are released: before L4 the invoice prints one address and one tax country, before L5 the destination addresses escape anonymization.
5. Theme pull requests (Flexy, default-twig) and template releases (pdf, e-mail) are opened after the core pull request is merged, since the theme CI installs `thelia/thelia@main`.
6. L6 documentation ships with the release that turns the feature on.

## Open questions

| Question | Why it matters | Who decides |
|---|---|---|
| What proves the demand, and what threshold starts the work? | The recommendation depends on it. Without a threshold the study stays open forever or ships a feature nobody uses. | Product maintainer |
| Is the target B2C, B2B or both? | B2B buyers often accept several invoices, which favors (b). B2C gifts need recipient handling and a simpler checkout. | Product maintainer |
| May one invoice carry VAT of two member states, and with which mentions? Which rounding rule applies (per destination or on the total)? | The worked example assumes destination taxation above the distance-selling threshold. A refusal reduces option 1 to one country per order. | Chartered accountant |
| Is the exemption decided per destination, and how does an invoice show an exempted and a taxed destination together? | A business buyer with a site in the shop country owes local VAT on it. The study proposes an exemption per destination. | Chartered accountant |
| How is an order-level discount split between destinations? | The tax of each rate and country depends on it. The study proposes a split pro rata of the goods excluding tax. | Chartered accountant |
| Free shipping on the order total or on the destination subtotal? | The study evaluates module slices per destination (the carrier invoices each destination). A cart that is free at one address can pay postage when split. Merchants may expect the order total. | Product maintainer, with module maintainers |
| Does the coupon condition "delivery module is one of" require every destination or one of them to match? | It reads the single cart module today. A wrong choice grants or refuses a coupon on mixed carriers. | Product maintainer |
| Which delivery address do PayPal, CmCIC and PayPlug (with PayPlugOney) send to the provider: the main destination or none? | All three send a single address (Module impact). The main one is a partial truth on a multi-address order; sending none may change fraud scoring on the provider side. | Module maintainers |
| Is a relay (pickup point) choice per destination wanted? | The seven single-destination modules include all pickup modules read. Without it a pickup carrier stays limited to single-destination orders. | Module maintainers, product maintainer |
| Should the requested delivery date and slot be chosen per destination? | They depend on the carrier, and the slot is booked once per order today (`core/lib/Thelia/Domain/Order/OrderFacade.php:318-324`) for the cart carrier (`core/lib/Thelia/Domain/Shipping/DeliveryDate/Service/DeliveryDateSelection.php:58`). The study offers them only to single-destination orders. | Product maintainer |
| Should one cart line be split between destinations by quantity? | The study keeps one line, one destination, so one tax country per line. A line can already be split between parcels of one destination. Buying the same product for two addresses means two lines. | Product maintainer |
| What default for the maximum number of destinations? | It bounds the quoting cost and the remote calls of web-service carriers. The study proposes 3, to be set from the measurement. | Core maintainers |
| Are recipients notified (shipping, tracking)? | The confirmation goes to the buyer only. Notifying a recipient needs their e-mail, a consent basis and a shop setting; carrier tracking mails are per order today. | Product maintainer, module maintainers |
| Which tax country applies to the gift wrapping line? | It is one line per order, taxed with the single order country today (`core/lib/Thelia/Domain/Order/OrderFacade.php:304-307`). | Chartered accountant, core maintainers |
| How does a postage credit note split two postage rates? How does FacturX express two ship-to parties and two postage rates? | CreditNote credits postage from the order scalars; FacturX derives one postage rate and one delivery country. | Module maintainers, chartered accountant |
| Is the feature behind a shop setting until every lot is released? | Shipping L2 before L4 and L5 would produce invoices with one address and destination addresses missed by anonymization. | Core maintainers |
| Should `BC-PROMISE.md` point to `UPDATE.md`, or should `UPGRADE-3.x.md` be created? | The promise names a file that does not exist; the deprecations and the change of meaning of this feature need one place. | Core maintainers |
| Which tracking modules published for Thelia 3 read `order.delivery_module_id` after the order, and how do the three label modules pick their orders? | They keep working on single-destination orders only; their count sizes L6 and its share of the contingency. | Module maintainers |
| Who signs this study and who arbitrates between the options? | A decision record needs an owner who accepts it and a named arbiter when maintainers disagree. | Product maintainer |

Left as "to be confirmed" in place, outside this table: the platform comparison ([Context](#context)), the price shown before the split ([Prices shown before the buyer splits the lines](#prices-shown-before-the-buyer-splits-the-lines)), the ProcessAndInvoice delivery note ([Invoicing and PDF](#invoicing-and-pdf)), the return country ([Other consumers](#other-consumers)) and the recipient access request ([Personal data](#personal-data)).
