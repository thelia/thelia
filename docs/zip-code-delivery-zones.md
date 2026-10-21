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

Codes and patterns are compared in their normal form (`ZipCodePattern::normalize()`):
compatibility characters folded (Unicode NFKC, through `ext-intl`, which the core
requires: `２０000` is `20000`), without spaces, dashes (every `\p{Pd}`, and the minus
sign) or invisible format characters (`\p{Cf}`: a soft hyphen, a zero-width space), with
the digits of every script written in ASCII (Arabic-Indic or Devanagari digits included)
and the ASCII letters in upper case. What is left must be `A` to `Z` and `0` to `9` only;
a code that keeps anything else (a letter outside `A` to `Z` such as `é`, a variation
selector, a control character, a combining mark) has no normal form. `SW1A 1AA` matches
`sw1a1aa`, `1000-001` matches `1000 001`. A pattern holds the same characters, with `*`
and `...`, and is 50 characters long at most. A pattern that can match nothing (empty,
only `*`, a range with letters or upside down) or that holds another character is refused
when it is written. One that reaches the table by another way matches no postal code and is
logged with its reason, once per zone and country a request reads: as an exclusion it excludes
nothing, as an inclusion it takes nothing and the zone keeps its other inclusions (an
unreadable inclusion never opens the zone to every code). An unreadable inclusion with no
other inclusion therefore closes the zone to every typed postal code of the country, a
placeholder included; no postal code at all is still accepted.

`ZipCodeRuleSet` applies the rules of a zone for one country:

- a zone without a rule takes every postal code, as zones always did;
- a code matching an exclusion is refused, whatever the inclusions say;
- when the zone has inclusions, a code must match one of them;
- no postal code at all (`null`: no address yet, a cart estimated without one) is unknown:
  every zone takes it, and `AreaDeliveryModuleQuery::findByCountryAndModule()` reads no
  rule for it;
- a typed code made only of spaces and dashes (a buyer typing `-` for no code, a code of
  spaces through the API) or empty (which only an address written outside the forms and
  the API can hold: both refuse a blank code) is a placeholder
  (`ZipCodePattern::isPlaceholder()`): a zone with an inclusion does not take it, a zone
  with exclusions only or without a rule does;
- a code without a normal form (a zero-width space alone, `é1`, a variation selector) is
  uncertain, as below.

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
model hooks, is seen from the next request on, and so are the rows deleted in cascade with
their zone or their country, SQL written straight to the table, a rule read then rolled back
with its transaction, and what another process writes during a long console command.

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
address, whose country and state are read too, as `CartGuard` reads them; the customer
address for an `addressId`, also when a country is given next to it as the API does,
provided the address is in that country, belongs to the customer of the cart and is not
the one the copy was taken from (the copy judges its own source, which may have been
edited since). A guest names no address this way: a guest row is reused by whoever types
the same e-mail address. A country given alone, or any other
address, takes the postal code of the cart's delivery address when that address is in the
country, as the postage estimate does. The front API (`GET /api/front/delivery_modules`)
judges the cart's copy unless the request names another address of the cart's customer
(never for a guest) than the one the copy was taken from.

A postal code without a normal form (`ZipCodePattern::normalize()` returns null) is
uncertain, as a code PCRE gives up on: an exclusion refuses it and an inclusion
does not take it; a zone without a rule still takes it. The database refuses a code that
is not valid UTF-8 in an address, so only a caller passing one itself meets that case.
`ShippingFacade::postalCodeWithoutCarrier()` names neither an unknown code, a placeholder
nor an uncertain code (`ZipCodePattern::nameable()`), and names a code without what would
break or turn its message around: invisible format characters (direction marks and
overrides, zero-width spaces), controls (line feeds, tabs, NEL) and line and paragraph
separators; its visible spaces and dashes are kept.

A rule with `*` runs as a regular expression. Should PCRE give up on it (its backtrack
limit), the failure is logged and an exclusion refuses the code, an inclusion does not
take it.

`ShippingFacade::postalCodeWithoutCarrier()` names the postal code of the cart's
address when it is the reason no carrier is offered: no carrier is offered, and one the
code is excluded from would quote the cart. To know it, it asks the price
(`TheliaEvents::MODULE_DELIVERY_GET_POSTAGE`) of the carriers the postal code turned away
as well as of those it lets through. Flexy shows it on the delivery step instead of the
general message.

A pattern written into a log line has its control characters, its backslashes, its line
and paragraph separators and its direction controls escaped (`ZipCodePattern::forLog()`),
so that a rule written by hand cannot forge or turn around a line of the log.

The log lines of the feature are for the shop's developers and go through `Tlog`, whose
default level is ERROR (`tlog_level`, seeded by no install): the unreadable rules and the
patterns PCRE gives up on are WARNING, the rules deleted with a country NOTICE. They are
written only once `tlog_level` is set that low. The merchant's trace of the rules added,
deleted, or deleted with a country is the administration log of the back office.

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
  code, or none. A format that leaves free text is not much better: `Country::getZipCodeRE()`
  reads `C` as `.+`, any characters, and copies every other character unescaped into the
  regular expression, so a `.`, `*`, `?`, `+`, `|`, brackets, braces or a backslash act as
  regular expression characters. The part they cover is free; the rest of the code is
  checked. An empty code is a placeholder, a mistyped one matches no exclusion: both pass
  the exclusions, and only inclusions hold. The back
  office warns on the card of a zone whose rules apply to such a country.
- A rule written for a country goes with the country when it leaves the zone
  (`TheliaEvents::AREA_REMOVE_COUNTRY`).
- A module that decides eligibility on its own (`isValidDelivery()`, the postage event)
  is not affected by the rules of the zones it is not linked to.
- The carriers are judged on the cart's copy of its address (its country, state and postal
  code), and so is the order by `CartGuard`; the modules, however, still receive the
  customer address behind the copy in `DeliveryPostageEvent::getAddress()`, which may have
  been edited since it was chosen (and is null for a guest whose copy points to no customer
  address). A module that prices on the address (postal code, city, coordinates) reads the
  cart's copy, `$cart->getCartAddressRelatedByAddressDeliveryId()`, as GeolocatedDelivery
  does: the event carries the cart.
- The carriers follow the cart's copy of its delivery address, which only choosing the
  address again refreshes: an address edited elsewhere (another checkout step, the
  customer account, the API) keeps its old postal code in the cart until it is chosen
  again. Flexy's delivery step refreshes the copy of an address edited on it, and chooses
  the address again, asking the carriers and their postage again, when its country, state,
  postal code or city changed (a carrier may price by city, as GeolocatedDelivery does);
  another edit keeps the carrier and its postage as they were.
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
