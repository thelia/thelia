# Upgrade from 3.3 to 3.4

## Breaking changes

These changes ship in a minor release, which departs from `BC-PROMISE.md`. They correct a price or a stock calculation, or close an access that the promise should not protect. No official module or theme depends on them. Each entry says what changed and what to do; `CHANGELOG.md` holds the full detail under "Breaking changes".

### Constructors and method signatures

A project or a module that extends, decorates or builds one of these classes by hand declares the new arguments and passes them on to `parent::__construct()`. A class built by the container, and a module that only reads a service from it, needs nothing. Optional arguments are added last, so only a subclass that overrides the method has to declare them.

- **Price lists, assortments and price display.** `EffectivePriceListener` and `ProductSaleElementsAccessService` (`PriceVisibilityResolver`, `PriceTierPublisher`), `EffectivePriceCollectionWarmer` and `ProductPriceOrderFilter` (`PriceVisibilityResolver`), and the `brand` loop `Brand` (`CatalogRestrictionResolver`, `CurrentCustomerProvider`) take new arguments with no default, after the existing ones.
- **Stock signals.** `StockIncrementer::incrementInAscendingId()` returns the list of `StockLevelChange` it made: a subclass that overrides it returns them. `Action\OrderReturn`, `Model\Cart::duplicate()`, `FreeProduct`, `StockReservationPurger` and `CartPurger` take an optional last argument; a caller that does not pass it dispatches no reservation event.
- **Stock reservation and preorder.**
  - `StockDecrementer::decrement()` returns a `?StockMovement` and takes a last `float $reservedByOtherCarts = 0.0`; `decrementAll()` is added, and a subclass overrides the protected `applyTake()`. `Action\Order::updateQuantity()` returns the `list<StockLevelChange>` it made and takes an optional fourth `?GetStockUpdateOperationOnOrderStatusChangeEvent`, which an override declares too.
  - `PSEFacade` (`StockLevelAnnouncer`), `CheckoutFacade` (`StockReservationService`), `ProductSaleElementsAccessService` (`VisitorSellableStock`) and `TheliaSchedule` (the cron of the purge) take a required argument.
  - Optional arguments are added last to `Action\Cart`, `Action\Order`, `Action\ProductSaleElement`, `OrderFacade`, `OrderProcessor`, `AdjustStockAction`, `AvailabilityFilter`, `FrontCartItemProcessor`, `QuickOrderFacade`, `OfferedCartLineService`, `CartLineAvailability`, `BundleComponentReader`, `Form\CartAdd` and the `cart` loop, and to `CartLineAvailability::forLine()` and `BundleComponentReader::read()`.
  - `StockShortageException` is no longer final and takes an optional message; `BundleComponent` takes a last `bool $unlimited = false`.
- **Express payment.** `CheckoutFacade` and `CheckoutPlacementProcessor` take `ExpressPaymentCheckoutGuard` as their last argument, with no default.
- **Product bundles.** `Action\Product` (`BundleCompositionService`), `OrderFacade` (`BundleOrderPlanner`, `BundleComponentReader`), `AttributeAccessService` (`BundleOrderPlanner`, `BundleCatalogState`), `PricingActivityChecker` (`BundleCatalogState`), `ProductSaleElementsAccessService` and `ReferenceResolver` (`BundleDisplayedStock`), `FrontCartItemProcessor` (`CartLineAvailability` and `CartCompositionResolver` in place of the optional `SaleUnitAvailableStock`) take new arguments with no default.
- **Customer groups and prices.** The CHANGELOG entry "New constructor arguments" lists the classes concerned, among them `Action\Cart`, `Action\Customer`, `Action\Order`, `Action\Category`, the loops `Product`, `ProductSaleElements`, `Category` and `Payment`, `ResourceCache`, `SharedCacheInvalidator`, `SharedCachePolicy`, `CartGuard`, `CheckoutPaymentService`, `OrderFactory`, `EffectivePriceCatalog`, `PricingActivityChecker` and `SaleAudienceChecker`. Read it with the classes your project extends at hand and declare the arguments each one now takes.
- **Promotion priority.** `CouponInterface` declares `getPriority(): int` and `isStopProcessing(): bool`. A promotion type that extends `CouponAbstract` inherits them (priority 0, no stop); one that implements the interface directly adds them. `CouponManager::__construct()` takes an optional `PromotionSelector` last.
- **Filters and counts.** `FilterService::filterTFilterWithRequest()`, `filterTFilterWithContext()`, `filterWithTFilter()`, `getCategoriesRecursively()` and `CategoryFilter::filter()` take an optional last `bool $openToVisitorOnly`; `Category::countAllProducts()` takes `?ProductVisibility $visibleToVisitor` and `array $closedCategoryIds` after `$productVisibility`. A subclass that overrides one of them declares the arguments.
- **Cart price refresh.** `Action\Cart::refreshCartItemPrices()` (protected) takes an optional `bool $bundleLinesOnly = false`. A subclass that overrides it with the old signature no longer loads.

  ```php
  // Before
  protected function refreshCartItemPrices(CartModel $cart, CurrencyModel $currency): void
  // After
  protected function refreshCartItemPrices(CartModel $cart, CurrencyModel $currency, bool $bundleLinesOnly = false): void
  ```

