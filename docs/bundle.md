# Product bundles

A **bundle** is a product sold as one line that is made of other products. A cycling kit made of a bike, two helmets and an optional lock is one product with one price and one line in the cart, while the stock that moves, the parcel that leaves and the weight that is billed are the ones of its components.

The feature costs nothing until a product becomes a bundle: the shop keeps one variable of its configuration (`catalog_has_bundles`, see *A shop without bundles pays nothing*) and every hot path reads it before doing anything else.

## What a bundle is

- A product with `product.is_bundle = 1`.
- It keeps **one technical sale element**, the default one. Its `quantity` is ignored and stays at 0, and it carries the price in fixed mode. A product that already has several sale elements cannot become a bundle, and a bundle cannot receive a second sale element. The refusal is made by the model (`ProductSaleElements::preInsert`, and `preUpdate` when the sale element changes product; `BundleDefinitionException`) under the lock of the product row, so two concurrent writers cannot both add one, and it holds whoever writes: the back office, `POST /api/admin/product_sale_elements` (422), a module.
- Its **components** are rows of `product_bundle_item`. Each one points at a sale element of another product, never at a product, with:
  - `quantity`, the quantity in one bundle (the fixed quantity when `min_quantity` and `max_quantity` are null);
  - `min_quantity` and `max_quantity`, the bounds a customer may choose within, both optional;
  - `required`: a required component is always part of the bundle, an optional one may be left out when its minimum is 0;
  - `position`, the order of display.
- A bundle holds no bundle. A product that is already a component cannot become a bundle, and a bundle cannot be added as a component.
- A sale element appears once in a bundle (unique index on `bundle_product_id, product_sale_elements_id`).

Bounds of a line are `[min_quantity ?? quantity, max_quantity ?? quantity]`. A required component needs `min_quantity > 0` when it has a minimum. The default composition takes every required component at its `quantity` and every optional one at its `min_quantity` when that is above 0, and leaves it out otherwise.

All quantities and prices of a bundle are decimals with six digits (`DECIMAL(16,6)`) and are computed on integers, never on floats (`BundleDecimal`).

## Prices

`product.bundle_price_mode` chooses where the price comes from.

- **`fixed`**: the price is the one of the sale element of the bundle, with the promotion, the sales and the catalog rules a product has. The components give the composition and the stock, nothing else.
- **`computed`**: the price is the sum of the components the customer kept, less a discount. The crossed-out price is the sum itself and the price is the sum less the discount; the line is a promotion (`promo = 1`) only when the discount is above 0. A component is priced at its promotion price when `promo = 1`, at its price otherwise, in the currency of the request.

`bundle_discount` and `bundle_discount_type` give the discount: `percent` (at most 100) or `amount`. An amount is expressed **in the default currency** and converted by the rate for any other currency; the price never goes below 0. Roundings are half up on six decimals.

Worked example, computed mode, 10 % discount:

| Component | Sale element | Quantity | Required | Price excl. tax | Stock |
|---|---|---|---|---|---|
| Bike | v | 1 | yes | 500 | 7 |
| Helmet | c | 2 | yes | 30 | 5 |
| Lock | a | 1 (min 0, max 3) | no | 20 | 10 |

The customer picks the lock twice.

- Crossed-out price: 500 + 2 x 30 + 2 x 20 = **600**.
- Price with the 10 % discount: 600 x 0.9 = **540**.
- With an `amount` discount of 50 instead: **550**.
- With the default composition (no lock): 560, and 504 after the discount.

One computation serves every reader (`BundlePriceCalculator`, reached through `BundleEffectivePriceSource` and `EffectivePriceResolver`): the product loops, the front API, the quick order, the cart and the order read the same price. The customer discount applies on top as for any product. The cart prices a bundle line at the addition and refreshes it at the same moments as any other line. A fixed bundle on promotion is refreshed like a simple line: the offer of its sale element ends the way the offer of a product does. A line of a computed bundle follows the discount of its bundle and is not taken for the trace of a promotional rule.

## Availability

The stock of the sale element of a bundle is never read. The availability is deduced from the components:

`min over the kept components of floor(stock / quantity)`, virtual components being ignored (a bundle of virtual components only is unlimited).

With the example above and the lock chosen twice: min(floor(7/1), floor(5/2), floor(10/2)) = **2**. Without the lock: min(7, 2) = 2. If the helmet falls to a stock of 1, the result is 0 and the bundle can no longer be added.

The stock read is the one of the sale unit in scope when there is one, the national counter otherwise.

