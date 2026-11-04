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
