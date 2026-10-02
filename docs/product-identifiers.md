# Product identifiers: GTIN and manufacturer part number

A combination (`product_sale_elements`) carries three identifiers besides its own reference:

| Column | Meaning | Check |
|---|---|---|
| `ean_code` | The GTIN of the item: EAN-8, UPC-A (12 digits), EAN-13 (ISBN-13 included) or GTIN-14. The length tells which one. | Digits only once spaces and hyphens are dropped, one of the four lengths, GS1 check digit. Empty is accepted. |
| `mpn` | The manufacturer part number, which merchant feeds ask for beside the GTIN. | Trimmed, 255 characters at most, empty stored as null. |
| `manufacturer_brand_id` | The brand that makes the item, when it is not the brand of the product. | Foreign key to `brand`, set to null when the brand is deleted. |

The column keeps its historical name `ean_code`, and the API property its name `eanCode`: renaming them would break every module and integration that writes them, for a cosmetic gain.

## Where the check runs

`Thelia\Model\ProductSaleElements::preSave()` checks `ean_code` and `mpn`, so every writer goes through it: the back office, the API, the stock import, the cloning of a product, and a module saving the model itself. A refusal is an `InvalidGtinException` or an `InvalidMpnException`, whose message names the combination and the rule broken.

Only a value that changes is checked. A shop holds codes saved before the check existed, some of them invalid: an edit that leaves the code alone saves, and the code stays as it is. The API constraint `GtinConstraint` follows the same rule and answers a 422 on the `eanCode` field.

The code is stored normalized, and always as a string: a numeric cast would eat the leading zero of a UPC-A.

## Duplicates

Two combinations of the same physical item may carry the same GTIN, so a duplicate is never refused and there is no unique index. It is reported:

- in the back office, by a warning after the save and next to the code in the combinations tab;
- on the admin API, by `gtinSharedWith`, the ids of the other combinations carrying the code. The front API never shows it.

`Thelia\Domain\Catalog\Product\Identifier\GtinDuplicateFinder::sharersAmong()` answers for a batch of combinations in two queries.

## Order lines

`order_product.ean_code` and `order_product.mpn` are copied from the combination when the order is placed. The invoice, the order lines export and the API read them there, so a code corrected in the catalogue afterwards does not rewrite what was sold.

## For the modules that build feeds

```php
use Thelia\Domain\Catalog\Product\Identifier\ProductIdentifierReader;

$identifiers = $reader->forSaleElements($combinationIds); // one query per batch

foreach ($identifiers as $id => $item) {
    $item->gtin;                // ?string
    $item->mpn;                 // ?string
    $item->manufacturerBrandId; // ?int, the brand of the product when the combination has none
}
```

## Searching

- Admin API: `GET /api/admin/product_sale_elements?eanCode=4006381333931`, `?mpn=…`, and `GET /api/admin/products?productSaleElements.eanCode=…`. Exact match: a partial code finds nothing.
- Back office: the search box of the product list finds a product by the GTIN of one of its combinations, typed with or without spaces, and by the start of a manufacturer part number.