Where the displayed quantity is served (`BundleDisplayedStock`): the `quantity` of the sale elements of a bundle in the `product` and `product_sale_elements` loops outside the back office, `GET /api/front/product_sale_elements`, the sale elements nested in `GET /api/front/products/{id}`, and `attr('product', ...)` data access. It is computed on the default composition. A bundle made only of virtual components shows `2147483647` (`BundleDisplayedStock::DISPLAY_LIMIT`): a theme must read any value at or above it as "unlimited". The administration reads of the API are left intact.

### What the cart refuses

With `check_available_stock` on, `BundleCartAdditionGuard` (`CART_ADDITEM`, priority 150) refuses with `NotEnoughStockException` when the quantity added plus the quantity already in the line goes beyond the availability of the chosen composition. It also refuses, with `InvalidBundleCompositionException` (an `InvalidCartException`, so a 422 through the API):

- a composition naming an item of another bundle, or an unknown one;
- a quantity outside the bounds of an item, a required item missing or set to 0;
- a composition that keeps nothing;
- a kept component that is not sellable: the product or its sale element is offline (`visible = 0`), the sale element was deleted, the product is a bundle, or the product is hidden from the current visitor (reserved to a customer group);
- a composition sent for a product that is not a bundle;
- a malformed composition, a quantity above 1,000,000 or more than 500 lines.

The refusals are translated messages the buyer can read, naming the reference of the component and the bounds where they matter; the ids stay on the exception for the code that catches it.

An optional component left out and not sellable never blocks the bundle. The demand of the other lines of the cart on the same components (another bundle, the component sold alone) is not counted at the addition: the order controls it.

`CartItem::preInsert` refuses, whoever writes (a module, a fixture), a non-offered line of a bundle with no composition and a line of a plain product that has one (`InvalidBundleCompositionException`, 422 where the API lets it through). `CartItem::preUpdate` applies the same rule when the product, the sale element or the composition of a line changes. A line that carries a composition is always compared with its product, whatever `catalog_has_bundles` says: the flag is a shortcut for catalog reads and may lag behind the product. Only a line without composition on a shop flagged as having no bundle skips the read.

## The cart line

A bundle line is a `cart_item` of the sale element of the bundle, with `bundle_composition`: the choice of the customer as canonical JSON, NULL on every other line.

```json
[{"bundleItemId":12,"quantity":"1"},{"bundleItemId":13,"quantity":"2"},{"bundleItemId":14,"quantity":"0.5"}]
```

- A list sorted by `bundleItemId`, one entry per item, the quantity a decimal string without trailing zeros, entries at quantity 0 dropped, no price. Two inputs expressing the same choice give the same JSON, which is what tells two lines of the same bundle apart: `findCartItem` merges lines of the same composition only.
- Unknown keys are ignored on reading. `Thelia\Domain\Catalog\Bundle\BundleComposition` parses, validates and writes it (`fromArray()`, `fromJson()`, `toCanonicalJson()`), and `CartItem::getBundleCompositionValue()` reads it.
- Without a composition on a bundle, the default composition is used. `Cart::duplicate` copies the composition and drops a line whose product no longer has the same shape (a bundle that stopped being one, an unreadable composition) or whose components no longer supply the quantity.
- A product that becomes or stops being a bundle loses its cart lines, on every path: `Product::postSave` deletes them in the transaction of the write when `is_bundle` changes, whether the switch comes from the admin API (`PATCH /api/admin/products/{id}`), the back office, `BundleCompositionService` or a module. A save that leaves `is_bundle` alone keeps the lines, and so does the validation of settings that writes nothing. This purge is a query, not a series of `CART_DELETEITEM` events: `cart.discount` keeps its old value until the next cart event recomputes it.
- The `cart` loop gives `STOCK` as the number of bundles the components can supply, and `attr('cart', 'cart_items')` adds `bundle_components` to each bundle line: `bundleItemId`, `productSaleElementsId`, `productId`, `title` and `quantity` for one unit of the bundle.

## Order lines

An order keeps the bundle as one priced line and its components as lines without a price. They are told apart by the `order_product.line_type` column (`OrderProduct::LINE_TYPE_BUNDLE = 'bundle'`, `LINE_TYPE_BUNDLE_COMPONENT = 'bundle_component'`) and linked by `order_product.parent`, which holds the id of the bundle line on each component line and is null everywhere else.

Example, two bundles ordered, the bike weighing 12.5 kg and the helmet 0.5 kg, the sale element of the bundle weighing nothing:

