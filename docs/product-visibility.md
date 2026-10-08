# Product visibility

`Thelia\Domain\Catalog\Product\ProductVisibility` decides which products of the catalog a front visitor sees. It joins
two kinds of rules with AND, and a product is visible when each of them lets it through:

1. the private drops of the reserved operations, `Thelia\Domain\Sale\ReservedSaleProductRule` (see
   [reserved-sales.md](reserved-sales.md));
2. every rule a module declares through `Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface`, such as a
   catalog closed brand by brand for each customer.

The rules narrow the query itself, so a collection, an item read and a count agree on what the catalog holds: a product
left reachable by its id is not hidden, it is only harder to find.

## Where it applies

| Reader | Narrowed |
| --- | --- |
| `/api/front/products`, `/api/front/product_sale_elements` (collections and items) | yes, by `ProductVisibilityExtension` |
| `/api/front/product_associations`, `/api/front/product_videos` and their links | yes |
| Product page (`product` view) | yes, a hidden product answers 404 |
| Combinations of a product (`ProductSaleElementsAccessService`) | yes |
| Quick order, and a purchase list loaded into it (`ReferenceResolver`) | yes; the purchase list itself is not |
| `product`, `accessory` and `product_sale_elements` loops | yes, front only |
| Listing facets (`/api/front/tfilters/products`) | yes, a hidden product feeds no facet and is not counted |
| Theme sitemap (Flexy) | yes, through `ReservedSaleVisibility`, for the visitor who generates it: the sitemap is then cached for everybody |
| Images, documents, prices, features, attribute combinations, categories and associated contents read by the id of a product or of a combination | no |
| Products listed by a sale (`/api/front/sales`) | no |
| `image`, `document` and `feature_value` loops | no |
| Adding to the cart | no: see [The cart](#the-cart) |

The back office and the admin API are never narrowed: the readers above only ask on a front operation, outside the
back-office context.

## Writing a rule

A module declares a service implementing the interface. Nothing else is needed: the interface carries
`#[AutoconfigureTag(ProductVisibilityRuleInterface::TAG)]`, and a module whose services are autoconfigured in
`configureServices()` is collected.

```php
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Model\Customer;

final class ClosedBrandsRule implements ProductVisibilityRuleInterface, ResetInterface
{
    /** @var array<int, list<int>> closed brand ids by customer id, 0 for an anonymous visitor */
    private array $closedBrandIdsByCustomer = [];

    public function __construct(private readonly ClosedBrandRepository $closedBrands)
    {
    }

    public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
    {
        $customerId = (int) ($customer?->getId() ?? 0);
        $brandIds = $this->closedBrandIdsByCustomer[$customerId] ??= $this->closedBrands->ofCustomer($customerId);

        if ([] === $brandIds) {
            return null;
        }

        return \sprintf(
            'NOT EXISTS (SELECT 1 FROM product closed_brand_product WHERE closed_brand_product.id = %s AND closed_brand_product.brand_id IN (%s))',
            $productIdColumn,
            implode(', ', array_map('intval', $brandIds)),
        );
    }

    public function reset(): void
    {
        $this->closedBrandIdsByCustomer = [];
    }
}
```

The rule is asked on every front read of the catalog, several times per request:

- **The customer is given.** It is the one the reserved operations see: the session, then the bearer token of the
  front API. It is null for a visitor who is not signed in or checks out as a guest, and the rule answers for that
  visitor too.
- **The condition is written into the statement as it is.** Nothing is bound: every value is an integer read from the
  database or cast with `(int)`, never a value taken from the request or the session, and there is no placeholder.
- **The column is the only anchor.** The query may read `product`, `product_sale_elements`, `accessory` or a subquery,
  and `product` is not necessarily joined: reach product data through a correlated subquery whose tables are aliased,
  as above, or the column given resolves against the subquery's own table.
- **null when the rule hides nothing.** An empty clause raises a `LogicException` naming the rule.
- **Memoize, and reset.** What the rule reads is kept for the request, and `ResetInterface` (autoconfigured as
  `kernel.reset`) empties it, so that a worker serving several requests does not carry one customer's catalog over to
  the next. A rule reads its data with plain queries, never through a front reader of the catalog, which would ask it
  again.

## The data access cache

The cross-request cache of the data access layer keys on the path, the format and the locale, not on the visitor. While
a module rule is declared, `/api/front/products` and `/api/front/product_sale_elements` step aside from it, as they do
while a reserved operation runs.

## The cart

A rule narrows what is read, not what can be bought: adding a combination to the cart reads it by its id. A module that
forbids buying a product also listens to `TheliaEvents::CART_ADDITEM` with a priority above 128, the one
`Thelia\Action\Cart::addItem` adds the line at, and throws a `Thelia\Domain\Cart\Exception\InvalidCartException`.

## Test map

- `tests/Unit/Domain/Catalog/Product/ProductVisibilityTest` (order of the rules, customer given, wiring by the tag)
- `tests/Unit/Domain/Sale/ReservedSaleVisibilityDelegationTest`
- `tests/Unit/Api/Service/API/ResourceCacheTest`
- `tests/Integration/Domain/Sale/ReservedSaleVisibilityTest` (a module rule beside a hidden operation)
- `tests/Integration/Api/TFiltersVisibleProductsTest` (listing facets, with a reserved operation and with a module rule)
- `tests/Integration/Api/ProductVisibilityWiringTest` (the services the container builds share one rule and are given the visibility)