- **Reserved sales.** `ReservedSaleProductRule::visibleProductClause()` takes an optional `bool $defaultVisitor = false`. A subclass that overrides it declares the argument.
- **Back-office search (`default-twig`).** `SearchRepository::findProducts()`, `findCustomers()` and `findOrders()` are private; `findCategories()`, `findFolders()`, `findContents()` and `findBrands()` are removed. Read a group with `find(SearchGroup $group, SearchTerm $term, string $locale, string $fallbackLocale, int $offset = 0)`, which returns a `SearchGroupResult` (the rows of the page as arrays, and the total) instead of a Propel collection. `SearchResultsPresenter::buildResults()` takes the fallback locale as a third argument, with no default, then an optional `?SearchGroup $only` and `int $page`; its `groups` entry holds `SearchGroupResult` objects. `findSellableCombinations()` keeps its signature.

### Classes and methods changed or removed

- **`ModelConfigListener` is no longer `readonly`.** A subclass declared `readonly class` no longer loads: drop the modifier on the subclass.
- **`RecordOrderHistoryListener::rememberStatusBeforeChange()` is removed.** Read the status an order left from `OrderEvent::getPreviousStatusId()`, set by `Action\Order::updateStatus()` from the status read under the row lock. Stop relying on that listener at priority 192 of `ORDER_UPDATE_STATUS`.
- **`PricingActivityChecker`** answers from `CatalogActivityProbe`, and its `reset()` forgets what the probe and the customer discounts read. `hasVisitorDependentPricing()` is also true when a group has a price of its own or a discount. `mayPriceBeyondCatalog()`, `pricesDependOnWhoIsAsking()`, `hasCustomerGroupPrices()`, `hasDiscountedCustomerGroup()` and `hasDiscountedCustomer()` are new. Check any code that reads it.
- **Result extensions of the Propel collection provider.** `QueryResultCollectionExtensionInterface::getResult()` no longer runs every extension with the last answer winning: the first that answers something other than `null` is served, in the order of `getDefaultPriority()`. The pagination extension has priority -100, so it is asked last. An extension of a module at the default priority (0) is now asked, and served, before it: check that it answers `null` when it should leave the result to the others.
- **`CustomerGroup::preDelete()`** throws `DefaultCustomerGroupProtectedException` for the default group. A module that deletes groups catches it.
- **`CART_ADDITEM`** dispatched for a product the customer's group or a catalog rule hides now throws `ProductNotAvailableException`, as an addition by any other path does. A module that dispatches it catches the exception.
- **Discount rate range.** A discount rate outside 0 to 100 on a customer or a group is refused at write (`InvalidCustomerDiscountException` on `CUSTOMER_CREATEACCOUNT`, `CUSTOMER_UPDATEACCOUNT` and `CUSTOMER_UPDATEPROFILE`, 422 on the admin API) and ignored at read. A module that dispatches those events with a rate outside the range catches the exception.
- **Data access cache.** `ResourceCacheInvalidationListener::clear()` and the purge of `SharedCacheInvalidator` mark the pool instead of flushing it at once; it is flushed at `kernel.terminate`, `console.terminate` or the end of a Messenger message. The keys of the price paths change, so the cache starts cold after the upgrade. Code that expects the pool to be empty right after `clear()` reads it after the flush.

### Customers and the database

