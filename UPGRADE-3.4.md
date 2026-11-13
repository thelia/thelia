# Upgrade from 3.3 to 3.4

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
