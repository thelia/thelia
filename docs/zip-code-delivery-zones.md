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
Corsica zone including it delivers to both. It does not charge Corsica the price of the
Corsica zone for all that: the rules decide which carriers deliver, the carrier decides
its price (see Limits). Without a postal code, the rules are not read.

`AreaZipCodeRuleQuery::ruleSetFor()` keeps the rules it read for the request: a checkout
page that lists, estimates and checks the carriers reads the rules of a zone once. A rule
saved or deleted through the model drops them, and so do a new request, a console
command and the reset of the services between two messages of a worker
(`ZipCodeRuleCacheListener`). A bulk write on `AreaZipCodeRuleQuery`, which skips the
model hooks, is seen from the next request on.

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
address (a guest's cart address points to no customer address), whose country and state
are read too, as `CartGuard` reads them; the customer address for an `addressId`, also
when a country is given next to it as the API does, provided the address is in that
country and belongs to the customer of the cart (a guest's own customer row included). A
country given alone, or an address of someone else, takes the postal code of the cart's
delivery address when that address is in the country, as the postage estimate does.

A postal code that is not valid UTF-8 normalises to nothing, and counts as unknown.

A rule with `*` runs as a regular expression. Should PCRE give up on it (its backtrack
limit), the failure is logged and an exclusion refuses the code, an inclusion does not
take it.

`ShippingFacade::postalCodeWithoutCarrier()` names the postal code of the cart's
address when it is the reason no carrier is offered: some active carrier would be offered
without it (its zones serve the country and it quotes the cart), and the rules of its
zones refuse that code. Flexy shows it on the delivery step instead of the general
message.

## Limits

- The rules decide who delivers, not at which price. A carrier prices the address the way
  it always did, without the postal code: `AbstractDeliveryModule::getAreaForCountry()`
  still answers the first zone of the carrier covering the country, and CustomDelivery
  takes its price slice among all the zones of the country
  (`CustomDelivery::getSlicePostage()`). A mainland zone excluding `20*` and a Corsica
  zone including it therefore make the carrier deliver to Corsica, at a price that need
  not be the one written for the Corsica zone. A price by postal code is the job of the
  carrier module.
- On a country without a postal code format (`country.zip_code_format` empty, or
  `need_zip_code` off, in which case the format is not checked), the customer can type any
  code, or none. An empty code is unknown and accepted by every zone, a mistyped one
  matches no exclusion: both pass the exclusions, and only inclusions hold. The back
  office warns on the card of a zone whose rules apply to such a country.
- A rule written for a country goes with the country when it leaves the zone
  (`TheliaEvents::AREA_REMOVE_COUNTRY`).
- A module that decides eligibility on its own (`isValidDelivery()`, the postage event)
  is not affected by the rules of the zones it is not linked to.
- The carriers follow the cart's copy of its delivery address. An address edited outside
  the checkout (the customer account, the API, the back office) updates the copy of every
  cart not yet ordered that ships to it when the edit keeps its country, state, postal
  code and city, and the carrier stays. An edit that moves it leaves the copy as the
  carrier and its postage were quoted for, and `CartGuard::checkValidDelivery()` refuses
  the order with `delivery-address-moved` until the address is chosen again (a carrier may
  price by city, as GeolocatedDelivery does). Flexy's payment step then sends the buyer
  back to the delivery step, which chooses the address again and asks the carriers and
  their postage; an API client posts the delivery address again. A cart with nothing to
  ship is not held back.
- A radius around a shop is the job of the GeolocatedDelivery module (2.0.0 for Thelia 3),
  a carrier of its own that zones and their postal code rules filter like any other.

## Back office

The page of a shipping zone (Configuration, Shipping zones) lists its rules and adds or
deletes one, under the `admin.configuration.area` resource. A deletion is confirmed first,
each addition and deletion is written to the administration log, and the card warns when
a rule applies to a country without a postal code format (see Limits).
`Thelia\Domain\Shipping\ZipCode\AreaZipCodeRuleWriter` refuses a pattern that matches
nothing and a country the zone does not hold.

## Tests

- `tests/Unit/Domain/Shipping/ZipCode/`: patterns and rule sets.
- `tests/Integration/Domain/Shipping/ZipCodeDeliveryZoneTest.php`: eligibility, the
  checkout list, the API list, the guard, the estimate, the cart payload, the `delivery`
  loop, the reason, and the rules read once per request.
- `tests/Integration/Model/AreaZipCodeRuleQueryTest.php`: a row that matches nothing, the
  reset of the rules read.
- `tests/Integration/Action/AreaActionTest.php`: the rules of a country removed from its
  zone.
- `tests/Integration/Domain/Shipping/AreaZipCodeRuleWriterTest.php`: writing a rule.
- `tests/Http/BackOffice/ShippingZoneZipCodeRulesTest.php` and
  `tests/Http/Flexy/DeliveryPostalCodeMessageTest.php`, skipped when the installed theme
  does not ship the screen.
