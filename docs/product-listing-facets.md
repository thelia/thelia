# Product listing facets

The column of filters beside a product listing (brand, category, feature values, attribute
values, promotion, newness, availability, customer rating, price) is computed by the core and
drawn by the theme. Each value shows how many products it would keep once the other filters
apply, and a checked value keeps its siblings on offer.

This document is the map for developers who work on the facets. The merchant side is the
"Filters" section of a category or of a product template in the back office, where each facet
can be hidden, moved, and drawn as checkboxes, radio buttons, a text field or a slider.

## How a facet is built

| Piece | Where |
|---|---|
| A facet | a service implementing `TheliaFilterInterface` (tag `api.thelia.filter`, autoconfigured), under `core/lib/Thelia/Api/Bridge/Propel/Filter/CustomFilters/Filters/` |
| Its counts | `TheliaAggregatedFilterInterface::getAggregatedValues()`: one query for the whole listing |
| The facet column | `FilterService::getFilters()`, served by `/api/front/tfilters/products` |
| The narrowing | `FilterService::filterWithTFilter()`, from the `tfilters` query parameter |
| The merchant settings | a `choice_filter` row per category or template, tied to the facet by the `type` of a `choice_filter_other` row |

A facet with no value to offer is not shown. A listing outside any category, brand or ranked
set of ids gets no facet at all.

A facet that is not attached to a feature or an attribute needs a `choice_filter_other` row to
be reachable from the back office: `setup/insert.sql.tpl` for a fresh install and the pending
update script for an upgraded shop, matched by `type` and never by id.

## Availability

`AvailabilityFilter`, type `availability`, two values: `1` in stock, `2` on order or out of stock.

A product is in stock when it is virtual, or when one of its visible combinations has a
positive quantity: one available size is enough, as for the promotion facet. Everything else is
on order or out of stock, a product without any combination included.

When the shop does not check stock (`check-available-stock` set to `0`), every product can be
ordered and the whole listing is in stock.

## Customer rating

`RatingFilter`, type `rating`, three values that are thresholds: `4`, `3` and `2` stars and up,
on a five-star scale. Two thresholds checked together read as the lower one.

The core holds no review. The module that collects reviews implements
`Thelia\Domain\Catalog\Product\ProductRatingSourceInterface` (tag
`thelia.catalog.product_rating_source`, autoconfigured):

```php
final readonly class RatingFacetSource implements ProductRatingSourceInterface
{
    public function productIdsRatedAtLeast(float $minimumRating, ?array $amongProductIds = null): array
    {
        // the products whose published average reaches $minimumRating out of five
    }
}
```

Without an implementation the facet stays inert: it offers no value, a query string naming it
changes nothing, and `FilterService::withheldFilterNames()` lists it so that the back-office
screen hides its row (`TheliaOptionalFilterInterface`). The Comment module is such a source: it
compares the stored average its best-rated order sorts on, brought to five stars when the shop
rates on another scale.

## Price

`PriceFilter`, type `price`, drawn as a slider (`delta`) unless the merchant picks another
display. Its two values are the bounds of the slider, the cheapest and the dearest product of
the listing rounded outwards to whole units; the selection comes back as `min` and `max`, and a
single-handle slider (`range`) sends one figure read as the maximum.

The price compared is the one the product card shows:

- the default combination;
- its promotional price when it is on sale, or the price a catalog price rule or a reserved sale
  gives the visitor (`EffectivePriceCatalog`, as `EffectivePriceListener` does for the API);
- in the currency the shop is browsed in (`ProductPriceCurrencyListener::currentCurrency()`), the
  default currency's price converted when none was typed;
- taxes of the visitor's delivery country included (`TaxEngine::getDeliveryCountry()`), computed
  product by product since a tax can read a feature of the product.

Prices are computed in PHP and kept for the request. Narrowing the listing adds a condition on
the product ids and joins nothing, so the order the listing is sorted in is untouched.

## Tests

| Subject | Test |
|---|---|
| Counts, sibling values, scopes | `tests/Integration/Api/FacetSelectionTest.php` |
| Availability | `tests/Integration/Api/AvailabilityFacetTest.php` |
| Customer rating, with and without a source | `tests/Integration/Api/RatingFacetTest.php` |
| Price | `tests/Integration/Api/PriceFacetTest.php` |
| Rows of an upgraded shop | `tests/Integration/Install/ChoiceFilterOtherListingFacetRowsMigrationTest.php` |
