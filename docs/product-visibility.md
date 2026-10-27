# Product visibility

`Thelia\Domain\Catalog\Product\ProductVisibility` decides which products of the catalog a front visitor sees. It joins
two kinds of rules with AND, and a product is visible when each of them lets it through:

1. the private drops of the reserved operations, `Thelia\Domain\Sale\ReservedSaleProductRule` (see
   [reserved-sales.md](reserved-sales.md));
2. every rule a module declares through `Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface`, such as a
   catalog closed brand by brand for each customer.

A third source joins them since the customer groups: the categories the group of the visitor is restricted to
(`ClosedCategoryProductRule`, see [Categories closed to a customer group](#categories-closed-to-a-customer-group)).

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
| Theme sitemap (Flexy) | yes, as the default group sees the catalog (`ProductVisibility::visibleProductClauseForDefaultVisitor()`), whoever generates it: the sitemap is cached for everybody |
| Prices, attribute combinations, feature values, associated contents, images and documents read by the id of a product or of a combination, and the links between a combination and an image | yes, a hidden product answers 404 on them and the collection leaves it out |
| Categories read by the id of a product | the categories closed to the group are left out (see [Categories closed to a customer group](#categories-closed-to-a-customer-group)) |
| Products listed by a sale (`/api/front/sales`) | no |
| `feature_value` loop | no |
| Adding to the cart through the front API (`POST /api/front/cart_items`) | yes, a hidden combination answers 422: see [The cart](#the-cart) |
| Adding to the cart by id (`CartFacade::addItem()`, the theme, live components, a module dispatching `CART_ADDITEM`) | yes, a hidden product is refused: see [The cart](#the-cart) |
| Lines already in a cart | yes, removed at sign-in and at the restore of the cart: see [The cart](#the-cart) |

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

## Categories closed to a customer group

Each group lists the categories it may browse (`customer_group_category`). The list is read only when the group is
restricted (`customer_group.restricts_categories`, set by the write of the list: a non-empty list sets it, an empty list
clears it). A group that is not restricted sees the whole catalog. A restricted group sorts every category in three, for
the members of that group only:

- open: the categories listed and their whole sub-trees;
- passing: the ancestors of a listed category that are not open themselves. They stay on the menu, in the breadcrumb
  and on their own page, so that an open sub-category can be reached, but the products filed in them are visible only
  when an open category holds them too;
- closed: every other one, hidden altogether.

The list of a restricted group can be empty, when the only category it listed was deleted. Nothing is then open and
nothing is passing: every category is closed, and only the products filed in no category stay visible. The group stays
shut out until an administrator edits the list or lifts the restriction in the back office. Emptying the list by hand
through the API or the back office clears the flag instead, and opens the whole catalog again.

A product is visible when at least one of its categories is open. A product filed in no category stays visible to every
group, as before the groups existed: a merchant who files nothing restricts nothing. The list of one group never opens
nor closes anything to another group.

An example, the tree Living Room > [Armchairs, Sofas], Dining Room > [Chairs, Stools], the professionals listing Sofas:
Sofas is open, Living Room is passing, Armchairs and the whole Dining Room are closed. A sofa is visible to them, an
armchair is not, and a product filed in Living Room alone is not either. The default group, with no list, sees
everything. If the professionals' only listed category, Sofas, is deleted, they stay restricted with an empty list: every
category is closed to them and they see only the products filed in no category.

`Thelia\Domain\Catalog\Category\CategoryVisibility` is the one place that sorts the categories for the current visitor
(a visitor, or a guest, is the default group; a customer is read back from the database, not from the session object):
`closedCategoryIds()` and `closedCategoryIdsFor()` answer the closed ones, `openCategoryIdsFor()` the open ones (null
when the group is not restricted). It is read by:

- `CategoryVisibilityExtension`: `/api/front/categories`, `/api/front/category_images`,
  `/api/front/category_documents` and the product links of a category, collection and item (404 on a closed one);
- `ProductCategoryVisibilityListener`: the `productCategories` a product embeds leave out the closed categories;
- `Action\Category::viewCheck`: the page of a closed category answers 404;
- the `category`, `category-tree`, `category-path`, `image`, `document` and `associated_content` loops, outside the
  back-office context (the child count of a category leaves out the closed ones, and its product count the products of
  the passing and closed categories, and the ones reached only through a closed sub-category);
- the category facet and filter of `/api/front/tfilters/products`;
- `ClosedCategoryProductRule`, joined by `ProductVisibility` with the rules above: the product rule, also applied to the
  product links of a category (`/api/front/product_categories/{product}/categories/{category}`);
- the Flexy sitemap, for the default group: `ProductVisibility::visibleProductClauseForDefaultVisitor()` gives the product
  condition whoever generates the file, so the categories of a restricted member never shape it.

## The data access cache

The cross-request cache of the data access layer keys on the path, the format and the locale, not on the visitor. While
a module rule is declared, `/api/front/products` and `/api/front/product_sale_elements` step aside from it, as they do
while a reserved operation runs.

The categories a customer group is restricted to do not count as such a rule (`ProductVisibility::hasModuleRules()`):
while at least one group is restricted to categories, the catalog paths are kept on the cache with the group of the visitor in their key,
and a shop where no group is restricted keeps the keys it had.

## The cart

The cart holds only what the catalog shows its visitor. The addition is narrowed on every path: the front API
(`POST /api/front/cart_items`, where a hidden combination answers 422, like one that does not exist), `CartFacade::addItem()`,
the live components of the theme and a module that dispatches `TheliaEvents::CART_ADDITEM`. The product is judged on the
sale element of the line, and a hidden one is refused with a `Thelia\Domain\Cart\Exception\ProductNotAvailableException`
(an `InvalidCartException`). A module that forbids buying a product still adds its own rule through
`ProductVisibilityRuleInterface`; it no longer needs a listener of `CART_ADDITEM`.

A line that the customer no longer sees is taken out of the cart when the customer signs in (`CUSTOMER_LOGIN`) and when the
cart is restored from its cookie, and a notice is left in the `error` flash bag of the session. Before the payment,
`CartGuard::checkLinesAreAvailable()` refuses a cart that still holds such a line: the checkout answers with the violation
code `cart-line-unavailable` and the ids of the products and of the cart lines in `productIds` and `cartItemIds`. The
visibility is read in one query per cart, and none when no rule restricts anything. The lines a promotion offers
(`is_offered`) are never judged. A hidden line can be reduced or removed, never increased (`CART_UPDATEITEM` and
`PUT /api/front/cart_items/{id}` refuse a higher quantity), and the payment refuses it. The sign-in and the cookie restore are
detailed in [customer-groups.md](customer-groups.md#the-cart-at-sign-in).

Example. The professionals are restricted to Sofas and have a table in their cart from before the restriction. At their
next sign-in the table line is removed and the notice tells them so. A forged `formValues` that adds the table is refused.

## Test map

- `tests/Unit/Domain/Catalog/Product/ProductVisibilityTest` (order of the rules, customer given, wiring by the tag)
- `tests/Unit/Domain/Sale/ReservedSaleVisibilityDelegationTest`
- `tests/Unit/Api/Service/API/ResourceCacheTest`
- `tests/Integration/Domain/Sale/ReservedSaleVisibilityTest` (a module rule beside a hidden operation)
- `tests/Integration/Api/TFiltersVisibleProductsTest` (listing facets, with a reserved operation and with a module rule)
- `tests/Integration/Api/ProductVisibilityWiringTest` (the services the container builds share one rule and are given the visibility)