- **Every customer needs a group.** `customer.customer_group_id` is required and has no default in the database. A customer created through the model gets the default group (`Customer::preInsert()`). A module or an import that inserts into `customer` with SQL sets the column.
- **New right `admin.customer.group`.** It is granted to no existing profile: only super administrators reach the customer groups until you give the right to a profile.

### API

- **Front coupons removed.** `GET /api/front/coupons`, `/api/front/coupons/{id}` and `/api/front/coupons/{code}/by-code` answer 404. A customer enters a code through the cart. `Thelia\Api\Resource\Coupon` is no longer an API resource and its constants `GROUP_FRONT_READ` and `GROUP_FRONT_READ_SINGLE` are removed: a module that cites them, extends the resource through a resource addon or builds its IRI drops that use.
- **`confirmationToken`** is removed from the customer read representation (`/api/front/account/customers/{id}`). A client that read it from there uses the link sent by e-mail.
- **`POST /api/front/customers`** reads `firstname`, `lastname`, `email`, `password` and the optional `langId` (an integer) and `registrationToken`. `customerTitle` and a `lang` IRI are no longer read. The route used to answer 500 on every call, so no working client sends the old body.
- **Prices may be `null`.** A headless client reads `price`, `promoPrice` and their untaxed figures of a sale element as nullable: they are `null` when the shop does not show the price to the caller, with `priceDisplayMode` and `purchasable` telling why. `ProductPrice::getPrice()` and `getPromoPrice()` return `?float` and their setters accept `null`.
- **Payment refused once on a price change.** The payment of a cart whose line prices moved since they were written is refused once with `CartPricesChangedException` (409 `cart-prices-changed` on the checkout API) after the lines, the automatic-promotion discount and the postage are priced again. A front office that places orders shows the message and the cart again; the next attempt goes through.
- **Order status conflicts.** A change of order status locks the `order` row first, then the stock rows in ascending id, on every path. A deadlock (1213), or a row changed since it was read on MariaDB 11.6 and later (1020), is refused with `OrderStatusChangeConflictException` (409 on the API). A client retries.
- **`ProductSaleElements::$quantity` is a float.** `getQuantity()` and `setQuantity()` return and take a float. A class that extends the resource and overrides them, or reads the getter into an `int`, follows. The JSON `quantity` of a combination may carry decimals.

### Prices, totals and quantities

- **Prices change for customers with a discount or a group that prices anything.** The product loops, the front API and the cart apply the group price, the group discount and the customer discount read from the database; the front API now applies the customer discount, which it left out. A price converted from the default currency follows `rate / default rate` everywhere, which moves the loops, the price facet and the price sort of a shop whose default currency rate is not 1. Compare a few prices of discounted customers before and after the update.
- **Order loop totals.** `TOTAL_AMOUNT` and `TOTAL_TAX` of the `order` loop come from `Order::getTotalAmountWithoutTaxesWithShipping()` and `Order::getTotalTaxAmount()`, each rounded to the cent so that `TOTAL_AMOUNT + TOTAL_TAX = TOTAL_TAXED_AMOUNT`. They may differ by a cent from earlier versions. `BEST_PRICE_TAX` of the `product` loop is fixed for a product not on promotion.
- **Rounding to the cent.** `Order::getTotalAmount()`, the order export, the cart line totals (`CartItem::getTotalPrice()`, `getTotalTaxedPrice()` and their promo and real variants) and the refunds of a return (`RefundAmountCalculator`) round half up to the cent, through `MoneyRounding::toTheCent()`, so the figures are the same on PHP 8.3, 8.4 and 8.5. Code that adds up cart lines reads `CartItem::getSummedTotalRealPrice()` and `getSummedTotalRealTaxedPrice()`, and a return of several lines is priced by `RefundAmountCalculator::linesRefund()`: adding up the rounded line totals can come one cent above what was charged.
- **Stocks with decimals.** `Product::createProductSaleElement()` takes a `float` stock. A stock with more decimals than its combination is sold with is refused (`QuantityRefusedException`) by `Action\ProductSaleElement` and `PSEFacade`: a module that writes such stocks gives the combination its decimals first. The order line export has a `unit` column after `quantity`.
- **Rounding of sums.** Under `order_rounding_mode`, `Order::getTotalAmount()` and the export round each line total as a decimal. Orders already placed keep the total they were invoiced with: the update script stores the highest order id in `last_float_line_rounding_order_id`, and `ConfigQuery::isOrderWithFloatLineRounding()` tells the orders up to it.
- **Preorder stock.** `product_sale_elements.quantity` may go below zero for a sale element on preorder. A module that read it as what can be sold reads `SellableStock` instead. The `cart` loop `STOCK` is what the line can still be raised to; `Action\ProductSaleElement` refuses a negative preorder limit or threshold.
- **`CartEvent`.** `getQuantity()` returns `?float` and `setQuantity()` no longer cuts the quantity to a whole number: a listener that compares it with `===` to an integer, or passes it to an `int` parameter, follows. The protected property `CartEvent::$quantity` is a `?float` where it was a `?int`: a subclass that redeclares it declares `?float`.

  ```php
  // Before
  protected ?int $quantity = null;
  // After
  protected ?float $quantity = null;
  ```