| id | line_type | parent | sale element | quantity | price | promo price | weight | taxes |
|---|---|---|---|---|---|---|---|---|
| 10 | `bundle` | null | the bundle's | 2 | 600 | 540 | 0 | the tax rule of the bundle product |
| 11 | `bundle_component` | 10 | the bike's | 2 | 0 | 0 | 12.5 | none |
| 12 | `bundle_component` | 10 | the helmet's | 4 | 0 | 0 | 0.5 | none |

The quantity of a component line is the component quantity times the quantity of the bundle line. A component line carries the title, reference, attributes, GTIN and MPN of the component, is never offered, and has no `order_product_tax` row. The `ORDER_PRODUCT_*_CREATE` events of a component carry no `cartItemId`. A bundle line without a composition that a promotion offers is ordered with the default composition, never as a plain product; a non-offered one without composition (a line written before the product became a bundle) is refused like a pack the catalogue no longer holds, by the cart step and by the order (`BundleLineNotOrderableException`), instead of being priced at the technical sale element with no stock taken from the components. Before the order, the cart step of the checkout reads every bundle line the way the order will (`CartGuard::checkBundleLinesOrderable()`): a pack whose composition the catalogue no longer holds (component deleted or offline, a required item added since, a quantity out of bounds) is reported with the violation code `bundle-line-not-orderable` and the buyer has to remove the pack and add it again. The checkout sends the buyer back to the cart with that message (`CheckoutProgressionService::firstViolation()`).

The same verdict is on the line before the checkout: `AttributeAccessService::cartItems()` (`attr('cart', 'cart_items')`) adds `bundle_orderable` (bool) and `bundle_refusal` (the translated reason the order would give) to each bundle line, computed by `BundleOrderPlanner::refusalsOf()`. A theme can show the reason on the line and ask the buyer to remove it. A cart with no bundle line costs no extra query. A deadlock or a changed row met while placing the order (`OrderPlacementConflictException`) answers 409 and the order can be placed again. The messages the buyer reads are in the core catalogs (`Config/I18n`).

How each gesture reads these lines (`OrderProduct` predicates):

| line_type | `isProductLine` | `isServiceLine` | `isBundleLine` | `isBundleComponentLine` | `isShippableLine` | `movesStockOfItsOwn` |
|---|---|---|---|---|---|---|
| `product` | yes | no | no | no | yes | yes |
| `service` | no | yes | no | no | no | yes |
| `bundle` | yes | no | yes | no | no | no |
| `bundle_component` | yes | no | no | yes | yes | yes |

- **Stock.** The sale element of the bundle is never decremented. The stock rows of the components are locked in ascending sale element id, at the placement, the cancellation, the stock adjustment of a status action and the return, so two orders sharing components cannot lock them in opposite orders. Every component is taken with `StockDecrementer` under the same guard and the same sale unit as a plain line. The pre-check adds the demand of the plain lines and of the components per sale element, so a bundle and the same component bought alone are checked together; the shortage names the component (`StockShortageException::$productReference`). Example: stocks 7 / 5 / 10, one bundle, so 6 / 3 / 8; a cancellation brings back 7 / 5 / 10.
- **Shipping.** Components are shipped, the bundle line is not: it never counts as left to ship (`ShippedQuantityCalculator`).
- **Returns.** The bundle line is the one the customer returns, refunded at its own price. A component cannot be returned alone (`ReturnNotAllowedException`, "This item is part of a bundle and cannot be returned on its own."). Receiving a returned bundle line puts the components back in stock in proportion to the quantity returned, honouring the restock mode and the `resellable` flag as for a product.
- **Restocking.** A cancellation, a paid transition, an `adjust_stock` status action and `Action\Order::updateQuantity` skip the `bundle` line and move the components.
- **Fingerprint.** A bundle line adds a `composition` key to the line of `OrderFingerprint`; other lines are unchanged.
- **Reorder from a purchase list.** `PurchaseListRepository::linesOfOrder` never takes the `bundle_component` lines.

### Reading these lines in an invoice or shipping module

- Sum the prices and taxes on `line_type != 'bundle_component'`: component lines have a price and a tax of 0, so a sum over every line is also right, but a list of "products bought" should show the bundle line and indent its components (`parent = <bundle line id>`).
- A parcel, a picking list or a weight computation reads the lines where `isShippableLine()` is true (plain, component), never the bundle line.
- The weight of an order is still the sum of quantity times weight over every line: it has no double counting.
- `GET /api/front/account/orders/{id}` exposes `lineType` and, new, `parent` on each order product.
- A line stored before the feature has `line_type = 'product'` and reads as it always did.

