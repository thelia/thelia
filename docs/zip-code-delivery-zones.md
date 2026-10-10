# Postal codes of delivery zones

A delivery zone (`area`) holds countries and states, and the carriers linked to it
(`area_delivery_module`) deliver there. Postal code rules narrow a zone down: a carrier
is offered, quoted and accepted for an address only when one of its zones covering the
country takes the postal code of the address.

This document is the map for developers who work on the feature. The behaviour itself
is specified by the test suites named below.

## Rules

A rule is a row of `area_zip_code_rule`: a zone, a pattern, whether it excludes, and
optionally one country of the zone (no country: every country of the zone).

`Thelia\Domain\Shipping\ZipCode\ZipCodePattern` reads a pattern:

- an exact code, `75001`;
- a pattern where `*` stands for any characters, `20*` or `75*1`;
- a range of numeric codes, `75001...75020`, bounds included.

Codes and patterns are compared in their normal form: upper case, without spaces or
hyphens. `SW1A 1AA` matches `sw1a1aa`, `1000-001` matches `1000 001`. A pattern that can
match nothing (empty, only `*`, a range with letters or upside down) is refused when it
is written, and ignored with a log line if one reaches the table by another way.

`ZipCodeRuleSet` applies the rules of a zone for one country:

- a zone without a rule takes every postal code, as zones always did;
- a code matching an exclusion is refused, whatever the inclusions say;
- when the zone has inclusions, a code must match one of them;
- an unknown postal code is accepted.

## Where it applies

`AreaDeliveryModuleQuery::findByCountryAndModule()` takes an optional postal code. With
one, a zone whose rules refuse the code is skipped, and the next zone of the carrier
covering the country is tried: a carrier with a mainland zone excluding `20*` and a
Corsica zone including it serves both. Without a postal code, the rules are not read.

The postal code is passed by:

- `DeliveryModuleEligibilityChecker::isEligible()`, and through it
  `ShippingFacade::listValidMethods()`, `listValidMethodsAsResourceApi()` and
  `DeliveryModuleResourceBuilder::build()`;
- `CartGuard::checkValidDelivery()`, from the cart's copy of its delivery address, so an
  order cannot be placed with a carrier the rules refuse, whatever the client sent;
- `PostageEstimator::estimatePostageForCountry()`: when no postal code is given, it uses
  the one of the cart's delivery address if that address is in the country estimated for;
- the `delivery` loop, when the address it reads is in the country of the loop.

The postal code goes with the address it belongs to: the cart's copy for the cart's own
address (a guest's cart address points to no customer address), the customer address for
an `addressId`, also when a country is given next to it as the API does, provided the
address is in that country. A country given alone carries no postal code.

`ShippingFacade::postalCodeWithoutCarrier()` names the postal code of the cart's
address when it is the reason no carrier is offered: some active carrier serves the
country, and the rules of its zones refuse that code. Flexy shows it on the delivery
step instead of the general message.

## Limits

- The rules decide who delivers, not at which price. A carrier that prices by zone
  (`AbstractDeliveryModule::getAreaForCountry()`) still reads the first zone covering the
  country, without the postal code.
- A module that decides eligibility on its own (`isValidDelivery()`, the postage event)
  is not affected by the rules of the zones it is not linked to.
- The radius around a shop (GeolocatedDelivery in Thelia 2) is a separate feature.

## Back office

The page of a shipping zone (Configuration, Shipping zones) lists its rules and adds or
deletes one, under the `admin.configuration.area` resource.
`Thelia\Domain\Shipping\ZipCode\AreaZipCodeRuleWriter` refuses a pattern that matches
nothing and a country the zone does not hold.

## Tests

- `tests/Unit/Domain/Shipping/ZipCode/`: patterns and rule sets.
- `tests/Integration/Domain/Shipping/ZipCodeDeliveryZoneTest.php`: eligibility, the
  checkout list, the API list, the guard, the estimate and the reason.
- `tests/Integration/Domain/Shipping/AreaZipCodeRuleWriterTest.php`: writing a rule.
- `tests/Http/BackOffice/ShippingZoneZipCodeRulesTest.php` and
  `tests/Http/Flexy/DeliveryPostalCodeMessageTest.php`, skipped when the installed theme
  does not ship the screen.
