# Discount lines of a module

A module can take an amount off a cart under its own name (a gift card, a store
credit, a loyalty reward). The cart lists it beside the promotions in its
`discounts`, adds it to its `discount`, and the order freezes it in
`order_discount_line` when it is placed. A theme prints the line like any
promotion, without knowing the module.

## Declaring lines

Implement `Thelia\Domain\Cart\Discount\CartDiscountLineProviderInterface` on a
service of the module. The container tags it; the core reads every provider at
each pricing of the cart.

```php
final readonly class StoreCreditDiscountLines implements CartDiscountLineProviderInterface
{
    public function __construct(private StoreCreditRepository $credits)
    {
    }

    public function discountLinesFor(Cart $cart): iterable
    {
        $amount = $this->credits->amountSpentOn($cart);

        if ($amount > 0.0) {
            yield new CartDiscountLine('StoreCredit.balance', 'Store credit', $amount);
        }
    }
}
```

- The key identifies the line for good: prefix it with the module code, 64
  characters at most. Two lines with the same key on one cart are refused.
- The label is what the buyer reads, already translated.
- The amount is tax included, the way the cart discount is stored. The core
  derives the untaxed part with the same factor it applies to the promotions.
- The provider is read often: keep it to what the cart and the buyer already
  hold, no remote call.

The module never writes `cart.discount` itself. The core writes it again at each
change of the cart and would drop the amount.

## When the lines change without the cart

When the buyer chooses to spend a balance, nothing in the cart moved. Dispatch
`TheliaEvents::CART_DISCOUNT_LINES_CHANGED` with a `CartEvent` on the session
cart: the core prices the discount again.

## How the amounts add up

The cart discount is the promotions plus the module lines, never more than the
goods are worth (postage is not discounted). When it reaches that ceiling, the
module lines are served first, each up to its amount, and the promotions share
what is left.

| Goods | Promotion | Module line | `cart.discount` | Listed |
|---|---|---|---|---|
| 120.00 | 5.00 | 3.00 | 8.00 | promotion 5.00, line 3.00 |
| 12.00 | 5.00 | 11.00 | 12.00 | promotion 1.00, line 11.00 |

## On the order

`OrderFacade::createOrder()` writes one `order_discount_line` row per line
granted something, in the order transaction: `line_key`, `label`, `amount`
(tax included, what the line was granted). `order.discount` stays the cart
discount, so the totals of an order do not change. The invoice and the order
page read the rows through `Order::getOrderDiscountLines()`; the module charges
the buyer's balance with the amount of its row, not with what its provider
would say later.

## Tests

`tests/Integration/Domain/Cart/ModuleDiscountLinesTest.php`.