## Weight

`Cart::getWeight()` and the order use the weight of the sale element of the bundle when it is above 0 (the components then weigh 0 in the order), otherwise the sum of the weight of the kept components times their quantity. A bundle is virtual (`Cart::isVirtual()`) only when its sale element weighs nothing and every kept component is a virtual product without weight. A bundle line with no composition (offered by a coupon) weighs what its default composition weighs.

## Tax

The bundle line takes the tax rule of the **bundle product**; the component lines carry no tax. A bundle is taxed the way the merchant configured the product, whatever its components.

Mixed rates: a bundle that gathers components taxed at different rates is taxed as a whole at the rate of the bundle product. The tax base is then not the sum of what each component would have paid at its own rate, which can matter for VAT. The merchant checks the tax consequence of such a bundle and picks the tax rule of the bundle product accordingly.

## Coupons

A coupon applies to the bundle line as it does to a product, on the price of the line. Components are never discounted. A bundle offered by a coupon is checked against the availability of its default composition and weighs its default composition.

## API

### Product

`Product` gains `isBundle` (admin read and write, front read), `bundlePriceMode` (`fixed`, `computed`), `bundleDiscount` and `bundleDiscountType` (`percent`, `amount`), the last three on a single read (admin and front) and an admin write. A missing field leaves the stored value alone. `BundleSettingsConstraint` answers 422 with the same messages as the service (discount above 100 %, several sale elements, a component that is a bundle, a product that is a component).

### `product_bundle_items`

- Admin, right `product`: `POST` and `GET` on `/api/admin/product_bundle_items`, `GET`, `PUT`, `PATCH` and `DELETE` on `/api/admin/product_bundle_items/{id}`. Filters `id`, `bundleProduct.id`, `productSaleElements.id`; sort on `id` and `position`. Fields: `id`, `bundleProduct`, `productSaleElements` (null once the sale element is deleted), `quantity`, `minQuantity`, `maxQuantity`, `required`, `position`, `createdAt`, `updatedAt`. `PUT` refuses a different `bundleProduct` (422) and a different `productSaleElements` (422): an item keeps its sale element (`BundleDefinitionException::itemKeepsItsSaleElement`), so to change a component, delete the item and add a new one. An item whose sale element was deleted (`productSaleElements: null`) has none to keep: delete it.
- Front, read only: `GET /api/front/product_bundle_items` and `/{id}`. Only the sellable components of an online bundle are served, and a component reserved to a customer group is hidden; a hidden id answers 404.

```json
POST /api/admin/product_bundle_items
{"bundleProduct":"/api/admin/products/42","productSaleElements":"/api/admin/product_sale_elements/77","quantity":1,"minQuantity":0,"maxQuantity":3,"required":false}
```

Every write goes through `BundleCompositionService`, one transaction each; its refusals are `BundleDefinitionException` (422). The messages are translated (`Config/I18n`) and name the product by its reference, the id being used only when the product, the item or the sale element no longer exists.

### Adding a bundle to a cart

`POST /api/front/cart_items` takes `bundleComposition`, a list of `{bundleItemId, quantity}`, written by the front only and read back by admin and front (embedded in the cart too). Neither price nor stock is read from the body.

```json
POST /api/front/cart_items
{"cart":"/api/front/carts/9","productSaleElements":"/api/front/product_sale_elements/310","quantity":1,
 "bundleComposition":[{"bundleItemId":12,"quantity":1},{"bundleItemId":13,"quantity":2},{"bundleItemId":14,"quantity":2}]}

201 {"id":812,"quantity":1.0,"price":600.0,"promoPrice":540.0,"promo":1,
     "bundleComposition":[{"bundleItemId":12,"quantity":"1"},{"bundleItemId":13,"quantity":"2"},{"bundleItemId":14,"quantity":"2"}]}
```

`PUT /api/front/cart_items/{id}` refuses a composition different from the one of the line (422); the quantity is checked against the components. The admin cart item operations carry no composition field, so they cannot create a bundle line.

## Back-office events

`BundleCompositionService` is the only writer; the back office reaches it by events handled by `Thelia\Action\ProductBundleItem` (priority 128):