### Themes and AI connections

- **Official themes.** Update Flexy (1.4.0), back-office (1.4.0), e-mail (1.4.0) and PDF (1.5.0) together with the core: all four require `thelia/core ^3.4`.
- **Flexy.** A child theme that overrides one of these constructors declares the new argument and passes it to `parent::__construct()`: `Summary\Checkout` takes `ExcludedPromotionsReader` last; `NextButton\Base` takes `Symfony\Component\DependencyInjection\ContainerInterface` after `DeliveryDateBridge`, before the `stepProviders` iterable.
- **Flexy quantities by measure.** `ProductDetails\Base::getRemainingStock()` returns an `int|float` and `CartItemDto::$stock` loses its type. `ProductSaleElementDTO::$quantity`, `CartItemDto::$quantity`, `OrderProductDTO::$quantity` and `AddToCartToast\Base::$quantity` are floats. `Cart\Base` takes the quantity as a `float` in `onQuantityChanged()`, `minus()`, `plus()`, `restoreCartItem()` and the protected `dispatchQuantityChange()`, and moves a line by the step of its combination. `Cart\Base::getTotalItems()`, `Summary\Order::getItemCount()` and the counts of `CartButton\Base` and `Summary\Checkout` give the number of lines, not the sum of quantities, once a line is sold by measure. A child theme that overrides one of these methods or reads one of these properties into an `int` follows.
- **AI connection with its own base URL.** A connection that reads its key from `THELIA_AI_X_KEY` or `THELIA_AI_X_TOKEN` and calls a base URL of its own (an OpenAI-compatible server, a self-hosted Ollama, a proxy) stops at its next call, test and save, with a message that names the variable to set. Set `THELIA_AI_X_BASE_URL` to the base URL, next to the key, where the server sets its variables. A connection to the usual address of its provider, or whose key is saved in the settings, has nothing to do.

## Deprecations

### `ZipCodePattern::normalize()`

`Thelia\Domain\Shipping\ZipCode\ZipCodePattern::normalize()` is deprecated since 3.4. It still returns what it returned in 3.3 (spaces and dashes removed, ASCII letters in upper case, always a string) and triggers a deprecation.

Replace it with `ZipCodePattern::normalForm()`, which folds compatibility characters (a full-width `２０` is `20`), removes invisible format characters, writes the digits of every script in ASCII, and returns `null` for a code that has no normal form (empty once its spaces and dashes are removed, a letter outside `A` to `Z`, a control character, invalid UTF-8).

```php
// Before
$code = ZipCodePattern::normalize($zipCode);

// After
$code = ZipCodePattern::normalForm($zipCode);
if (null === $code) {
    // the code cannot be checked: decide what the rules do with it
}
```

To only know whether a code is in a zone, call `ZipCodePattern::matches()`, which judges such a code itself.

## Translations

### `Customer title` translation key

The title field (Mr, Mrs) of the registration, address and customer update forms (`CustomerCreateForm`, `CustomerUpdateForm`, `AddressCreateForm`) is now labelled with the key `Customer title` instead of `Title`. `Title` keeps labelling the heading of a product, a category or a content. The core catalogs that translated `Title` ship `Customer title` with the same value, so a shop that does not override anything sees no change.

A shop that overrides `Title` in `local/I18n/{locale}.php` to change the label of the customer title must now declare `Customer title` as well, otherwise the forms fall back to the catalog value.

```php
return [
    'Title' => 'Salutation',
    'Customer title' => 'Salutation',
];
```