| Constant | Event | Carries |
|---|---|---|
| `TheliaEvents::PRODUCT_BUNDLE_ITEM_ADD` (`action.productBundleItemAdd`) | `ProductBundleItemEvent::add()` | bundle product, sale element, quantity, bounds, `required`, position |
| `TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE` | `ProductBundleItemEvent::update()` | item id and the whole new definition (a null bound clears it) |
| `TheliaEvents::PRODUCT_BUNDLE_ITEM_DELETE` | `ProductBundleItemEvent::delete()` | item id |
| `TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE_POSITION` | `ProductBundleItemPositionEvent` | item id and position |

`getItem()` is filled after add and update. A refusal surfaces as a `BundleDefinitionException` the controller catches to show its message. `ProductUpdateEvent` carries `isBundle`, `bundlePriceMode`, `bundleDiscount` and `bundleDiscountType` (null leaves the value alone), and cloning a product copies its settings and its components.

## A shop without bundles pays nothing

`BundleCatalogState` keeps the variable `catalog_has_bundles` of the configuration: `0` no bundle, `1` bundles none of which is computed, `2` at least one computed bundle. The configuration cache loads it once per request, so reading it costs no query, and a shop on `0` runs the queries it ran before. It is kept up to date by itself: `Thelia\Model\Product` recounts it whenever a product becomes or stops being a bundle or changes its price mode, so the admin API, the back office, the clone and the imports are all covered. The recount is written inside the transaction of the product; the shared configuration cache entry is dropped at the end of the request, of the command or of the job of a worker, handled or failed (`ModelConfigListener`), after the commit, so the next request rebuilds the state from what is committed. A cart line that holds a composition does not depend on this variable. A database that never had the variable has no bundle. The variable is seeded to `0` on install and update, and hidden.

## Shared cache

The page of a bundle is shared like the page of any product when the shared cache is enabled: `/api/front/product_bundle_items` is on the list of resources every visitor reads the same (`SharedCacheKeyCollector::READABLE_PREFIXES`), and a page that reads it is tagged `product_bundle_items`. Any change of a product purges that key, so a bundle follows the price, the visibility and the title of its components; a component added, changed, moved or deleted purges the bundle product, whichever way it was written (admin API, back office, clone of a bundle, module): the purge hangs on the Propel events `POST_SAVE` and `POST_DELETE` of `product_bundle_item`, not on the `PRODUCT_BUNDLE_ITEM_*` events, which the admin API does not dispatch. As the purge of any product also purges the `product_bundle_items` key, it reaches every bundle page of the shop, not only the pages of the product that changed. As for any product, the stock an order takes raises no catalogue event: the availability a shared page shows is bound by `shared_cache_ttl`.

## Deleting a component

`product_bundle_item.product_sale_elements_id` is `ON DELETE SET NULL`: deleting a sale element leaves the line in place and the API shows `productSaleElements: null`. A bundle whose required line lost its sale element is no longer sellable, and the back office flags it. Deleting the bundle product deletes its lines (`CASCADE`).

## Known limits

- **SQL sorts and filters on price or stock read the sale element of the bundle.** `min_stock` of the `product` loop, the `RangeFilter` on `quantity` of the API and the sort by quantity of the `product_sale_elements` loop see a stock of 0, so a bundle is left out of an "in stock" filter and shows in an "out of stock" one. Sorts and filters on `product_price` see the catalog price of the sale element of a computed bundle, not the computed price. The displayed price and quantity are right.
- **Catalog rules and sales do not stack on a computed bundle.** The computed price comes before them in `EffectivePriceResolver`; they apply to a fixed bundle as to any product. The customer discount applies to both.
- **Reorder from a purchase list takes the default composition.** `customer_list_item` stores a reference and a quantity, so a bundle bought with another composition comes back with the default one, never as bare components.
- **The displayed availability ignores the reserved visibility of a component.** A bundle with a required component reserved to a customer group shows a stock above 0 to a visitor outside the group, and the addition is refused.
- **A computed bundle takes the national price of its components.** The price of a component is its promotion price or its price in the currency of the request, as the national sale element gives it, even when the bundle is sold in a sale unit that sets its own prices: the prices of the sale unit are not read for the components.
- **The cart total still counts an invalid pack line.** A line flagged `bundle_orderable = false` stays in the total of the cart until the buyer removes it; only the cart step and the order refuse it.
- **A bundle holds no bundle.**
- A bundle offered by a coupon with no kept component is not checked against stock.
- The default PDF template prints the components under their bundle, with no price, from the release of the template that reads `LINE_TYPE` and `PARENT`; an older PDF template and the e-mail templates still list them as lines at 0.
