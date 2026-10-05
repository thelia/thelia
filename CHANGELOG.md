# 3.3.0 (unreleased)

## Catalog

- GTIN and manufacturer part number of a combination. The code kept in `ean_code` is a GTIN of the GS1 family (EAN-8, UPC-A, EAN-13 with ISBN-13, or GTIN-14) and is checked wherever a combination is saved, back office, API, stock import and modules alike: spaces and hyphens are dropped, the length and the check digit verified, and a refusal (`Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException`) says which rule the code breaks. An empty code is still accepted, and a code stored before the check is never re-checked until it changes, so editing the stock of a product whose old code would not pass is not blocked. `product_sale_elements` gains `mpn` and `manufacturer_brand_id` (the brand of the product applies when it is null), `order_product` gains `mpn`, frozen when the order is placed like the GTIN. A GTIN shared by two combinations is saved and reported, never refused: `GtinDuplicateFinder` lists the other carriers. `ProductIdentifierReader::forSaleElements()` reads the codes of a batch of combinations in one query for the modules that build merchant feeds. The property keeps its name `eanCode` on the API; `mpn`, `manufacturerBrand` and, for administrators, `gtinSharedWith` are added, and `eanCode` and `mpn` are exact filters on `/admin/product_sale_elements` and, through `productSaleElements.eanCode` and `productSaleElements.mpn`, on the products. The `product_sale_elements` and `order_product` loops output `MPN`. See `docs/product-identifiers.md`.

## Exports and imports

- An order lines export, `thelia.export.order_lines`, writes one row per order line with the references of the product and of the combination, the GTIN and the manufacturer part number frozen on the line, the quantity and the unit price, bounded by the same optional dates as the full order export. The two product price exports gain an `mpn` column, and the stock import an optional `mpn` column; a row of the stock import whose `ean` is not a GTIN is refused with the reason and the import goes on. A GTIN or a part number a spreadsheet would run as a formula is exported behind a leading quote.

## Behaviour changes

- A combination saved with a new `ean_code` that is not a GTIN is refused, wherever it comes from: a 422 on `eanCode` from the admin API, a refused row with its reason from the stock import, an `InvalidGtinException` from a module that saves the model. A code is stored without the spaces and hyphens typed in it. An integration that wrote free text or a mistyped code in that field gets the refusal on its next write of the code; codes already stored stay as they are until they change.

# 3.2.1

Security and maintenance release of the 3.2 line, without any breaking change. It ships `setup/update/sql/3.2.1.sql`, which renames the tax types a shop migrated from Thelia 2 still stores under their Thelia 2 class, declares the back-office hooks the Twig templates call, and records the new version. `thelia/setup` ships as 3.2.1 with this core; `thelia/config` does not change and stays at 3.2.0. The core refuses a back-office theme `thelia/backoffice-default-twig-template` older than 1.2.2, which carries the same CSV export fix: update it to 1.2.2, and the front theme `thelia/flexy` to 1.2.1, at the same time.

## Security

- [GHSA-5524-qfxp-33v9](https://github.com/thelia/thelia/security/advisories/GHSA-5524-qfxp-33v9) — the upload policy checked the file name the client sent, while storage keeps only its letters, digits, dashes, underscores and dots. A document named `report.php ` (trailing space), `report.ph p` or `report.ph#p` passed both the extension blacklist and the server-executable floor, was stored as `report-1.php` and published under `public/cache/documents/`, where a web server that runs PHP in the document root executed it. The policy now checks the name the file is stored under as well as the name it was sent with, and storage itself refuses a server-executable name, so a caller that skips the policy cannot store one either. The SVG sanitizer also recognises an SVG by its stored name, and `MediaFacade::uploadImage()` and `MediaFacade::uploadDocument()` apply the upload policy, as the video upload already did. Files already stored under such a name stay in place after the update: see the upgrade notes.

- [GHSA-7wrm-pcw6-4g9m](https://github.com/thelia/thelia/security/advisories/GHSA-7wrm-pcw6-4g9m) — a document upload accepted HTML and the other types a browser opens as a page or runs as a script, and the shop published them under `public/cache/documents/`, so a script carried by a document ran on the shop origin for anyone opening its link. Documents with an HTML, XHTML, XML, XSL, JavaScript or compressed SVG extension are now refused, whatever `document_upload_forbidden_extensions` says; the list is `FileConfiguration::BROWSER_ACTIVE_DOCUMENT_EXTENSIONS`. The file endpoints of the API serve a document as a download, with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`. On Apache, the document cache gets an `.htaccess`, written by `update.php` for the documents already published and before any document is published, that sends `nosniff` for every document and `attachment` for all but PDF, raster image and plain text files, which still open in the browser. It needs the `FileInfo` override that `public/.htaccess` already needs. nginx does not read it: the upgrade notes give the equivalent block. Documents uploaded before the update stay published until they are removed, as the upgrade notes describe.

- [GHSA-gcgv-f8rf-w2wc](https://github.com/thelia/thelia/security/advisories/GHSA-gcgv-f8rf-w2wc) — the CSV exports wrote customer and newsletter subscriber names, addresses and phone numbers exactly as typed, so a value starting with `=`, `+`, `-` or `@` reached the file as a formula that ran in the spreadsheet of the administrator who opened it. Any text cell of a CSV export, from the core or from a module, that starts with one of these characters, a tab or a carriage return now gets a leading `'` and stays text. A plain number such as `-5.00` or `+33612345678` is written unchanged. A cell holding `,` or `;` is also enclosed in quotes, so a spreadsheet that splits lines on the other separator cannot start a formula halfway through it. JSON, XML and YAML exports are unchanged. The back-office theme 1.2.2 writes the newsletter subscriber export the same way.

## Fixed

- A shop migrated from Thelia 2 with a fixed amount tax or a feature amount tax (eco-tax) prices the products under these taxes again. The migration only moved the percentage tax type to its Thelia 3 class, so the other two kept a class name no tax type answers to, and computing a price failed with "Recorded type ... does not exists". `3.2.1.sql` renames them on a shop already migrated, and `3.0.0-alpha1.sql` on a shop migrated from now on.
- A feature amount tax returns its amount as a number. `FeatureFixAmountTaxType` returned the text of the feature value, which strict types turned into a `TypeError`.
- The 105 back-office hooks the Twig templates call without declaring them, `customer.tab`, `customer.tab-content` and `customer-edit.actions` among them, are seeded on a fresh install and added by `3.2.1.sql` on an installed shop. A module subscribing to one of them was dropped when the container was built ("Hook customer.tab is unknown."), and its section never showed. A hook a module already created keeps its id and its titles.
- `setup/update.php` no longer dies when the cache of the previous release belongs to the web server user. It moves `var/cache/<env>` and `var/propel/<env>` aside before deleting what it can, prints the command that deletes the rest as its owner, and stops with code `8`, before touching the database, when it cannot even move them. See `UPDATE.md`.
- The pickup location provider reads its search parameters (radius, address, country, module ids) from the filters of the operation context before the request, so a caller that is not an HTTP request, such as `resources()` in a front theme, can pass them.
- The state field of the address form is translated.
- A `Thelia\Core\File\Exception\FileException` built from a message alone threw a `TypeError` instead of itself.
- The `/file` endpoints of the front and admin API serve the file to a client whose `Accept` header puts `text/html` first, as a browser opening the URL does. They answered 500.
- The customer export writes the first and last names under their own column labels; they were swapped.

## Upgrade notes

Update the code and the database as `UPDATE.md` describes, from the root of the project, then warm the cache up:

```bash
composer update thelia/thelia-skeleton --with-all-dependencies
php local/setup/update.php
php Thelia cache:warmup --env=prod
```

The update script writes the `.htaccess` of the document cache (`public/cache/documents/` by default), so on Apache the documents already published are served with the download headers from then on. If it cannot write the file, it prints a warning naming `Thelia\Core\File\DocumentCacheProtection`, which writes it. nginx does not read `.htaccess`: add this block to the server block of the shop. The `^~` prefix also keeps the PHP location away from the document cache.

```nginx
location ^~ /cache/documents/ {
    add_header X-Content-Type-Options "nosniff" always;
    add_header Content-Disposition "attachment" always;

    location ~* \.(?:pdf|jpe?g|png|gif|webp|avif|txt)$ {
        add_header X-Content-Type-Options "nosniff" always;
    }
}
```

### Files uploaded before the update

The update refuses such uploads from now on, but it leaves the files already stored where they are. Look for an image or a document stored under a name a web server runs (GHSA-5524-qfxp-33v9), and for a document a browser opens as a page or runs as a script (GHSA-7wrm-pcw6-4g9m). From the root of the project, with GNU find:

```bash
find local/media public/cache -regextype posix-extended \
  ! -type d ! -path public/cache/documents/.htaccess \
  \( -iregex '.*/[^/]*\.(ph(p[3-8st]?|t(ml?)?|ar)|s(html?|tm)|ht(access|passwd))(\.[^/]*)?' \
  -o -iregex '[^/]+/[^/]+/documents/.*\.(html?|x(ht(ml?)?|ml|slt?|spf|ul)|m(ht(ml)?|ml)|r(df|ss)|atom|kml|svgz|[cm]?js)' \) \
  -print
```

The first pattern finds a server-executable extension anywhere in a file name (`report-1.php`, `shell.php.jpg`), among images and documents; the second finds an HTML, XML, XSL, JavaScript or compressed SVG document. The command only lists files. It skips the `.htaccess` the update writes, and it lists the links of the cache whose target is already gone. If the shop keeps its media or its document cache elsewhere (`document_cache_dir_from_web_root`), change the paths.

For each file listed under `local/media/`, delete the image or the document from the back office, on the Images or Documents tab of its product, category, content, folder or brand, so its row goes too. Then run the command again with `-delete` in place of `-print` to remove the files left behind.

Deleting an image or a document over the admin API removes its row and leaves its files, and deleting it from the back office leaves its copy under `public/cache/`. A file deleted that way is still served at its URL: the command lists it as well, and `-delete` removes it.

# 3.2.0

Second minor of the 3.x line. 221 commits since 3.1.0. The version number follows the update script this release ships, `setup/update/sql/3.2.0.sql`, which carries the tables behind the catalog price rules and creates those of the second factor of the administrators, the product videos, the purchase lists, the gift wrappings and the order history, next to the VAT verification columns of the address, cart address and order address tables.

## Promotions and sales

- `TheliaEvents::SALE_PRODUCTS_QUERY` (`Thelia\Core\Event\Sale\SaleProductsQueryEvent`) is raised by `Thelia\Action\Sale::updateProductsSaleStatus()` with the query that selects the products of a sale, before it is read, so a module can exclude products with a SQL condition. The promo status is still reset on every product of the sale, so a product a listener excludes loses the status it had. Nothing changes without a listener.
- Catalog price rules: a named, dated rule with a priority prices a slice of the catalog (categories with or without their descendants, brands, templates, feature values, attribute values, named products, combined with AND) for everyone or for named customers, by a percentage, an amount per currency or a fixed price per currency. The price replaces the promo price in the loops, on the product page, in the cart, on the order and through the front API, without writing anything into `product_price`; it is stored as dated segments in a table of its own, so an opening or a closing takes effect at the second, and follows the catalog when a product, a category or a price changes. `catalog-price-rule:recompute` catches up whatever a change too large for its request left over. A flash sale converts into turned-off rules. See `docs/catalog-price-rules.md`.

## Catalog

- Product videos: a product carries videos next to its images, in one gallery order shared by both. A video plays from a platform the shop enables (`video_providers`: YouTube, Vimeo, Dailymotion), of which only the identifier is stored, or from a file the shop hosts (`video_upload_allowed_mime_types`). It has a visibility, translatable wording, an optional thumbnail taken from the images of its product, and can be bound to combinations. Exposed by the admin and front APIs as `product_videos` and `product_sale_elements_product_video`; the front endpoints serve only what the shop shows, reserved operations included.
- Every image the core manages (product, category, content, folder, brand) takes a translatable `alt` and a `decorative` flag. A decorative image is published with an empty alt; an image without alt text keeps falling back on its title.
- Cloning a product copies its videos, with their thumbnail, their combinations and their place in the gallery, and the alt text and decorative flag of its images. It also copies the wording of each language of the images and documents again: the update listener saved nothing without the model as it was before, so the translations of a clone were left out.
- A brand page offers filters. It takes the columns of the categories where the visible products of the brand are organized, one row per criterion (a feature, an attribute or another one); when two categories govern the same criterion, the visible row in the lowest position is kept. The values shown are those of the brand's products, with their quantities, so no filter leads to an empty list, and the brand facet is never offered on a brand page. `getFilters()` reads a `scope` parameter next to `tfilters` (`scope[brand]=3` on the page of brand 3), because `category=7&brand=3` means opposite things on a category page and on a brand page; a caller that sends no `scope` keeps the previous reading, where a single brand outside a category is a brand page. The `choice_filter` rows of all the categories are read in bulk, with one query per level of the category tree, so the number of queries no longer follows how the brand's products are spread.
- A product listing offers two flag facets, Promotion and Newness. A product matches as soon as one of its visible sale elements is on sale, or new, and the counts follow the other filters applied. Both are listed in the category and template filter screens of the back office, visible by default, where they can be hidden. `3.2.0.sql` inserts their `promo` and `new` rows into `choice_filter_other` when no row of that type exists, taking the next free id since the filters are matched by type, with their titles in cs_CZ, de_DE, en_US, es_ES, fr_FR, it_IT, nl_NL and ru_RU (`INSERT IGNORE`, so a title the merchant typed is kept). A fresh install seeds them as ids 4 and 5.
- A module adds a sort to the product listing by implementing `Thelia\Domain\Catalog\Product\ProductSortProviderInterface` (tag `thelia.catalog.product_sort`, autoconfigured): a value, a title, a position and the API parameters it sends. The theme lists them next to its own sorts, ordered by position.

## Media

- A shop chooses the image formats it serves. The `image_formats` setting lists the formats generated next to the source image, most efficient first (`Thelia\Domain\Media\Enum\ImageFormat`: `avif`, `webp`); the source format is not in the enum, since it is always produced and always the fallback. `image_quality_webp` and `image_quality_avif` set the quality per format, as one number does not give the same picture from one encoder to the next. `Thelia\Domain\Media\Service\ImageFormatCapabilities` answers what the configured driver can write (GD writes AVIF only from PHP 8.1 built with it, and a shared host may have neither Imagick nor AVIF), and `ImageFormatPolicy` is the single place a theme, the rendering module or the back office asks: it drops a format the server cannot write instead of letting an encoder fail in the middle of a catalog page. A fresh install gets `webp`; an updated shop gets an empty list and keeps the images it has, so nothing is regenerated on the first crawl. The core only holds the setting and the checks: the files are produced and served by TheliaLibrary, and nothing renders differently until it is updated.
- Each language of an image can show its own file. This covers the images of products, categories, contents, folders, brands and modules. `getFile()` returns the file of the current locale; a language without one gets the file of the default language when `default_lang_without_translation` replaces missing translations, and nothing otherwise, as translated texts do; the update script gives every active language the file the image had, so nothing changes on screen after the update. Replacing a file replaces it for the language being edited only, and the previous file leaves the disk when no other language still uses it; deleting an image removes the file of every language, and two languages uploading the same file name no longer overwrite each other. Cloning a product copies the file of each language that has its own. The `image` loop reads the file in its own locale. The six image models share this through `Thelia\Model\Tools\LocalizedFileTrait` and `Thelia\Core\File\LocalizedFileModelInterface` (`getOwnFile()`, `getStoredFiles()`, `isFileUsedByAnotherLocale()`), and reading the fallback never adds an empty translation to the model.
- The admin API follows the language. The `*ImageI18n` resources expose `file`, read only. `file` and `fileUrl` of an image resource follow `?locale=` (the default language otherwise), and `GET /api/admin/<type>_images/{id}/file` serves the file of `?locale=`. `POST /api/admin/<type>_images` takes a multipart `locale` field, the language the uploaded file is stored for (the default language when left out, 422 for a locale the shop does not have), and `POST /api/admin/<type>_images/{id}/file` (multipart `fileToUpload` and `locale`) sets or replaces the file of one language, with the UPDATE permission on the resource of the image. This breaks code that reads the `file` column: see Breaking changes.

## Checkout and payment

- The whole tunnel is reachable from the front API, for an authenticated account and without a session. Six operations sit under `/api/front/account/checkout/{cartId}`: the four choices a buyer makes (`delivery_address`, `invoice_address`, `delivery_module`, `payment_module`, each answering with the cart as the shop now prices it), then `validation`, which reports every remaining refusal at once with a stable code next to the sentence, and `place`, which writes the order. Nothing about money is taken from a body: the postage, the discount and the totals are computed by the shop, and the selection operations carry an identifier and nothing else. The placement goes down the very path a theme goes down, `ORDER_PAY`, so the stock, the consents, the emails and the payment module call are the same ones. A cart that already carries an order gets that order back with `alreadyPlaced: true` and is never charged twice. The payment is not raised again, so such an answer carries no payment action and the state of the order is read from `paid`, `orderStatusCode` and `GET /front/account/orders/{id}`. A cart that another request is placing is answered 409, as is a cart the shop can no longer serve, whose message names the product. A cart of another account is answered exactly as a cart that does not exist. The consents are answered in the body of `place`, as `{"consents": [{"code": "terms_and_conditions", "accepted": true}]}`: there is nowhere to leave an answer between two requests, since the shop keeps no record of what an abandoned cart agreed to, so the request that writes the order is the one that carries it. A mandatory consent left out or answered `false` refuses the placement with `consent-missing` and the code of the consent in `details`; a code the shop is not asking for is refused with `consent-unknown`; what is accepted is frozen onto the order exactly as a theme freezes it. `validation` takes no body and therefore keeps reporting `consent-missing` until the placement answers it: that report is the list of boxes to show the buyer. Guest checkout is not part of these operations.
- The invoice address frozen on an order carries its own state. It took the state of the delivery address, so any order billed and shipped to two different states was written with a wrong billing state, on the single order write the theme tunnel and the front API share.
- Each line of a cart answered by the API is taxed in the delivery country of that cart, as its totals already were. The lines were taxed in the shop default country whenever the request had no session, so a cart delivered to a country taxed differently answered lines that did not add up to its own total.
- An order carrying a currency or a language when `ORDER_PAY` is raised is frozen with them, and the session is only read for what the order leaves unsaid. Nothing changes for a theme, which states neither and still gets the session; a module that set a currency on the order and relied on the session overriding it now gets the one it set. The same listener also reads the cart the order names before the cart of the session, and works on a request that has no session at all.
- A cart is turned into an order once, and the database is what says so: the order transaction locks the cart and refuses a second order for it, whatever the number of application servers. The guard covers every path that writes an order, orders created manually through `ORDER_CREATE_MANUAL` included. A cancelled order does not count, so a buyer whose payment failed can order the same cart again. A shop running on more than one node should also point `LOCK_DSN` at a store its nodes share: the shipped default, `flock`, is a file on the local disk.
- A stock shortage while an order is being written is raised as `Thelia\Domain\Order\Exception\StockShortageException`, a class of its own rather than a plain `TheliaProcessException`, and it names the product. Code matching the wording of the message to tell a shortage apart from any other failure can match the class instead.
- Gift wrapping and a note for the recipient. The merchant defines wrappings in Configuration > Gift wrappings (a translated wording, a price, a tax rule, an activation switch and a position), and the buyer picks at most one of them at the payment step, with a note for whoever receives the parcel. A price of zero is a wrapping offered, which is why it is a column and not a configuration entry. The wrapping chosen is invoiced as a line of the order of type `service`, priced and taxed like a product, with its wording and its tax rule frozen: renaming, repricing or deleting the service afterwards leaves every order already placed saying what it said. The note is frozen on the order, bounded at `GiftWrapping::MAX_GIFT_MESSAGE_LENGTH` characters and refused whole rather than cut; it is printed on the delivery note and repeated in the confirmation e-mail, and never on the invoice. A wrapping turned off disappears from the checkout and is dropped from the carts still pointing at it. With no wrapping active the checkout is the one it was before the feature existed. The service is not goods: it takes no stock, it is not returnable, and it does not count towards free shipping.
- `3.2.0.sql` creates `gift_wrapping` and `gift_wrapping_i18n` and adds `cart.gift_wrapping_id` (set to null when the wrapping is deleted), `cart.gift_message`, `order.gift_message`, `order_version.gift_message` and `order_product.line_type`, `product` by default, so nothing is backfilled. The Gift wrappings page answers to the new `admin.configuration.gift-wrapping` resource, to grant to the back-office profiles that manage them. A module follows the wrappings through `TheliaEvents::GIFT_WRAPPING_CREATE`, `GIFT_WRAPPING_UPDATE`, `GIFT_WRAPPING_DELETE`, `GIFT_WRAPPING_UPDATE_POSITION` and `GIFT_WRAPPING_TOGGLE_ACTIVE`. The payment step, the configuration page, the order view, the confirmation e-mail and the delivery note need the matching releases of Flexy, default-twig and the e-mail and PDF templates.
- The cart stays with the shopper until the payment is confirmed. Placing an order no longer empties it: `Thelia\Action\Order::create()` stops raising `ORDER_CART_CLEAR`, and the cart is consumed when it is read again while the order that names it is paid, `equivalent_code` included. A declined card, a cancelled payment or a closed tab leaves the shopper with the cart they had, ready for another attempt, whether they come back themselves or only the provider notified the shop. `ORDER_CART_CLEAR` stays declared and listened to, so a module raising it itself still empties the cart and still retires the guest.
- A new payment attempt reuses the unpaid order of the cart instead of placing another one. `Thelia\Domain\Checkout\Service\CheckoutPaymentService` compares a fingerprint of the cart lines, both addresses, the delivery module and its postage, the payment module, the currency and the discounts: unchanged, and with a payment module that declares it supports retries, the same order is presented to the module again, with the same reference and no second confirmation e-mail. Changed, or with a module that does not declare the capability, the previous order is cancelled through the status flow and one new order is placed. A cart never carries more than one live unpaid order.
- `Thelia\Model\Order::setCancelled()` goes through `ORDER_UPDATE_STATUS` instead of writing the status on the row. Cancelling an order therefore gives its stock back and runs the status listeners, which it did not before, whatever raised it.
- `BaseModule::getCurrentOrderTotalAmount()` prices the cart the payment is about: the one `MODULE_PAYMENT_IS_VALID` carries while a module is asked whether it accepts it, and the one the order is built from while it is asked to pay, held by `Thelia\Domain\Module\Payment\PaymentCartContext`. That cart is taxed in the country of its delivery address, or of its customer's default address when it has none yet. The session cart is read only when no payment is under way, and outside any request (a console command, a worker) the method answers 0 instead of throwing. A payment module built on it therefore judges an order placed through the front API on the cart being ordered, without a line of its code changing.

## Taxation

- A shop liable for VAT invoices without VAT an order billed to a verified VAT number of another member state, once `vat_exemption_mode` is set to `verified_vat_number`. The setting arrives disabled, so an upgraded shop changes no price. Thelia does not call VIES itself: a module implements `Thelia\Domain\Legal\Service\VatNumberVerifierInterface` and reports its answer through `VAT_NUMBER_VERIFIED`. The shipped `NullVatNumberVerifier` answers "undetermined", so a shop without such a module exempts nobody. A verification stays valid for `vat_verification_lifetime_days`, 90 by default.
- The verification is recorded on the address (`vat_verified_at`, `vat_verified_name`), copied to the cart address and frozen on the order address, next to `vat_exempted` and `vat_exempted_amount`. Changing the number or the country of an address drops it, whatever writes the address: the forms, the admin and front API, a profile update. An answer is recorded only while the address still carries the number and the country that were checked, and it reaches the cart copies of that address, so a refusal that arrives after the invoice address was chosen taxes the cart again. An unanswered check changes nothing: a verification still valid keeps exempting until it expires, and none is granted while the service is down. An answer about an address that carries no number is not recorded. Editing an address refreshes the copies that open carts hold of it, so moving it to the shop country or dropping its company stops the exemption of a cart already billed to it. On an order address, only a new number drops it, and the company, registration number and VAT number of the invoice address of an exempt order can no longer be edited from the back office.
- An exempt cart pays no VAT on its lines or on its postage, and a percentage discount is taken on the untaxed amount. The cart keeps the postage the delivery module quoted, tax included, and the exemption is applied each time it is read, then frozen on the order together with `vat_exempted`: a verification recorded, revoked or expired after the quote, or the setting turned off, moves the postage with the lines on screen and on the order. The discount is priced again when the invoice address changes, and when the cart summary finds the exemption changed since the discount was priced (`Thelia\Action\Coupon::reconcileWithVatExemption()`, called by the Flexy summary). The VAT frozen in `vat_exempted_amount` is the VAT of the lines on the discounted base, the discount being spread in proportion to the line amounts, plus the postage VAT the delivery module quoted. The checkout summary of Flexy says why a VAT number typed on the invoice address does not exempt the order (not verified, verification expired), from `VatExemptionResolver::stateForCart()`. An exempt order keeps its amounts after the number is revoked or the setting turned off. The front API exposes the state read only, as `Address.vatVerifiedAt`, `Address.vatVerificationValid`, `Cart.isVatExempted` and `Order.vatExempted`. #3988

## Orders

- Every order keeps a dated history: its creation, each status change, the edits of its addresses, its delivery and its transaction reference, the numbering of its invoice, every e-mail sent to the customer, the notes of the merchant and the events of its returns, each with its author (an administrator, the customer, a module or the system). The back office shows it as a History card on the order sheet, a paginated timeline where an administrator writes notes, edits their own and can make one visible to the customer; Flexy lists the visible ones under "Messages from the shop" on the customer's order page. The history lives in `order_history`, which `3.2.0.sql` creates and fills with the past status changes read from `order_version`.
- `GET /api/admin/orders/{orderId}/history` serves the history to an administrator who can view `admin.order`, and `GET /api/front/account/orders/{id}` carries the notes the customer may read in `customerNotes`. A module writes an event type of its own with `Thelia\Domain\Order\Service\OrderHistoryRecorder::record()`, and `OrderEvent::setSourceModuleCode()` names the module that raised an order event, as `BasePaymentModuleController` does, so the history credits the payment module with the status change.
- `maintenance:purge` removes the history entries older than `purification_order_history_days` days; 0, the default, keeps everything. The personal data export carries the history of each order.

## Customers

- A B2B customer can order by reference. `POST /api/front/account/quick-order/resolve` takes lines of a reference and a quantity (the reference of a sale element, the reference of a product or an EAN, case ignored) and answers a control table that says which lines are resolved, ambiguous, unknown, unavailable or refused for their quantity; `POST /api/front/account/quick-order/{cartId}/add` puts the resolved ones in the cart. A request takes 500 lines at most, and a customer 30 calls a minute.
- Purchase lists: a customer creates, renames, duplicates and deletes lists, fills them from the cart, from an order or from the quick order table, and loads them back into that table. A customer holds 100 lists at most, a list 500 lines and a line 999 999 units. The lists are served under `/api/front/account/purchase-lists`, and a list of another customer answers 404. `3.2.0.sql` creates `customer_list` and `customer_list_item` and adds an index on `product_sale_elements.ean_code`.
- The personal data export carries a `customer_lists` section, and anonymizing a customer deletes their lists.

## Content

- Content slots. A theme asks for the links of a slot by its code instead of naming contents by their id. `Thelia\Core\Content\Slot\ContentSlotService::links($slot, $locale)` returns a list of `ContentSlotLink` (label, URL or none for a heading, source, source id, children, new window), and `first()` the first of them. The caller always passes the locale, so a slot reads the same from a console command. A module fills a slot, or takes one over, by implementing `ContentSlotResolverInterface` (tag `thelia.content_slot_resolver`, autoconfigured, priority set with `#[AsTaggedItem]`). Resolvers are asked from the highest priority down and the first one that does not answer null owns the slot, an empty list included; a slot nobody owns is empty, never an error. The core resolver sits at priority -100. It answers `header_links` from the new `header_menu_items` setting, an ordered list written `folder:<id>,content:<id>`, `footer_links` from the visible contents of the `information_folder_id` folder in their position in it, and `consent.<code>` from the content the consent links to. A reference to a missing or hidden folder or content is left out. A fresh install starts with an empty header and the demo names its Blog folder and its About us page. The update script gives an updated shop `folder:2,content:1`, keeping those of the two that exist, which is the header the theme showed until now.
- The terms and conditions have a single source, the content of the `terms_and_conditions` consent. The update script copies `terms_conditions_content_id` onto that consent when it names no content and the setting names one that exists. The setting is deprecated: the demo import still writes it for the 1.1 themes that read it, and it will be removed in the next major version.
- `Thelia\Core\Template\BackOffice\BackOfficeNavigation::isSectionVisible($section)` tells a back-office theme whether to show a navigation section. A module hides one, `folder` for instance, by implementing `NavigationSectionVoterInterface` (tag `thelia.backoffice_navigation_voter`, autoconfigured). The routes of the section and their permissions do not change.

## Themes

- A front template that declares a `<parent>` in its `template.xml` now inherits from it everywhere. Only the Twig loader and the translator followed that chain until now, so a child template lost the routes, assets, icons and components of its parent and a theme derived from Flexy had to copy the whole theme. `TemplateService::getTemplatesAbsolutePathWithParents()` returns the directories of a template followed by those it inherits from, nearest parent first; the existing methods keep answering one directory per template type. The chain is read from `template.xml` on disk, since it is needed while the container is compiled, before Propel is available.
- A route declared by the active template overrides the route of the same name that it inherits, as `TemplateAttributeLoader` loads the chain from the farthest parent to the active template. A `template.xml` that names no author is accepted, as its XSD allows; it answered 500 on every page. The new `thelia_front_template_components_dir` parameter points at the first components directory of the chain, so the anonymous Twig components of a parent resolve for a child template, and `config/packages/twig_component.yaml` of the recipe reads it. Its value is the same on a standard install, so there is nothing to migrate. A child template reduced to its descriptor, a stylesheet and a logo renders the home page, the catalog, the product, the cart, the login, the contact, the search and the sitemap, with its own stylesheet winning over the parent's.
- The writing direction follows the language. A page in Arabic, Hebrew, Persian, Urdu or another language written right to left is served with `<html dir="rtl">`, on the front and in the back office, with nothing to set: `Thelia\Domain\Localization\Service\LocaleDirection` reads the direction from the language code, and the `lang` table does not change. A template calls `lang_direction()`, a Twig function of TwigEngine 1.1, or reads the `lang_direction` variable the template parser assigns; both answer `ltr` or `rtl`. In PHP, `LocalizationFacade::getCurrentLangDirection()` answers for the current language, and for the default language of the shop outside a session.
- Flexy and default-twig lay out with logical CSS properties and require `thelia/twig-engine-module` ^1.1. In a back-office table, `column_text(..., leftToRight: true)` keeps a figure such as an amount left to right in a right-to-left page. A custom theme that writes `dir` itself or spaces its blocks with physical `left` and `right` properties has to move to logical properties to render a right-to-left language correctly; `docs/rtl-support.md` describes what to change.

## Search

- `Thelia\Core\Event\Product\ProductSearchedEvent` carries a product search a shopper submitted on the front, with its locale and the number of products found, so a module can keep a search log whatever runs the search. It is dispatched under its class name, so a module subscribes to `ProductSearchedEvent::class` and keeps working on an older core. Flexy raises it once per submitted search, on the first page of results; TntSearch 4.1 logs it, which brings the searches of the shop to the "Searches without result" report.

## Exports and imports

- A conversion funnel export, `thelia.export.conversion_funnel` in a new Reports category, writes one row per day with the carts created, the carts holding a line, those with a delivery module, those with a payment module, the orders placed and the orders paid. The period rate of paid orders to carts holding a line is read on the back-office report, not per day. Days without activity are kept with zero counts. The period starts on the cart purge horizon at the earliest, as on the back-office report, so orders are never set against carts the purge already deleted; without a period it runs from that horizon up to today.
- `maintenance:purge` reads the cart retention settings, `purification_cart_no_order_days` and `purification_cart_anonymous_days`, through `Thelia\Domain\Cart\Service\CartPurgeHorizon`, which the conversion report and its export read too. A negative value is read as 0: the purge deletes every cart without order, as it did with the negative value, and the reports start at the current time instead of failing.

## Background jobs

- The core ships Symfony Messenger, with one bus and two transports. `async` carries the jobs of the shop and `failed` keeps those that failed for good. Nothing changes for a shop that sets nothing: `MESSENGER_TRANSPORT_DSN`, empty in `.env`, leaves `async` synchronous, so every job runs in the request that dispatched it and nothing waits for a worker. A shop that runs `bin/console messenger:consume async` names a queue: `doctrine://default` keeps it in the shop database, and a Redis or AMQP DSN works too. A job that fails is tried three more times, 30 seconds, 2 minutes and 8 minutes apart, then set aside in `failed`. The `failed` transport lives in the shop database whatever the queue (`MESSENGER_FAILURE_TRANSPORT_DSN` overrides it), and `messenger:failed:show`, `:retry` and `:remove` work on it.
- `doctrine://default` is served by `Thelia\Messenger\Transport\ShopDatabaseTransportFactory`. It hands the official Doctrine transport a DBAL connection opened with the settings of the Propel connection, so the core needs `doctrine/dbal` and `symfony/doctrine-messenger` but neither DoctrineBundle nor the ORM. The connection is a second one: a job dispatched inside a Propel transaction is queued even if that transaction is rolled back. `setup/thelia.sql` and `3.3.0.sql` create the `messenger_messages` table, which is not in `schema.xml` and has no model, and the transport never creates it on the fly.
- Queued jobs are JSON read through `Thelia\Messenger\Serializer\AllowedClassesSerializer`, never PHP serialization. The message class must be one of the core (`Thelia\`), of an active module (the namespace named after its code), `SendEmailMessage`, or listed in the `thelia.messenger.allowed_message_classes` parameter. A stamp must be a Messenger stamp or come from those same namespaces, and a stamp carrying serializer context is refused. The check runs on dispatch too, so a module queuing a class the workers would refuse finds out at once. It also keeps the console and process messages of Symfony, which run a command, out of any queue.
- The mails are rendered in the request, where the language and the address of the shop are known, and their delivery to the mail server goes through `async`. With a queue, a slow or unreachable mail server no longer holds up the page that sends the mail, and a mail that could not be delivered is set aside, ready to be sent again.
- A worker starts every job from the state a fresh command starts from (`Thelia\Messenger\EventListener\WorkerStateResetListener`): the settings are read again, so a change made in the back office since the previous job is seen. It also forgets the active languages, the default country, the module settings, the rewritten URLs and the currency of the prices, and sets the translator back to the default language.
- `Thelia\Messenger\Monitoring\BackgroundJobsMonitor` answers what the back office shows of the background jobs. It tells whether a queue is configured, how many jobs wait in it when the transport can count them, and lists the failed jobs (`FailedJob`) with what they were, why they failed and how many attempts they took. A failed job can be replayed, which puts it back on the transport it failed on, or runs it at once without a queue (a job that fails again stays aside), or it can be deleted. The screen answers to the new `admin.configuration.background-jobs` resource (`AdminResources::BACKGROUND_JOBS`, id 59 on a fresh install, added by `3.3.0.sql` with its title in the 8 install languages), since the description of a mail names its recipients and a reason may quote personal data.
- `thelia:messenger:purge-failed --older-than=30` deletes the jobs set aside for longer than that many days (`--dry-run` counts them). A failed job keeps everything it was dispatched with, an order confirmation the address and the content of the order, and nothing else removes it.
- An export asked for in the back office is a job (`Thelia\Domain\DataTransfer\Job\ExportJobLauncher`, table `export_job`), routed to `async`. Without a queue it runs in the request and comes back finished, as the export did before. With a queue the page is free at once, and the row tells how many rows are written so far, then where the file is, or why it failed. `ExportHandler::export()` takes an optional `$onProgress` closure, told the rows written every `ExportHandler::PROGRESS_STEP` rows, and `ExportHandler::resolveRangeDate()` turns the year and month of the back-office form into dates. A finished export is never run twice. A failed one is recorded on its row and goes straight to `failed`, where replaying it starts it over. `maintenance:purge` deletes the jobs older than 7 days; their files are already gone after a day.
- The recurring tasks of the shop are declared with it (`Thelia\Scheduler\TheliaSchedule`, `symfony/scheduler`) and run by a worker consuming `scheduler_thelia`, next to `async`. They are: `sale:check-activation` every minute, `maintenance:purge` at 3:30, `thelia:messenger:purge-failed` at 4:00, and `currency:update-rates` only when `THELIA_SCHEDULE_CURRENCY_RATES` is given an expression, since it overwrites every rate, the ones set by hand included. Each expression comes from `THELIA_SCHEDULE_SALE_CHECK`, `THELIA_SCHEDULE_MAINTENANCE_PURGE`, `THELIA_SCHEDULE_FAILED_JOBS_PURGE` and `THELIA_SCHEDULE_CURRENCY_RATES`, and an empty one leaves the task out. Nothing runs while no worker consumes the schedule, so a shop keeping these commands in its crontab keeps them there and must not do both. The sale check switches back on a sale turned off by hand inside its dates, as the command always did. The schedule is locked (`LOCK_DSN`) and remembers its last run, so two workers never run a task twice and a worker coming back catches up the last missed run only. A module adds a task with `#[AsCronTask(..., schedule: 'thelia')]` or `#[AsPeriodicTask(..., schedule: 'thelia')]` on a command or a service.

## Installation and updates

- `php bin/console thelia:database:convert-utf8mb4` converts a database upgraded from Thelia 2 to `utf8mb4`. Such a shop keeps its tables in `utf8` (`utf8mb3`) while Thelia 3 connects in `utf8mb4`, so it refuses an emoji with error 1366 where a fresh install stores it. The rebuild locks each table for writes for a time that grows with its size, so it is a command to run once, in a maintenance window and after a backup, not an update script; `UPDATE.md` says when. It is a dry run by default and lists the tables and columns that are not in `utf8mb4` with their approximate size; `--force` converts table by table with `CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci`, the collation of `setup/thelia.sql`, then switches the default of the database, and `--table`, repeatable, limits the run to the named tables.
- During the conversion, a table still in `COMPACT` or `REDUNDANT` moves to `DYNAMIC` in the same statement, as a fresh install creates it, so a unique key on a `VARCHAR(255)` does not fail on the 767-byte limit; an index that would still be too long is reported before anything runs. `TEXT` columns keep their type instead of widening to `MEDIUMTEXT`. A text column used by a foreign key stops the run before any change, as MariaDB and MySQL refuse to change its character set. A table in another character set (`latin1`) is reported and left alone, because its bytes may be UTF-8 written through a `latin1` connection, and a column whose collation is not `general_ci` is flagged, as it ends up in `utf8mb4_general_ci`. The run stops at the first table that fails and prints the database error; running it again picks up the tables left, and a run with nothing left changes nothing.
- `setup/update.php` runs unattended with `-n` (`--no-interaction`, `--yes`): every question is answered yes, the backup and the restore included. It exits 0 when the database is already on the latest version, so a deployment can call it on every release. When the database is on another version than the code, it removes `var/cache/<env>` and `var/propel/<env>` before booting, so the update no longer starts on the caches of the previous release.

## Fixes

- `maintenance:purge --dry-run` no longer deletes the export files older than a day: `PurgeExportCacheListener` ignored the dry run, and `ExportCachePurger::purgeOldExportFiles()` takes a `$dryRun` flag to count them instead.
- A class with a property typed with a union or an intersection can be read by the application serializer. `Thelia\Api\Bridge\Propel\Serializer\PlainIdentifierDenormalizer`, which the serializer asks about every class it reads, called `getName()` on any property type and failed on those, `int|string` included.
- An image rendition requested without a background colour gets a cache file of its own. `ImageEvent::getOptionsHash()` answered an empty hash as soon as the width, the height, the resize mode or the background colour was missing, so all such renditions of a source image shared the file generated first.
- An export that declares no column alias keeps the columns of its data. `AbstractExport::applyOrderAndAliases()` and `JsonFileAbstractExport::applyOrderAndAliases()` only handed the data back untouched for a `null` `$orderAndAliases`, while the property is `[]` by default, so such an export wrote one empty line per record. An empty list is now read as no alias at all; an export that declares aliases is unchanged.
- The `tfilters` selection of a collection reaches its query once. `TheliaFilter::filterProperty()` ignores the property it is called for and `AbstractFilter::apply()` calls it for every entry of the request, so each parameter (`visible`, `itemsPerPage`, `page`, `category_depth`, ...) applied every condition of the selection once more to the same query: seven to eight copies of the category and attribute conditions on a product listing, and a query that slowed down with every parameter added. The selection is now applied the first time `TheliaFilter` sees a query and not again to that query, whether it is read from the context or from the request.
- `module:schema:apply` no longer empties the tables of an installed module. The `TheliaMain.sql` of a module drops each table before creating it again, so replaying it on a shop in service lost the data of the module. The command now refuses a module when a table its `TheliaMain.sql` drops holds rows, names those tables with their row count and exits with a failure; with `--all`, the other modules are still applied. `--force` replays the script anyway. Tables that are empty or absent, and the `update/*.sql` scripts, are applied as before; the install and `bin/test-prepare` do not go through the command.
- A PUT on `product_sale_elements` that leaves out writable properties is refused with a 422 naming the missing fields, instead of a 500 on `Column 'product_id' cannot be null`. The guard that keeps a null away from a required column did not follow a relation setter to its foreign key (`setProduct()` fills `product_id`), and read `product_sale_elements.position` and `visible` as nullable in the schema where the installer creates them `NOT NULL`; the schema now agrees with the table, which changes nothing for an installed shop. `product`, `ref` and `quantity` are required for a direct write (a stock of zero is accepted), and without that the request would have answered 200 and wiped `isDefault`, `promo` and `eanCode`. Creating a product with nested sale elements is unchanged.
- A customer written through the admin or front API needs a first name and a last name. A payload without them, an ERP sending `firstName` where the resource reads `firstname` for instance, ended in a 500 carrying the failed INSERT; it now answers 422 naming the field and writes no row.
- A product update whose nested sale elements carry no `id` keeps the row holding the same `ref` instead of replacing it. An ERP that holds its own references and never Thelia's ids deleted the sale element on every synchronisation, with its attribute combination and its customer family price, and still got a 200. The match is made only under a persisted parent, for a non-empty `ref`, on a table with a `Ref` column and a single foreign key to the parent, and only when exactly one row matches (`product_sale_elements.ref` is not unique): otherwise a row is created as before. A nested element still replaces the sub-collections the payload leaves out, such as `productPrices` or `attributeCombinations`, which is the contract of a transmitted array.
- Selecting attribute filters no longer lists a product once per matching variant. A product declined in five checked colours filled five tiles of the listing; `AttributeAvFilter` now asks for a matching sale element in an `EXISTS` and the product comes back once.
- A shop upgraded from Thelia 2.6 no longer answers 500 on `GET /api/front/tfilters/products?tfilters[category]=<id>` for a category that has filters. `choice_filter.type` was added without a default and filled nothing, so every carried-over row kept NULL and reached `Filter::setFieldType(string)`. A NULL or empty type is now shown as `checkbox`, as for a filter with no row, and `3.2.0.sql` sets it in the table.
- A shop upgraded from Thelia 2.6 can create an administrator and change a password again. `admin.password_renew_token` stayed `NOT NULL` without a default, so `admin:create` failed with SQLSTATE 1364, `admin:updatePassword` with 1048, and the back office answered 500 for both; `3.2.0.sql` gives the column its fresh install definition.
- An upgraded shop gets the fresh install definition of other legacy columns, so a write that a fresh install accepts is no longer refused with error 1364: `folder.parent` (`DEFAULT 0`, a folder created at the root), `coupon.expiration_date`, `currency.format`, `state.isocode`, `order_status.color` and `position` (nullable), and the same on `folder_version` and `coupon_version`. `order.invoice_date` and `order_version.invoice_date` become `DATETIME` where they were `DATE`, which cut the invoice time to 00:00:00 on every write. The statements can be replayed.
- The backup `setup/update.php` offers before an update is streamed to the file instead of being built in memory. On a 2.8 GB database the PHP process reached 7.6 GB before writing anything and was killed, and with a finite `memory_limit` the backup was refused as too big for an automatic backup. `Database::backupDb()` now reads the rows unbuffered (the setting is restored afterwards) and writes each table as `INSERT` statements of 1 MiB at most, a larger row getting its own; a backup that fails part way removes its partial file, and a short write (disk full) raises instead of leaving a truncated dump. The file layout is unchanged, so `restoreDb()` reads old and new dumps. `Update::checkBackupIsPossible()` is deprecated and always answers true. Known limit: `FLOAT` columns are dumped rounded to six significant digits, so a `currency.rate` of 17.13039970... comes back as 17.1304, as before.
- A violation in a nested resource is named with its path again. `ApiResourcePropelTransformerService` caught `ApiPlatform\Symfony\Validator\Exception\ValidationException`, a class that does not exist in API Platform 4.3, so an error on the second address of a customer came back as `label` and not as `addresses[1].label`. It now catches `ApiPlatform\Validator\Exception\ValidationException`.
- Activating a module whose `postActivation()` writes to a new i18n table no longer fails with `Cannot fetch TableMap for undefined table` and rolls the activation back. The Propel rebuild a module activation forces regenerates an init file that the kernel had already loaded with `require_once` earlier in the same request, so the second load did nothing and the in-memory `DatabaseMap` never learned the new tables; the generated loader is now run again after a forced rebuild.
- Writing a rewritten URL outside an HTTP request no longer crashes with `URL instance is not initialized.`. A module's `postActivation()` creating pages, an import command or `module:activate TheliaCMS` rolled the write back, because `RewritingUrl::postSave()`, `postDelete()` and `UrlRewritingTrait` cleared the cache of an instance that only `TheliaHttpKernel` creates. They call the new `URL::clearInstanceRewritingUrlCache()`, which does nothing when there is no instance, hence nothing to clear. `UrlRewritingTrait::getUrl()` and `generateRewrittenUrl()` still need an instance to build a URL.
- A rewriting row that is a pure alias, with no view, view id or locale and a `redirected` target (a legacy URL carried over with no page behind it), answers its 301 instead of a 500 on `URL::retrieve(): Argument #1 ($view) must be of type string, null given`. The manual redirect is now checked before the two locale checks of `matchRequest()`, and falls back on the target URL the resolver already joined.
- An anonymous newsletter subscription, with an e-mail and no name, subscribes instead of crashing on `NewsletterEvent::$firstname must not be accessed before initialization`. `firstname` and `lastname` default to an empty string, which is what is stored.
- `MessageQuery::getFromName()` throws its intended exception for an unknown message name. It compared `findOne()` to `false` where the method returns `null`, so the lookup ended in a `TypeError` on the return type.
- A route condition that calls `service()` no longer answers 500. The routing context had no `_functions` parameter; `request.context` now gets the expression language provider, as FrameworkBundle wires `router.request_context`, and the chain router hands that context to every router it holds.
- `module:post-activate-all` reports a failed post-activation. It caught every error, printed it as a comment and exited 0, so an install reported success with a half-installed module. It now goes on with the other modules, prints each failure as an error and exits 1; `bin/install` and `thelia:install` already checked that code, and `bin/test-prepare` now stops on it too.
- A module update that drops a column, an index or a foreign key that is already gone is ignored. A fresh install applies the `TheliaMain.sql` of a module and then replays its `update/*.sql`, so such an update met error 1091: `DatabaseSetup` printed it as a warning (four for the Page module on every install) and `module:schema:apply` stopped on it. 1091 joins the codes both already tolerate (1050, 1060, 1061, 1068, 1826, 1005/121), which all mean "already there" in the same replay; like them, a typo in the name to drop goes through silently.
- `bin/install` and `bin/test-prepare` clear the application cache pools when they recreate the database. `var/pools/<env>`, or the backend that `THELIA_CACHE_DSN` names, stayed in place, so after a reinstall a module could serve what it had cached from the previous database (the footer menu of the CMS kept entries the new database did not hold). Both run `cache:pool:clear --all` first, which reaches a remote cache backend too.
- `Thelia\Domain\Pricing\CatalogPriceResolverInterface` has its alias declared, to `Rule\CatalogPriceRuleResolver`, in `Config/Resources/services/core/pricing.php`. It came from the rule of the service loader for an interface with a single implementation, which does not hold for a decorator: the loader registers those aliases once at the end of the file it loads, where the core scan and the `configureServices()` of every module run, so a module class implementing the interface made it doubly implemented, no alias was registered and the container failed to compile on `EffectivePriceResolver`. `docs/catalog-price-rules.md` says where the alias lives and how a module decorates the contract; a module whose prices depend on the visitor or on the cart also decorates `PricingActivityChecker`, otherwise the core never asks.
- A checkout step declared by a module lands after the steps already at its position and before the payment, which moves down to make room, so no two steps share a position. Steps that still share one, including the rows a 3.1 shop left on the delivery's place, are served cart first, then delivery, then module steps, then payment and confirmation: a module step is never served before the delivery.
- A delivery module that cannot quote the cart (not available for the address, unknown module, virtual cart) is taken off the cart with its postage instead of staying on it silently, so the checkout no longer lets the buyer pay with a carrier that never quoted. A posted module id that is not a carrier serving the address leaves the cart without one.

## Administrators

- An administrator can protect their account with a time-based one-time code (RFC 6238, any authenticator app), with ten single-use backup codes for a lost phone. A protected account is not signed in by its password alone: `admin.checklogin` keeps the account out of the security context, gives the session a new id and sends it to `admin.two-factor.verify`, and only a valid code, or an unused backup code, completes the sign-in. `admin.two-factor.cancel` gives the password form back. A pending sign-in expires after five minutes and ends after five wrong codes; ten wrong codes on an account within ten minutes, with no right code between them, refuse every code for that account; each attempt is counted before its code is checked, so requests sent together cannot all slip under the limit, whatever the door and however many times the password was typed again. This account counter comes on top of the address counter the code form inherits from `BruteforceForm`, like the login form but with its own count, active in production only and while `form_firewall_active` is on: the address counter slows down one machine, the account counter stops a guess spread over many addresses, which no address counter ever sees. A code is accepted once, even by requests arriving together: the step and the backup code are claimed by a conditional update. The secret lives in `admin_two_factor`, the backup codes as password hashes in `admin_two_factor_backup_code`, both deleted with the account; the secret shown while the second factor is being enabled stays in the session that asked for it until a first code proves it. Every activation, deactivation, reset, backup code use and failed code is written to the admin log, never with the code.
- The admin API applies the same rule: `POST /api/admin/login` for a protected account needs a `code` field next to the username and the password, and answers exactly as a wrong password does without it. A refresh token remembers the second factor the account had when it was issued, and is refused once that second factor is enabled, removed or replaced.
- The remember-me cookie of a protected account no longer opens the back office on its own: it skips the password and asks for the code, on back-office pages only, and five wrong codes retire it. Enabling, disabling or resetting the second factor retires the remember-me token of the account, so a cookie issued before the change opens nothing afterwards.
- The `admin_two_factor_required` setting, off on a fresh install and after an update, and hidden from the configuration variables, makes the second factor mandatory: a signed-in administrator without one is sent to `admin.two-factor.setup` and reaches nothing but that page and the logout until it is done, back-office controllers of modules outside `/admin` included. The obligation is checked again on every back-office request, so an administrator who disables their second factor, or whose second factor a peer resets, is sent back to the activation on their next page rather than at their next sign-in. The admin API gives such an account neither a token, nor a refresh, nor an answer with a token issued before.
- `bin/console admin:two-factor:reset <login>` removes the second factor of an administrator who lost both their phone and their backup codes. The back-office template renders the pages `two-factor-verify`, `two-factor-setup` and `two-factor-backup-codes`, and `AdminTwoFactorManager` is what a template or a module calls to disable, reset or regenerate. Only the `default-twig` back-office template ships those pages: a shop still on the Smarty `default` template must not turn the setting on, and an administrator who enabled a second factor cannot sign in there until `admin:two-factor:reset` removes it.
- Known limit: the shared secret is stored in the database unencrypted, so a leaked database gives it away; encrypting it waits for a vault of the shop secrets.
- The admin log no longer keeps the `Cookie` and `Authorization` headers of a request, which carried the session, remember-me and API credentials of whoever made it, and a failed back-office sign-in or password creation no longer keeps the request body, which held the password that was typed.
- A remember-me cookie that does not decode, for an administrator or a customer, is ignored instead of failing every page of the shop for the browser that carries it.
## Modules

- A module declares in its `module.xml` whether it is active right after the shop is installed, through the optional `<enabled-by-default>0|1</enabled-by-default>` element of the 2.2 descriptor format. A module that says nothing, or says 1, is registered active as before; a module that says 0 is registered inactive, stays listed in the back-office and waits for the merchant to activate it. No module of the distribution declares 0 yet, so a fresh install still activates all of them. The install reads the element without the kernel (`bin/install`, `bin/test-prepare`, `thelia:install`) and stops with a readable error on a value that is neither 0 nor 1, or on a descriptor that declares the element and that the module schema refuses (the element out of its last place, or a descriptor still in the 2.1 format, which does not know it). The installers check every descriptor before they create the database, so a refused one stops them before the database, the schema and the environment file exist; `thelia:install` checks them right after the permissions, before it asks anything. A descriptor that does not declare the element is registered as before, without being checked against the schema. The installers recreate the module table before they register, so the declaration decides the state of every module they register; the second copy of a module found in both `vendor/thelia/modules` and `local/modules` only refreshes the namespace and the version of the row the first copy created, and rewrites its title and replays its SQL as before.
- The install entry points (`bin/install`, `bin/test-prepare`, `thelia:install`) refuse a descriptor through `Thelia\Module\Exception\InvalidModuleDescriptorException`, an `\InvalidArgumentException`. `DatabaseSetup::registerAndApplyModules()` takes the module directories as an optional parameter; without it, the directories are the ones it always read. `Thelia\Install\Standalone\ModuleDescriptorReader` reads and checks them without a database, the step every installer runs first. `thelia:install` runs its module and template steps through `Thelia\Install\Standalone\ModuleRegistrationStep` and `Thelia\Install\TemplateApplier`.
- `Thelia\Domain\Module\Composer\ComposerHelper::dumpAutoload()` regenerates the project autoloader, and throws a `\RuntimeException` carrying the Composer output when it fails; `template:set` uses it and still only prints that failure. `Thelia\Module\ModuleDescriptor::enabledByDefault()` reads the element from a descriptor, for the steps that run with and without the kernel. `Thelia\Tools\TerminalText::withoutControlCharacters()` replaces the control, bidirectional and invisible formatting characters of a text a module ships before a console prints it, and each byte of a text that is not UTF-8 outside a well-formed sequence, `TerminalText::singleLine()` does it for an identifier, line feeds and tabs included, and `TerminalText::onOneLine()` folds a message spread over lines onto one, as the install and `template:set` print a refused descriptor. `Thelia\Module\ModuleDescriptor::MANDATORY_INACTIVE_WARNING` holds the text of the warning about a mandatory module left inactive. `Thelia\Command\Install` takes its module and template steps as optional constructor arguments.
- The root `composer.json` requires Cheque, CustomDelivery, FreeOrder, VirtualProductDelivery, HeaderHighlights and RecentlyViewed directly, ahead of the theme releases that stop requiring them: default-twig drops the first four and Flexy the last two. `thelia/thelia-skeleton` 3.2 requires them, and both themes conflict with a skeleton older than 3.2.

## Behaviour changes

- `template:set` (phase 7 of the install) only activates the modules the theme brings to the shop, that is the modules the module table does not know yet. During `bin/install` and `thelia:install`, phase 6 has already registered every module found under `vendor/thelia/modules`, theme modules included: `template:set` then activates nothing and only reports the inactive ones. A module the shop already knows keeps its state, whether its descriptor ships it inactive, the merchant switched it off or `module:refresh` registered it inactive, and the output names the module the theme is missing. One exception: a module listed under `<required>` by a module the theme brings and activates is activated with it, one level deep, whatever its own descriptor declares and even if the merchant had switched it off. A module the theme brings that ships inactive activates none of its required modules. Before, applying a theme switched every inactive module it requires back on. `ModuleManagement::installModule()` follows the same rule: a module declaring `<enabled-by-default>0</enabled-by-default>` is installed and registered, not activated. `ModuleManagement::installModulesFromTemplatePath()` returns every module the theme lists, active or not: read `getActivate()` to tell them apart. After `module:refresh`, which registers a new module inactive, activate the theme's modules from the back-office or with `module:activate` before `template:set`: the theme no longer switches them on.
- A module the shop knows is recognised by its namespace or by its code: a module whose namespace changed between two releases keeps the row, and the state, the merchant gave it.
- A module the theme brings and the shop cannot activate stops `template:set` with `ERROR: <message>`, the trace in verbose mode and in the log, and a non-zero exit code. An `\Error` (a module class that does not load) is reported the same way as an exception. The modules listed after it are not installed and the theme is not enabled; the modules activated before it stay installed and active, without being announced, and so do its own `<required>` modules, which the activation switches on before it checks the module itself. Before, the same failure already stopped the command, but as an uncaught exception trace; the only failure printed in red and carried on was the reactivation of a module the shop already knew, which `template:set` no longer attempts.
- The stop of `template:set` only holds for the run that met it when the activation failed: the installation registered the module inactive, the row stays, and a second `template:set` finds a module the shop knows, leaves it inactive, names it and enables the theme. When the registration failed (a class that does not load, an exception raised while the module is installed), no row is left, unless the module's own `install()` ran statements MySQL commits implicitly before failing, and the next `template:set` fails the same way. The second run's line reads `Module X is required by the theme but is registered inactive: left as it is, and the theme goes on without it; activate it from the back-office if the theme needs it.`
- `thelia:install` propagates the failure of a template: the remaining steps run, then the command prints `Thelia installed with errors: a template could not be applied. Check messages above.` and exits non-zero, as `bin/install` already did. A PHP `\Error` raised by `template:set` outside its module step (the module step reports it and exits non-zero) still stops `thelia:install` where `bin/install` counts it as a failed step and goes on.
- Both installers keep the exit code a post-install command returned when a `console.terminate` listener dies afterwards on the cache the command cleared (`Failed opening required`): before, that error was read as a success whatever the command returned. `bin/install` runs every command of its post-install phase this way, `thelia:install` runs `template:set` this way, in a kernel booted without debug as `bin/install` already did: the dispatcher of a debug kernel loads every listener before calling the first one, and would read a successful `template:set` as failed.
- Once every theme module is handled, and before enabling the theme, `template:set` prints `N theme modules found, M active.` instead of `N modules installed and activated.`. The state of each theme module is read once every module has been handled: a module a later one activated through `<required>` is reported active. `Module X successfully installed and activated.` is only printed for a module the theme brings; a module activated on the way as the `<required>` module of another is not announced. A mandatory module the theme finds inactive is named as such: `Module X is mandatory but is registered inactive: activate it from the back-office.`
- The install warns, once the module table is written, about a mandatory module registered inactive (`X is mandatory but is registered inactive: activate it from the back-office.`) and about an active module whose `<required>` module is registered inactive (`Y is registered active but requires X, which is registered inactive: activate X from the back-office.`). The installers register a module inactive because its descriptor ships it so, or because the first copy of a module found in two directories did. The install registers each module on its own and only warns: it does not check that the active module runs without the inactive one.
- Neither the back-office nor `module:activate` (with or without `--with-dependencies`) activates the required modules of a module. In the back-office, the mandatory flag hides the deactivation switch of an active module and forbids deleting the module.
- A module found in both `vendor/thelia/modules` and `local/modules` is read, and its SQL applied, from each copy as before; the first copy, the `vendor/thelia/modules` one, creates the row, and the warnings describe that row. The SQL of a module shipped inactive is applied at install like any other: its tables exist before it is activated.
- `DatabaseSetup::getWarnings()` describes the last `registerAndApplyModules()` only, where the warnings of successive calls used to add up. The install, and `ModuleDescriptorValidator` for its callers (`module:refresh`, `template:set` and any activation that checks the module; the back-office activation does not), load a descriptor with `LIBXML_NONET`, as a defence in depth: neither loads external entities or DTDs in the first place.
- The withdrawal period of a return runs from the last time the order moved to `sent` in its history. An order placed before the history existed, or never marked as sent, keeps counting from its creation, as before.
- `thelia:install` applies the `default-twig` back-office theme when it is asked none, as `bin/install` already did: the Smarty back-office `default` it used to apply is no longer installed, and a template that cannot be applied now makes the install end on a failure. `Thelia\Command\Install::DEFAULT_THEMES` lists the theme of each type it applies by default. The message saying a template could not be applied is printed even when a later step of the install throws.
- A module a theme brings that ships inactive is registered without the checks its activation would run (the Thelia version it requires, its `<required>` modules active, its Propel schema): the back-office and `module:activate` skip them as well when the merchant activates it later.

## Breaking changes

- The line an order history gets for a mail (`email_sent`) is written once the mail server has taken the mail, by `Thelia\Mailer\EventListener\OrderEmailHistoryListener` on `SentMessageEvent`, and no longer by `MailerFactory` right after handing it over. Without a queue this is the same request; with one, it is the worker, later, and the author of the line is `system`. `MailerFactory` names the order in two headers of the mail, `X-Thelia-Order-Id` and `X-Thelia-Message-Code`. It no longer takes an `OrderHistoryRecorder`: its constructor has three arguments. With a queue, `sendEmailMessageOrFail()` and the methods built on it only throw when the mail could not be built or queued; a delivery failure is set aside in `failed` instead.
- The Smarty back office (`thelia/backoffice-default-template`) is no longer required nor activated: `default-twig` is the only back office the core installs, and the Smarty one is not maintained in 3.x. The update script switches a shop whose `active-admin-template` is still `default` to `default-twig`, and turns off `TheliaSmarty` and `VirtualProductControl`, which only came with it. A shop that still runs the Smarty back office has to require `thelia/backoffice-default-template` itself, register `BackOfficeDefaultBundle\BackOfficeDefaultBundle` in `config/bundles.php`, set `active-admin-template` back to `default` and activate `TheliaSmarty` again. `thelia/hook-admin-home-module`, which fills the dashboard of `default-twig`, is now required by the root package; `thelia/smarty-module` and `thelia/web-profiler-module` are no longer installed, in development either: the Symfony profiler stays, the Thelia panels it added (Smarty among them) go away.
- `Thelia\Form\Lang\LangUrlEvent` ships with the core, under the same name. `CouponAbstract::COUPON_FORM_NAME` names the coupon form the coupon fields are posted under, in place of `Thelia\Form\CouponCreationForm::COUPON_CREATION_FORM_NAME`. Every other class of `Thelia\Controller\Admin` and `Thelia\Form` that came with the Smarty back office goes away with it. Modules that extend them stop loading: the versions of EasyProductManager and EasyCustomerManager written before their Thelia 3 port (they extend `Thelia\Controller\Admin\ProductController`), and Dealer up to 4.0.0 (`Thelia\Controller\Admin\FileController`). EasyProductManager 4.0.0, EasyCustomerManager 3.0.0 and Dealer 4.0.7 onwards no longer use them. CustomerFamily reads the form name `thelia_product_creation` only, which `default-twig` builds too.
- `BaseModule::getCurrentOrderTotalAmount()` counts the postage of the cart when `$with_postage` is true, as its signature always said. It read the postage off the session order, which nothing in 3.x writes to, so the amount a module checked never included the delivery. A module comparing that amount to a minimum or a maximum now compares the amount the order will carry. FreeOrder, which accepted a free cart whose delivery is charged, now refuses it. `Thelia\Action\Order::__construct()` and `Thelia\Action\Payment::__construct()` take `PaymentCartContext`; a module instantiating them itself has to pass it.
- `Thelia\Controller\Admin\SessionController::__construct()` and `Thelia\Core\HttpFoundation\Session\SessionManager::__construct()` take `AdminTwoFactorManager` and `TwoFactorChallenge`; `RefreshTokenController::__construct()` and `AuthenticationSuccessSubscriber::__construct()` take `AdminTwoFactorManager`. A module instantiating them itself has to pass them. `Thelia\Core\HttpFoundation\Request::toString()` leaves the `Cookie` and `Authorization` headers out, and the `PHP_AUTH_USER`, `PHP_AUTH_PW` and `PHP_AUTH_DIGEST` values Symfony copies into the headers, so the admin log no longer keeps the HTTP Basic credentials of a shop behind a password-protected directory.
- `Thelia\Module\PaymentModuleInterface` declares `supportsPaymentRetry(): bool`. `AbstractPaymentModule` answers false, so a module extending it has nothing to do; a module implementing the interface directly has to declare the method. Say true only when the provider reference varies at each attempt and the notification finds the order back by the order's own reference.
- `Thelia\Model\Order::setCancelled()` takes the event dispatcher: `setCancelled(EventDispatcherInterface $dispatcher)`. A caller that cancelled an order by hand has to pass it, and gets the restocking and the status listeners it did not have before.
- `Thelia\Domain\Checkout\Service\CheckoutPaymentService::__construct()` takes `OrderFacade`, `OrderFingerprint` and `PaymentCartContext` on top of what it took. A module instantiating it itself has to follow; a module reading it from the container has nothing to do.
- A payment module reading the session cart while it is asked to pay now reads the cart the shopper filled, where it used to read an empty one: the cart is no longer cleared before `MODULE_PAY`. `AbstractPaymentModule::cartItemCount()` still answers zero when there is no session.
- `Thelia\Core\Template\Loop\Product` takes `EffectivePriceCatalog` and `PricingActivityChecker` in place of `ReservedSalePriceCatalog`; `Loop\ProductSaleElements` and `ProductSaleElementsAccessService` take `EffectivePriceCatalog` in place of `ReservedSalePriceCatalog`; `Thelia\Action\Cart` takes `EffectivePriceResolver` and `PricingActivityChecker` in place of `ReservedSalePriceResolver` and `SaleAudienceChecker`. A module instantiating one of them itself has to follow.
- `Thelia\Api\EventListener\ReservedSalePriceListener` is `EffectivePriceListener`: it now serves the rule price as well as the reserved one.
- `Thelia\Api\Service\API\ResourceCache` reads `thelia.api.data_access.cache.visitor_dependent_price_prefixes` and asks `PricingActivityChecker`; the former `reserved_sale_sensitive_prefixes` parameter is kept as an alias of the new one.
- The API persist and remove processors dispatch `Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent` after a write, and the collection provider dispatches `CollectionModelsLoadedEvent` before transforming a page. Nothing listens to them but the core; a module may.
- The front `ProductSaleElements` resource carries `displayInitialPrice`, null unless a rule or a reserved operation priced the sale element on that read.
- The toggle and position actions of `Thelia\Controller\Admin\AbstractCrudController` (`setToggleVisibilityAction()`, `updatePositionAction()`, `genericUpdatePositionAction()`) answer 403 to a request without the session token. A module whose back-office list toggles or reorders rows through them without sending `_token` has to send it.
- `Thelia\Tools\TokenProvider::checkRequestToken()` reads the token from the `_token` body field, then from the `X-CSRF-Token` header, then from the query string. A token in the query string is deprecated: it still passes, with a deprecation, and so does a value a caller read from the URL itself and handed to `checkToken()`. Setting the `thelia.token.accept_query_string` parameter to false refuses it; a later version will make that the default.
- `Thelia\Domain\Media\MediaFacade::__construct()` takes `FileProcessorService` and `ProductMediaOrder` on top of what it took. A module instantiating it itself has to pass them; a module reading it from the container has nothing to do.
- The images and the videos of a product share one sequence of positions. Moving a product image up or down swaps it with the medium right before or after it, whichever table that medium lives in, and deleting one closes the gap in both tables. A module reading `product_image.position` alone may find gaps where the videos sit.
- `Thelia\Model\Cart::getTaxedAmount()`, `getTotalAmount()` and `getTotalVAT()` take a trailing `$withGiftWrapping` argument, false by default, so nothing a module calls changes; the grand totals the front reads pass true. `Thelia\Domain\Order\OrderFacade::__construct()` takes `GiftWrappingLineFactory` and `Thelia\Domain\Cart\CartFacade::__construct()` takes `CartGiftWrappingService` on top of what they took. A module instantiating one of them itself has to follow; a module reading it from the container has nothing to do.
- `Thelia\Domain\Invoice\EventListener\AllocateInvoiceRefListener::__construct()` takes `OrderHistoryRecorder` on top of what it took. A module instantiating it itself has to pass it.
- The personal data export carries a `customer_lists` section next to the core ones (`CustomerPersonalDataExporter::CORE_SECTION_NAMES`), and a `history` key on each order. Code comparing the exact keys of the export sees them.
- `order_product` carries `line_type`, `product` on every line written so far and `service` on the ones the shop invoices beside the goods. Anything walking the lines of an order to pick, ship, weigh or count stock reads `OrderProduct::isProductLine()`; anything totalling money keeps counting them all.
- The `file` column of `product_image`, `category_image`, `content_image`, `folder_image`, `brand_image` and `module_image` is dropped by `3.2.0.sql`: the file of an image is now held by `<type>_image_i18n.file`, nullable, one per language. The script copies the file into the translation of every active language and of the default language, creating the translation when the image had none, so every language keeps the picture it showed before. A shop that came from Thelia 2.6, whose files are already in the translations, keeps them and only gets the column made nullable. `getFile()` keeps its signature and returns the file of the current locale, so code going through the models has nothing to do; code that reads the column itself, in SQL or from the table map, has to read the translation instead. GoogleShoppingXml 4.0.0 and EasyProductManager 4.0.0 read the translation; earlier versions read the dropped column.
- API Platform is pinned to 4.3.x. `core/composer.json` declares a conflict with `api-platform/symfony` and with its split packages (`documentation`, `http-cache`, `hydra`, `json-schema`, `jsonld`, `metadata`, `openapi`, `serializer`, `state`, `validator`) from 4.4.0 up to, and excluding, 5.0. The 4.4 upgrade command breaks the console on a shop without Doctrine, and the split packages, which `api-platform/symfony` 4.3 accepts in `^4.3`, resolved to 4.4 and raised a filter declaration deprecation for every operation. A project or a module that requires API Platform 4.4 cannot be installed next to the core.
- In the `default-twig` back-office theme, `BackOfficeDefaultTwigBundle\Service\Dashboard\DashboardStatsProvider::__construct()` takes `PeriodOptions`, which now builds the period presets the dashboard and the reports share. A module instantiating the provider itself has to pass it.
- The core requires the 1.2 line of the Flexy and default-twig themes: `thelia/core` conflicts with `thelia/flexy` and `thelia/backoffice-default-twig-template` below 1.2, since Flexy 1.1 listens to `ORDER_CART_CLEAR`, which the core no longer raises, and the 1.1 back-office theme sends none of the form tokens the core now checks. Composer refuses an update that would keep a 1.1 theme and says why.
- `Thelia\Action\Cart`, `Thelia\Action\Tax`, `Thelia\Api\Bridge\Propel\Serializer\CartNormalizer` and `Thelia\Api\Service\DataAccess\AttributeAccessService` take `VatExemptionResolver`, and `Thelia\Domain\Order\OrderFacade` takes `VatExemptionResolver` and `ExemptedVatCalculator`. `Thelia\Domain\Order\Service\OrderAddressPersister::prepareOrderAddresses()` takes `bool $vatExempted` before the connection. A module instantiating one of them itself has to follow; a module reading it from the container has nothing to do. In the back-office theme, `BackOfficeDefaultTwigBundle\Controller\Customer\AddressController` takes `VatVerificationAvailability`.
- Saving an `Address` whose VAT number or country changed clears `vat_verified_at` and `vat_verified_name`, unless the same save writes the verification. Saving an `OrderAddress` with a new VAT number does the same.
- `Cart::getPostage()` and `Cart::getPostageTax()` hold the quote of the delivery module. What the buyer owes is read from `Cart::getTaxedPostage()`, `Cart::getUntaxedPostage()` and `Cart::getPostageTaxAmount()`, which take the exemption into account; the front API `Cart.postage` and `Cart.postageTax` and the Flexy `postage` and `postage_tax` cart attributes follow them.

## Security

- GHSA-cfvv-2jvw-x2hp — the back-office toggle and position actions inherited from `AbstractCrudController` changed data without checking the session token, and a deletion read its token from the URL only, where access logs, browser history and Referer headers keep it. These actions now check the token, look for it in the request body or the `X-CSRF-Token` header before the URL, and compare it in constant time.

- GHSA-gvcv-hvpp-89gx — an SVG store logo or banner reached the web space as it was uploaded: the image cache linked to it or copied it into `public/cache/images/`, so a script or an event handler it carried ran on the shop origin for anyone opening its URL, which every front page advertises in `og:image`. The image cache now publishes an SVG only as a copy stripped of its active content, whatever `original_image_delivery_mode` says, replaces the links earlier versions left there and publishes nothing for an SVG it cannot read. The SVG sanitizer applied to uploads also drops processing instructions, the document type declaration and its entities, XHTML elements, and javascript: or data: URIs behind any attribute prefix, and refuses a file whose root is not an SVG element; a raster image embedded as base64 is kept. Run `php bin/console image-cache:clear` after the update to drop resized copies made from an unsanitized SVG.

# 3.1.2

Security release of the 3.1 line, without any breaking change. It ships `setup/update/sql/3.1.2.sql`, which changes no schema and records the new version, so that the update runs and protects the documents already published. `thelia/setup` ships as 3.1.3 with this core, its 3.1.2 tag being already taken; `thelia/config` does not change and stays at 3.1.1. The core refuses a back-office theme `thelia/backoffice-default-twig-template` older than 1.1.2, which carries the same CSV export fix for the newsletter subscribers: update it to 1.1.2 at the same time. The front theme does not change.

## Security

- [GHSA-5524-qfxp-33v9](https://github.com/thelia/thelia/security/advisories/GHSA-5524-qfxp-33v9) — the upload policy checked the file name the client sent, while storage keeps only its letters, digits, dashes, underscores and dots. A document named `report.php ` (trailing space), `report.ph p` or `report.ph#p` passed both the extension blacklist and the server-executable floor, was stored as `report-1.php` and published under `public/cache/documents/`, where a web server that runs PHP in the document root executed it. The policy now checks the name the file is stored under as well as the name it was sent with, and storage itself refuses a server-executable name, so a caller that skips the policy cannot store one either. The SVG sanitizer also recognises an SVG by its stored name. Files already stored under such a name stay in place after the update: see the upgrade notes.

- [GHSA-7wrm-pcw6-4g9m](https://github.com/thelia/thelia/security/advisories/GHSA-7wrm-pcw6-4g9m) — a document upload accepted HTML and the other types a browser opens as a page or runs as a script, and the shop published them under `public/cache/documents/`, so a script carried by a document ran on the shop origin for anyone opening its link. Documents with an HTML, XHTML, XML, XSL, JavaScript or compressed SVG extension are now refused, whatever `document_upload_forbidden_extensions` says; the list is `FileConfiguration::BROWSER_ACTIVE_DOCUMENT_EXTENSIONS`. The file endpoints of the API serve a document as a download, with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`. On Apache, the document cache gets an `.htaccess`, written by `update.php` for the documents already published and before any document is published, that sends `nosniff` for every document and `attachment` for all but PDF, raster image and plain text files, which still open in the browser. It needs the `FileInfo` override that `public/.htaccess` already needs. nginx does not read it: the upgrade notes give the equivalent block. Documents uploaded before the update stay published until they are removed, as the upgrade notes describe.

- [GHSA-gcgv-f8rf-w2wc](https://github.com/thelia/thelia/security/advisories/GHSA-gcgv-f8rf-w2wc) — the CSV exports wrote customer and newsletter subscriber names, addresses and phone numbers exactly as typed, so a value starting with `=`, `+`, `-` or `@` reached the file as a formula that ran in the spreadsheet of the administrator who opened it. Any text cell of a CSV export, from the core or from a module, that starts with one of these characters, a tab or a carriage return now gets a leading `'` and stays text. A plain number such as `-5.00` or `+33612345678` is written unchanged. A cell holding `,` or `;` is also enclosed in quotes, so a spreadsheet that splits lines on the other separator cannot start a formula halfway through it. JSON, XML and YAML exports are unchanged. The back-office theme 1.1.2 writes the newsletter subscriber export the same way.

## Fixed

- A `Thelia\Core\File\Exception\FileException` built from a message alone threw a `TypeError` instead of itself.
- The not found page of a hidden product, brand, category, folder or content no longer keeps the view and the id of the page it refused.

## Upgrade notes

Update the core and the setup package, then the database, from the root of the project, and warm the cache up. The project keeps the 3.1 line of the skeleton, so Composer keeps the core on 3.1:

```bash
composer update thelia/core thelia/setup --with-all-dependencies
php local/setup/update.php
php Thelia cache:warmup --env=prod
```

The update script writes the `.htaccess` of the document cache (`public/cache/documents/` by default), so on Apache the documents already published are served with the download headers from then on. If it cannot write the file, it prints a warning naming `Thelia\Core\File\DocumentCacheProtection`, which writes it. nginx does not read `.htaccess`: add this block to the server block of the shop. The `^~` prefix also keeps the PHP location away from the document cache.

```nginx
location ^~ /cache/documents/ {
    add_header X-Content-Type-Options "nosniff" always;
    add_header Content-Disposition "attachment" always;

    location ~* \.(?:pdf|jpe?g|png|gif|webp|avif|txt)$ {
        add_header X-Content-Type-Options "nosniff" always;
    }
}
```

### Files uploaded before the update

The update refuses such uploads from now on, but it leaves the files already stored where they are. Look for an image or a document stored under a name a web server runs (GHSA-5524-qfxp-33v9), and for a document a browser opens as a page or runs as a script (GHSA-7wrm-pcw6-4g9m). From the root of the project, with GNU find:

```bash
find local/media public/cache -regextype posix-extended \
  ! -type d ! -path public/cache/documents/.htaccess \
  \( -iregex '.*/[^/]*\.(ph(p[3-8st]?|t(ml?)?|ar)|s(html?|tm)|ht(access|passwd))(\.[^/]*)?' \
  -o -iregex '[^/]+/[^/]+/documents/.*\.(html?|x(ht(ml?)?|ml|slt?|spf|ul)|m(ht(ml)?|ml)|r(df|ss)|atom|kml|svgz|[cm]?js)' \) \
  -print
```

The first pattern finds a server-executable extension anywhere in a file name (`report-1.php`, `shell.php.jpg`), among images and documents; the second finds an HTML, XML, XSL, JavaScript or compressed SVG document. The command only lists files. It skips the `.htaccess` the update writes, and it lists the links of the cache whose target is already gone. If the shop keeps its media or its document cache elsewhere (`document_cache_dir_from_web_root`), change the paths.

For each file listed under `local/media/`, delete the image or the document from the back office, on the Images or Documents tab of its product, category, content, folder or brand, so its row goes too. Then run the command again with `-delete` in place of `-print` to remove the files left behind.

In 3.1.2, deleting an image or a document over the admin API still removes its row only, and deleting it from the back office still leaves its copy under `public/cache/`: delete them from the back office, then run the command with `-delete`. A file deleted before the update is still served at its URL; the command lists it as well.

# 3.1.1

Security release of the 3.1 line, without any breaking change. It ships `setup/update/sql/3.1.1.sql`, which changes no schema and only records the new version; `thelia/setup` ships as 3.1.2 with this core; `thelia/config` does not change and stays at 3.1.1. Update the back-office theme `thelia/backoffice-default-twig-template` to 1.1.1 at the same time.

## Security

- GHSA-gvcv-hvpp-89gx — uploaded SVG images are sanitized more strictly and only reach the image cache once sanitized, and the back-office theme 1.1.1 runs the store logo and banner through the same checks. Run `php bin/console image-cache:clear` after the update.
- GHSA-j2c3-9c4q-c2ch — fixed in the back-office theme 1.1.1: the combinations and default price forms of a product only save through a POST that carries the form token.

## Fixed

- A fresh install no longer dies at boot on `thelia/thelia-library-module` 2.0.10, which relies on a class of the 3.2 core: the core now declares a conflict with that release, so Composer keeps 2.0.9.

# 3.1.0

First minor of the 3.x line. 88 commits since 3.0.0. The version number follows the update script this release ships, `setup/update/sql/3.1.0.sql`, which carries the tables and columns behind guest checkout, checkout consents, the audience and countdown of a sale, automatic promotions and offered lines, order returns, order status transitions, product relation types, customer tags and configurable checkout steps.

## Security

- GHSA-59cp-795h-6wgx — registering as a guest with an address somebody had already used as a guest handed back a token that could set a password on their account, and a password waiting for its activation code could be replaced by anyone holding such a token. The guest token now records whether its registration opened the row, and only that token, or the tracking link of an order mailed to the address, completes the account; a password waiting for its code is only replaced by a caller holding a tracking link.
- GHSA-m887-7g6m-w83g — the listeners that decide something from the request path (admin API permissions, API rate limits, refresh-token limits, security log, API statelessness) read the path as the client spelled it, while the router decodes it first, so an encoded spelling such as `/api/%61dmin/...` reached the admin API without the per-resource permission check. Every path comparison now reads the path the way the router does, and the admin permission check also recognises an admin operation from the route it matched, whatever the spelling.
- GHSA-r63g-6wfg-v5v9 — a coupon condition summary is built from a message that carries its own markup, and the back office renders it as such; the customer names, product, category, country and module titles it inserted came straight from the database, so a name holding a tag ran as a tag on the coupon screen. Every value a condition summary inserts is now escaped before it reaches the message, and the message keeps its markup.
- The form firewall stores IPv6 addresses. A fifteen-character column only held an IPv4 address, so a visitor arriving over IPv6 was counted by no attempt at all.
- The remember-me token is retired on logout and on a password change.
- The administrator password is generated when the install is given none.

## Behaviour changes

These apply to any shop that updates, without asking for them. The first two concern integrations that call the API.

- The API page size is capped at one hundred. The caller picks its page size and nothing bounded it, so a single call could ask the shop to load, hydrate and serialize a whole table. An integration asking for more now receives one hundred items without an error: if it walks a catalogue assuming it gets everything at once, it has to move to paginated reads. A project that needs another ceiling redefines `pagination_maximum_items_per_page` in its own `api_platform` configuration.
- The API limits its rate. Two hundred requests a minute for an anonymous caller, eight hundred for an authenticated customer, two thousand for the administration, ten failed login attempts and twenty token refreshes. The `THELIA_API_RATE_LIMIT_*` variables set each ceiling, and a list of addresses and CIDR ranges exempts trusted callers.
- The connection character set is named in the DSN, `utf8mb4`, when the DSN named none. A shop that picked its own keeps it: a DSN written in a `database.yml` is taken as it is. A database inherited from a Thelia 2 migration whose tables stayed in `latin1` has to name its set in the DSN before updating.
- The session id is renewed when a customer or an administrator authenticates. An integration that carried the session id across the login has to read the one in the response.
- Every response carries three headers by default: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`. They are only set when absent, so a shop writing its own keeps the last word. A shop displayed in an iframe on another domain has to write its own `X-Frame-Options`.
- The `locale` filter refuses an inactive language instead of accepting it, and names the active languages in its message.
- The cart refuses a zero or negative quantity. Removing an article is done by deleting the line, not by setting its quantity to zero.
- `publicUrl` answers in the language that was asked for. The value came from whatever language the translation loop had left behind, which produced an address in a language nobody had requested.
- The logging handlers a shop declares win over those of the core. A core default always beat the file written by the shop, which could write thousands of lines per request into the error log.
- `/api/front/currencies` and `/api/front/currencies/{id}` serialize through a read group, and the response no longer carries `visible`, `createdAt` and `updatedAt`. A headless front that read `visible` now finds no such field, without an error.
- Writing `null` into a NOT NULL column through the API is ignored instead of failing: the column keeps its value and the call answers 200 where it used to answer 500. #3948
- When `THELIA_CACHE_DSN` names a backend, the configuration table is cached without expiry and invalidated when a setting is written through the model. A setting written straight in SQL, a restored dump or a second application writing the table stays unseen until the cache is cleared.
- On a persistent worker runtime such as FrankenPHP or RoadRunner, the memo of the active languages and of the default country is no longer reset on every request; a change written outside the model is seen when the worker restarts. PHP-FPM shops see no difference.
- A customer can open at most twenty return requests an hour, under the `order_return_request_per_client` limiter.

## Breaking changes

- `OrderService::saveTransactionRef()` takes the transaction reference as a string instead of an integer. A gateway reference is a string: a payment module passing an integer was already truncating it.
- `AdminForm::SEO` is `thelia_seo` instead of `thelia.admin.seo`. The constant named something the registry did not know. A module reading the constant has nothing to do; a module that hard-coded the string has to fix it.
- `ResourceService::preload()` takes the collection to preload and the language, instead of the collection, the resource class and the context.
- The product price filter `PriceFilter`, which nothing called, is removed.
- The `accessory` table gains a `type_id` foreign key towards the new relation types, and stops being declared as a pure junction table. Propel therefore no longer generates the many-to-many methods `getProductsRelatedByAccessory()`, `addProductRelatedByAccessory()` and their twenty-odd neighbours on `Product` and `ProductQuery`. Nothing in the core, the bundled themes or the published modules calls them; a third-party module that does goes through `AccessoryQuery`, or through `addAccessory()` and `removeAccessory()`, which are unchanged. #3920
- `Thelia\Action\Product` takes a second constructor argument, `ReservedSaleVisibility`, and its `addAccessory()` and `removeAccessory()` listeners receive the event name and the dispatcher like every other listener. A module that instantiates the action itself or calls those two methods directly has to follow; dispatching `TheliaEvents::PRODUCT_ADD_ACCESSORY` and `PRODUCT_REMOVE_ACCESSORY` is unchanged.
- `CheckoutValidationService` is built from the tagged checkout step providers instead of a `CartGuard`. A module that instantiated or decorated it with a `CartGuard` has to follow.
- `CouponCreateOrUpdateEvent::getCode()` may return null, since an automatic promotion has no code. A caller under `strict_types` that hands the value to a `string` parameter has to deal with the null, and an override has to widen its return type.
- `CustomerPersonalDataExporter::CORE_SECTION_NAMES` gains `order_returns`. A module that counted or pinned the exported sections has to follow.
- The test double `Thelia\Test\RecordingMailerFactory` drops `sendEmailToCustomer()` for `sendEmailMessageOrFail()`; only the test suite of a module is concerned.

No route or API operation is removed, no existing interface changes and no event disappears: a module that extends the core through its events, hooks and interfaces has nothing to revisit beyond the constructors listed above.

## Checkout

A visitor can order without opening an account. The guest is a `customer` row like any other, with no password, marked by `is_guest`: everything an order already hangs off, the addresses, the invoice, the history, keeps working unchanged. The guest finds the order again through its tracking token, and can turn the purchase into an account afterwards.

The feature ships switched off: the `guest_checkout_mode` setting decides, and a shop that updates keeps the tunnel it had. A product whose after-sales needs an account, a subscription or a downloadable licence, is kept out of the guest tunnel by `guest_checkout_forbidden`.

The tunnel itself is now described by configuration rather than by theme code. A `checkout_step` table carries the order, the activation and the wording of each step, while what a step does lives on a `CheckoutStepProviderInterface` service, so a module can ship a step of its own. Turning a step off removes its screen and never its check: placement runs the check of every registered provider, so a module step is enforced at payment and not only on screen. A broken configuration falls back to the shipped defaults with a warning in the log, and a cart with nothing to ship no longer sees the delivery screen. The `checkout_display_mode` setting publishes the form the theme renders, steps or a single page. Fresh installs get the four current steps; an updated shop keeps the historical wordings. The developer map is in `docs/checkout-steps.md`. #3942

The buyer accepts the terms and conditions before paying, and the merchant manages further consent boxes from the back office. A consent carries translated wording, an optional link to a content, a mandatory or optional flag and a position, and can be deactivated without being deleted. The `terms_and_conditions` consent is created on install and on update, taking over the content the `terms_conditions_content_id` setting points at. A mandatory active consent left unanswered stops the order and names the consent by the wording the buyer was shown. One `order_consent` row is written per active consent in the order transaction, freezing the wording as displayed, the answer, the date and the buyer's IP address, so a later rewording never rewrites what was accepted. Orders created from the back office or the command line record nothing, since nobody was asked. The IP address travels with the customer's personal data export, and anonymization erases it while keeping the wording, the answer and the date. #3896

## Promotions and sales

A cart promotion can apply on its own. It carries no code, is evaluated on every cart change, and shows in the summary under its public title. The engine is the coupon one: a `trigger_mode` column decides whether a code is required, and automatic promotions join the session coupons before the usual sort. #3928

A new `BuyXGetY` effect is described in data: a triggering lot (a quantity of products, of a category, of a selection, or the whole cart), what the offer covers (the same lot, a named product, the cheapest of the lot) and the discount (free, a percentage, an amount). The whole-cart lot forms a single lot whatever the cart holds and reads the triggering quantity as a floor, which is how a merchant says "one gift from a spending threshold" without seven articles producing seven gifts. When the offer names another product, the line is added by a dedicated service after the evaluation. An offered line is flagged in the database, refused on the event path and on the front API, sized down to the remaining stock, and taken back when the promotion stops applying. A gift out of stock never blocks the order: the promotion is skipped and the shopper is told. #3931

An order freezes what it used. `order_coupon` gains the coupon id and the serialized effects, so a later edit of the coupon never rewrites the order and a codeless promotion stays countable. `order_product.is_offered` copies the cart marker when the order is written, so the back office, the invoice and a partial refund see a gift as a gift. A new `MatchDeliveryModules` condition covers the chosen carrier. The promotions are priced again just before the order is written, for the buyer who sat on the payment page while an automatic promotion expired or ran out of stock.

A sale can be public or reserved for named customers, and can hide its products from everyone else instead of showing them at their usual price. A reserved sale never writes `promo` or `promo_price` to the catalogue: its price is resolved at read time for entitled customers only, batched, with the same taxed-offset formula as the public path. Visibility is enforced at query level across the front API, the loops, the product view and the theme sitemap. Cart prices are settled when the cart changes, at sign-in and on restore, and an order keeps the price it was placed at. A sale also carries a countdown display setting: never, from a given number of hours before the end, or from the opening. Read-only `/front/sales` and `/admin/sales` resources expose the settings, the remaining seconds and the rewritten url. `docs/reserved-sales.md` maps the feature. #3921

## Orders

A customer can request a return, and the shop processes it to the end. Returns ship switched off, behind `order_return_enabled`, with a window of fourteen days set by `order_return_window_days`. A request carries its lines, a reason drawn from a list the merchant manages, and moves through a state machine: requested, information awaited, accepted, received, refused, settled, expired. Eligibility is checked line by line against what was already returned, the postage can only be returned once, the refund amount is computed from the order, and receiving a return can put the stock back. The return has a reference of its own, a PDF document rendered in the customer's language, back-office screens, front-office screens in the customer account, and admin and front API resources. Returns are part of the personal data export and of anonymization.

The merchant declares, status by status, which statuses an order may move to, and what runs when an order enters a status or takes a given transition. A status with no declared transition stays free, so a fresh install and an updated 3.0.0 shop behave exactly as before. The guard sits in the core status listener, so the back office, the admin API, payment modules returning from a provider and console commands are held to the same graph, and a custom status follows the graph of the canonical status it stands for. Forcing a refused transition is a right of its own, `admin.order.status-force`, traced in the admin log. Actions are services collected by tag, so a module adds an effect by declaring a service; the shipped ones are the mail to the customer, the mail to the shop managers, the stock movement, the invoice numbering and the coupon release, seeded switched off next to the existing core listeners. A failing action is journalled and skipped without undoing the status change, and the following actions still run. #3939

## Catalog

The relation between two products carries a configurable type. Three types ship and are active on a fresh install: accessory, cross-selling, which is reciprocal, and up-selling, each with a translated label and a stable code. Every relation already saved takes the accessory type, and the `accessory` table keeps its columns, so a module querying `AccessoryQuery` keeps working. The merchant creates, renames, reorders, hides and deletes the types from the back office, and deleting a type a shop still uses is refused rather than taking its relations along. Positions are scoped to the product and the type, so reordering one block leaves the others where they were. The facade gains `addAssociation()`, `removeAssociation()` and `getAssociations()`, alongside three new events; `addAccessory()`, `removeAccessory()` and the three accessory events are kept and still fire. A self-relation is refused. #3920, #3941

The product collection of the API sorts by creation date and by title. #3885

## Customers

- A visitor signing in from a page comes back to that page instead of being sent to their account. #3903
- Customer records carry free-text tags. A tag has a label and a colour, is created from the tag configuration screen or attached from a customer screen, and a label already taken is refused with the tag standing in the way named in the message. The customer list shows a tag column and filters on a tag, reading the tags of every row in one query. Two tags can be merged, and a console command, `tag:prune-orphans`, lists or removes attachments pointing at a customer that no longer exists. Anonymizing a customer drops their tags, and the personal data export handed to the customer carries no trace of them; an admin API resource exposes them.

## API

- The rewritten urls of a page resolve in one read, the relations reached under a collection are read in batches, and a paginated collection is counted once. #3910, #3905, #3907
- The addons of a resource are no longer rebuilt when a parent is reached through a back reference. #3908
- The front currency resource declares a read group, so it answers again instead of walking every accessible getter until it meets a Propel table map.

## Performance

- The kernel no longer opens a second connection on every request to ask whether the shop is installed. #3909
- The configuration of a module is read in one go, the module of a hook is resolved once per process, and the configuration table is read once per request from a shared cache entry. #3901, #3900, #3915
- The active languages and the default country keep their memo across requests, which used to be a full table read per request on a persistent worker runtime. #3916
- A cached image is decoded only when something asks for the decoded object, instead of on every call. #3917

## Cache

The cache backend is chosen with an environment variable, `THELIA_CACHE_DSN`. It is empty by default, so a shop that updates does not change engine. #3892

## Fixes

- The module chosen for delivery and for payment is judged at order time the way it was judged when the offer was made.
- Boot survives a cache clear instead of requiring files that are gone: the `sql_mode` verdict and the Propel runtime cache are rebuilt rather than read.
- A module `config.xml` that declares a loop name is honoured again. #3925
- A written module row announces its change again.
- Link positions stay untouched when a link is read.
- The kernel survives a module template directory that is gone.
- Translation catalogues are exported with `var_export`.
- Every seeded message has a subject in every install locale, and the checkout steps and consent resource titles are seeded for every shop language.
- `bin/install --help` prints the options and exits, and an unknown option is refused before anything is created or dropped, where both used to run the full install against the database the environment pointed at. #3950
- A fresh install writes the administrator address into the shop notification list when the list is empty, so order and module notifications reach the merchant without a configuration step. #3951
- A coupon carrying no condition no longer writes an ERROR line in the shop log on a path that behaves as intended. #3952
- The generated Propel models no longer raise a PHP 8.5 deprecation: the Thelia fork of Propel, 1.0.3, writes canonical casts, drops `ReflectionProperty::setAccessible()` and `xml_parser_free()`. The core requires it.
- The core no longer raises a Symfony 7.4 deprecation on a page render: validator constraints take named arguments, the two API voters accept the vote argument, and the language URL form names its default protocol. The SEOne, RecentlyViewed, HeaderHighlights and CustomDelivery modules and the back-office theme received the same treatment in their own releases. #3955
- The update script removes the compiled container and the generated Propel models of the previous release before it boots, so a shop that ran `composer update` no longer has to clear its caches by hand before `local/setup/update.php`.
- A message a module seeded for Thelia 2, with `{$order_ref}` or `{config key="…"}` in its subject or body, is interpolated instead of reaching the customer as a raw placeholder: the simple Smarty variables are rewritten to Twig before rendering. The Cheque, CustomDelivery and VirtualProductDelivery modules also rewrite their seeds in their own releases. #3958
- The country choices of the address form no longer collapse every untranslated country under one blank entry: a country without a title in the current language is labelled by the title of the default language, then by its ISO code. #3957
- The demo shop ships with a carrier: the demo import attaches the CustomDelivery module to every shipping zone with a flat price, so a fresh `--with-demo` install can be ordered from end to end. #3953

# 3.0.0

First stable release of Thelia 3. 56 commits since 3.0.0-beta5. The version number follows the update script this release ships, `setup/update/sql/3.0.0.sql`, which carries the lost password mail wording, the canonical mail template configuration row, and deactivates the module the default install no longer ships.

## Security

Two coordinated advisories are fixed and published with this release. Shops running a beta should update without waiting.

- GHSA-v6v7-757c-2w5g — the front cart items API answered for any cart item id, whoever asked. Cart items are now scoped to the cart their caller owns, whether the caller is a logged-in customer or an anonymous session, and creating a cart no longer accepts a foreign owner.
- GHSA-p2h2-2622-q773 — any authenticated back-office account reached every admin API resource. The admin API now enforces the per-resource permissions the back-office profiles define.
- Coupons are no longer readable anonymously: `/api/front/coupons` requires an authenticated customer, so restricted coupon codes stay restricted.
- The front API no longer lists the installed modules and their exact versions; `/api/front/modules` is gone, the admin endpoint is unchanged.
- The cart token is assigned server-side. The body of `POST /api/front/carts` cannot choose it any more, which closes cart fixation; a headless client reads the token in the response instead of picking one.
- The lost password mail sends a signed, single-use reset link. The shop never generates, stores or mails a new password on someone else's request, and the form no longer discloses whether an address has an account. #3840
- The remember-me cookie carries `HttpOnly`, `Secure` and `SameSite`.

## Breaking changes

- The write group of the front cart API no longer contains the cart owner, its discount, or its token: those are set by the server. A front-office client that sent them must stop; readers are unaffected.
- `/api/front/coupons` answers 401 for anonymous callers, and `/api/front/modules` no longer exists.
- The constructor of `Thelia\Action\Customer` changed with the password reset rework. #3840
- A shop configuration value has the last word over the core defaults again. A project that overrode `title`, `version` or `stateless` in its own `config/packages/api_platform.yaml` to compensate should remove those keys. #3860
- Updating a Thelia 2 database in place is refused with an explicit message instead of silently replaying the 2.5 scripts; migrating from Thelia 2 is a separate, documented path. `UPDATE.md` now describes Thelia 3 in-place updates.
- The `VirtualProductControl` module left the default install: it only ever shipped a Thelia 2 Smarty back-office hook. The update script deactivates it; the module row comes back if it is ever reinstalled on purpose.
- A message reaches the transport in one place only, `MailerFactory::sendEmailMessageOrFail()`. `sendEmailMessage()`, `sendEmailToCustomer()` and `sendEmailToShopManagers()` delegate to their `OrFail` variant and swallow the `Thelia\Mailer\Exception\EmailNotSentException` it raises, so their callers are unaffected. A module that overrode `sendEmailMessage()` must override `sendEmailMessageOrFail()` instead, or its code no longer runs.

## Front office

- The contact form linked in the footer submits: the core handles `POST /contact`, mails the store with the visitor address as reply-to, and answers on the page. #3838
- A visitor who checks several sub-categories, several brands or several values of one feature widens the list instead of emptying it. #3867, #3829, #3828, #3830
- The tfilters facets are counted from the products the visible filter keeps, and each feature and attribute facet reads in its own mode. #3830
- Front sale elements are priced in the currency being browsed, not the shop default. #3835
- Thelia forms can opt into stateless CSRF tokens, so a cached product page no longer rejects the first add-to-cart of a fresh visitor. Session tokens stay the default. #3825
- A payment return reaches the checkout routes Thelia 3 declares instead of Thelia 2 route names. #3836
- A parser rendering outside HTTP (mail from a command, PDF from a cron) reports no request instead of crashing. #3827

## Performance

- The API bridge stops paying for data the serializer discards, memoizes and batches the rewritten url lookups, and reads the config table, the default country and the active languages once per request; to-many relations of a collection load in one query each. The demo home went from about 1400 SQL queries to about 150. #3812, #3813, #3814, #3823, #3839, #3844
- The resource addons aggregate in front of the serializer metadata cache, so `cache:warmup` no longer strips them from API responses. #3834

## API

- A write answers with the translation it saved, not the one it replaced. #3831
- Thelia forms build from the injected form factory builder, so form extensions registered through `thelia.forms.extension` apply again. #3832
- The api documentation page wears the Thelia colours, title and version. #3853

## Back office

- Submitting the admin login while a session is already open redirects to the requested page instead of erroring. #3822

## Install and update

- Environment overrides stay out of the shared config cache, and the first install run wires the themes it was given. #3859, #3852
- The email template configuration lives under the single name every shop reads; the update script carries a value stored by a beta over. #3858
- The install skips the Sass build when no active theme ships Sass sources. #3842
- An order export totals an order once, whatever its number of tax and coupon rows, and totals the exported orders the way they were invoiced. #3848, #3843
- The `nl_NL` language Thelia 2 shipped is seeded again, eight languages in all. #3851
- PHP is required as `>= 8.3 < 9.0` everywhere the bound is checked, and the test suite runs on PHP 8.3, 8.4 and 8.5.

## Project

- `SECURITY.md` is a coordinated vulnerability disclosure policy with an incident response process, and every release ships a CycloneDX SBOM.
- The yaml config and the twig themes are linted on every CI run, after the propel models are built.
- The `thelia/config` and `thelia/setup` packages declare a description and a license, so `composer validate` passes on both.

# 3.0.0-beta5

8 commits since 3.0.0-beta4. The version number follows the update script this release ships, `setup/update/sql/3.0.0-beta5.sql`, which adds the legal identifier columns to the address, cart address and order address tables.

## Orders and invoicing

- The rounding rule an order is totalled with is configurable. `order_rounding_mode` keeps the historical unit-price rounding as the default, and a shop can opt into rounding the line totals instead. A console command switches the mode, reports how the figures of existing orders would move, and freezes the totals of the orders placed before the switch. The api exposes order unit amounts with the precision the order total uses. #3801
- A business address carries the legal identifiers electronic invoicing asks for: a company registration number and a VAT number, entered on the address, copied to the cart address and frozen on the order address, next to the company name they identify. The format is checked only on a value that was actually typed, and is country-aware. Neither identifier is ever mandatory and existing rows stay NULL, so nothing is required retroactively. #3798, #3804

## Front office

- Checking several values of the same feature filter widens the product list instead of emptying it: the values of one feature are OR-ed together, distinct features still narrow the list. #3810
- A rewritten url that spells out a path keeps its separators instead of losing them to the url sanitizer. A shop that wants flat urls can still forbid the slash through the configuration key. #3809
- The front controllers point at the router service and the routes a Thelia 3 install registers, so their redirects resolve instead of failing when a visitor reaches them. #3802
- A live component renders again: the Thelia view listener no longer claims the default render action of a component as an unknown view and answers 404 before the component is rendered. #3798

## Emails

- A mail message that has no template file renders the body stored in the database with the default parser, instead of being silently lost. #3803

## Install

- A fresh install of the development repository resolves again: the version of the `core/` path repository follows the release, and the bundle list no longer registers an asset bundle nothing installs. #3799

# 3.0.0-beta4

30 commits since 3.0.0-beta3. The version number follows the update script this release ships, `setup/update/sql/3.0.0-beta4.sql`. That script carries no database change: every fix of this cycle lives in code, and the file exists so that the updater finds a script matching the version marker instead of replaying the whole history.

## Updating a shop

- The update loop runs on the connection Propel hands out, and `setup/update.php` bootstraps on a thelia-project layout: updating an existing shop works again. #3769, #3770
- The backup taken before an update restores, where NULL values used to be dumped as empty strings, and it is allowed when `memory_limit` is unlimited instead of being refused outright. #3778, #3792
- An update run is reported for what it did instead of a phantom transaction error, the script aborts cleanly and exits non-zero when stdin gives no answer, and a missing backup file no longer breaks the run. #3776, #3768, #3759

## Installing a shop

- A fresh install serves its first page: `bin/install` creates `var/translations`, an AssetMapper path that `symfony/ux-translator` registers but never creates at boot. #3791
- The back office stylesheet is built during install, now that the `default-twig` theme ships on AssetMapper with sass-bundle: no npm anywhere any more, Node.js is no longer a prerequisite. #3795
- The `sql_mode` verdict cached during install is read from the server configuration, not from the session that computed it, so web requests get the session mode they need on MySQL, and `STRICT_TRANS_TABLES` stays in the session mode on both engines. #3793, #3782
- `composer/installers` 2.x is accepted, and `thelia/setup` and `thelia/config` declare a branch alias so their development line resolves. #3781, #3790

## Front office

- An unknown front office path answers 404 instead of 500. #3762
- A redirect lands on the domain of the requested language, and a rewritten url keeps its query parameters when it switches language. #3775, #3771
- SVG images are served as they are instead of being handed to the raster image pipeline. #3761

## Modules

- The module refresh keeps going when one module raises a PHP error, and resolves the descriptor path of a module before the refresh can fail on it.
- A generated module no longer ships a deprecated `routing.xml`. #3766

## Console and internals

- Deprecation logs stay out of the production error log. #3764
- The three worst-rated legacy classes were split into smaller units, without a change of behaviour. #3759
- Tests cover the virtual product download link and route, guard the core against deprecated `Request::get()` call sites, and assert the theme render instead of skipping it once the theme vendors are installed. #3763, #3765, #3756, #3757
- The Readme documents the AssetMapper asset builds, front and back, and carries the build, release and stack badges. #3789, #3796, #3760

# 3.0.0-beta3

118 commits since 3.0.0-beta1. The version number follows the update script this release ships, `setup/update/sql/3.0.0-beta3.sql`, so that a fresh install does not read itself as out of date.

## Themes

- A theme declares in `config/views.yaml` the root templates that are views rendered by a controller, not pages. The file was read but its content was ignored, so every root template of a theme stayed reachable through a url made of its own name. A theme that ships no such file keeps the previous behaviour. #3617

## Customer data

- The core can anonymise an account and export the personal data it holds. It dispatches `CUSTOMER_ANONYMIZE` and `CUSTOMER_PERSONAL_DATA_EXPORT`, ships a console command for each, and a module adds its own sections to the export through a provider interface. #3588
- An anonymised account is marked as such, and an order outlives the module it was paid or shipped with. #3692, #3713
- The admin log no longer keeps the identity of a customer erased by an anonymisation. #3719
- The form firewall records that kept ip addresses forever are purged. #3684

## Store settings

- The legal identifiers of the shop have one configuration key each, instead of a single free-text field, which is what electronic invoicing asks for. The former key is kept and still read. #3748

## Shipping and taxes

- Each delivery module can carry its own postage tax rule. #3664, #3700, #3727
- The postage tax of a mixed cart is split between the rates the cart carries. #3721
- `cart.postage` and `order.postage` now have the same meaning, and the postage estimate is read as the object it is. #3698, #3683
- The tax calculator is replaceable through a factory service, and the tax discount helpers are replaceable, their static entry points deprecated. #3587, #3647
- The customer discount rate is stored on the order and handled as the decimal it is. #3532, #3542
- A cart discount is left untouched when nothing in the cart is taxable. #3612
- The tax factor cache is indexed on the order or cart it was computed for. #3596

## Virtual products

- The virtual document of a sale element is stored in its own table, exposed on the api, carried when a product is cloned, and persisted on product update. #3720, #3687, #3691, #3550
- A virtual download is reported only when the order has a virtual document. #3545
- The virtual flags of an order product are exposed on the front order payload. #3677

## Modules

- A module is upgraded in place instead of being deleted and reinstalled, and stays activated when its upgrade is refused. #3699, #3671
- Hooks are registered on install only, not on every module refresh, and a module can declare the position of its hooks. #3710, #3585
- `hook:clean` keeps the hook positions and the ignored hooks. #3561
- Deleting a module removes the exports and imports it leaves behind. #3670
- The back office tells the admin to deactivate a module that an order still references. #3548

## API

- An item is named after the surface the request was served from. #3738
- Upload constraints are published read-only on the file resources, the shop upload policy applies to api uploads, and the upload endpoints receive their parent item. #3643, #3637, #3636
- The front address endpoints are usable and safe, a single customer title can be read, and the cart address fields answer with the cart address they point at. #3733, #3734, #3716
- Address ownership is enforced on `GET /front/account/addresses/{id}`. #3448
- The folder parent id is exposed instead of a boolean. #3618
- `OrderProduct.virtualDocument` is typed as a string, and a null price is returned as null instead of being rounded. #3541, #3553

## Security

- Lost password requests and account activation code requests are rate limited, per address and per client. #3665, #3656
- The activation code is mailed with a subject and a usable link on registration. #3672
- An admin session is denied when the admin account was deleted. #3456
- An upload policy applies to every file upload and is configurable per shop, and the declared import formats are enforced on the uploaded file. #3582, #3597

## Install, update and seed

- `setup/update.php` runs again on the Thelia 3 line, the update chain resumes from the closest known version, and the columns added after the 3.0.0-beta1 tag are migrated. #3579, #3500, #3566
- The running version is written in the database on a fresh install, and the jwt key pair is generated by `bin/install`. #3577, #3576
- `generate:sql` is runnable again and the seed is reproducible; the update script templates nothing renders were dropped. #3735, #3737
- A fresh install serves its first page: `bin/install` builds the assets the active theme needs, and the bundle list no longer names a package nothing requires. #3749
- The shipped translations are seeded when a language is added, and the seed sources use the Twig syntax the mailer renders. #3718, #3726
- The seed carries the ISO 3166-1 countries that were missing, the French departments as states of France, the ISO 4217 numeric code on currencies, and the full ISO 3166-2 code as a computed field. Several outdated seed values were corrected. #3557, #3536, #3549, #3531, #3529, #3567, #3660, #3690, #3454
- A country can carry states without requiring one, and the address forms no longer offer a hidden state. #3723, #3706
- A random `APP_SECRET` is generated when none is set, and a missing one is detected from the env files.

## Front office

- A rewritten url is never redirected to itself when the language domain is missing or is the current one, and the current page is kept when switching to another language domain. #3630, #3506, #3504
- The stored rewritten url is handed back, and the url of a deleted object answers 404. #3615
- Rewritten urls are sanitized on insert. #3507
- Invalid coupon codes are removed from the cart session, coupons are consumed when the payment is confirmed rather than when the order is created, and cart item prices and special offer status are refreshed when a cart is restored. #3499, #3525, #3526
- `search_mode=any_word` supports multi-word searches in `search_in`. #3473
- `Customer::getLocale()` no longer fatals when the account has no language. #3688

## Emails

- Customer emails are rendered in the customer language when they are sent from the back office. #3543
- The parser template definition is restored when mail rendering fails. #3539

## Errors and logging

- Uncaught exceptions are logged before the error page stops event propagation. #3484
- The transaction is rolled back when a non-Exception error escapes an action. #3613
- An unknown back office template answers 404 instead of 500. #3573

## Exports and imports

- Every order is exported when no date range is given, and the command can supply one. #3650
- Old export cache files are purged before each export. #3501
- The columns an import expects are exposed so a csv template can be built. #3598
- The shipping tax rule title is exported as text, and the delivery country in its own column. #3689

## Performance

- The tfilters facet values are aggregated in the database. #3653
- The category child id cache is indexed on the depth it was walked with. #3611
- The Propel schema is kept when a cache clear cannot have changed it. #3600

## Console and internals

- Console commands are registered with `addCommand()`, deferred cache clears run from the console, and Thelia's own cache command is reachable. #3638, #3620
- The trusted proxy and host parameters are converted to what `Request` accepts. #3649
- Declared public visibility is kept when the PSR-4 registration redefines a service, and the dead service declarations the Thelia prototype load overwrites were dropped. #3708, #3652
- Thelia services stay reachable from the test container, and the http smoke suites fail on a broken page. #3639, #3666
- The tracked env, bundles and phpunit files match what the recipes install. #3651
- A custom order status can declare the canonical status it stands for. #3533

# 3.0.0-beta1

Thelia 3 is a major version. Main changes:

- PHP 8.3 minimum, Symfony 7.4 LTS
- API Platform 4.3, installed standalone
- New back office built with Twig (`thelia/backoffice-default-twig-template`), enabled by default
- New front office template Flexy (`thelia/flexy`), built with Twig and Symfony UX
- Email and PDF templates rendered with Twig (PDF generation through dompdf)
- Smarty stays available through the TheliaSmarty module for existing templates
- Propel models generated with native PHP types

The 2.6.x line is maintained on the `2.6` branch.

# 2.5.5
- #3267 Fix wrong .env location in TinyMCE filemanager config
- #3263 fix: better get current locale
- #3261 Bump cross-spawn from 7.0.3 to 7.0.6 in /templates/frontOffice/modern
- #3258 fix : alt and title in image
- #3254 Bump http-proxy-middleware from 2.0.6 to 2.0.7 in /templates/frontOffice/modern
- #3249 feat(ci): use git deploy
- #3248 fix(ci): preprod only on main
- #3247 Bump express from 4.19.2 to 4.21.0 in /templates/frontOffice/modern
- #3246 Bump axios from 1.1.3 to 1.7.4 in /templates/frontOffice/modern
- #3245 feat(ci): put in place ci
- #3242 Fixed rounding in taxes and taxed prices caclulation
- #3241 autofill contact form if user is a customer
- #3240 Fix/lang url
- #3230 forgot a rounding
- #3228 fix : rounding in cart and order
- #3226 Fix attributes and path commerce guys address
- #3221 Feat/payment modules
- #3220 Count in admin
- #3218 Fix Export product TTC
- #3214 Feat: add php Thelia loop:info command
- #3213 Show menu bar option
- #3212 Allow feature free text value to be set to 0
- #3208 Give the possibility to modify the PDF filename
- #3205 Update order-edit.html
- #3201 Fix a wrong loop instantiation (new + init instread of new)
- #3200 Update utils.php
- #3199 Fix uploaded file mode
- #3198 fix: image source in content card
- #3196 Bump express from 4.18.2 to 4.19.2 in /templates/frontOffice/modern
- #3195 Update CustomerController.php
- #3194 Update BaseCachedFile.php
- #3193 Update FolderBreadcrumbTrait.php
- #3192 Update CatalogBreadcrumbTrait.php
- #3191 Update CatalogBreadcrumbTrait.php
- #3189 Update FeatureAvController.php
- #3188 Update Product.php
- #3187 Bump webpack-dev-middleware from 5.3.3 to 5.3.4 in /templates/frontOffice/modern
- #3186 Fix Bo pse attribute value display when product doesn't have template
- #3185 Bump follow-redirects from 1.15.2 to 1.15.6 in /templates/frontOffice/modern
- #3183 Fix bad "setContainer" call on Symfony AbstractController
- #3182 Adding products & sub categories count by categorie
- #3179 Allow to modify pse from psesByProduct plugin by event
- #3175 Add symfony debug bunudle
- #3174 Fix inverted condition in ProductTaxedPricesExport
- #3173 Update BaseHook.php
- #3171 Fix order event not being dispatched if previous dispatch stop propagation
- #3170 Fix bad select values for coupon who remove postage
- #3169 fix: change default tttl for apache mod_expires
- #3159 Update categories.html
- #3158 DockerFile: Php image changed to php:8.2-fpm-alpine
- #3155 Mon Profil > Mon profil
- #3154 Adding Taiwan SQL
- #3153 Adding Taiwan locale
- #3147 Bump semver from 5.7.1 to 5.7.2 in /templates/frontOffice/modern
- #3146 Bump word-wrap from 1.2.3 to 1.2.5 in /templates/frontOffice/modern

# 2.5.4
- #3145 Feat Change Thelia version 2.5.3 to 2.5.4
- #3137 Fix translations countries
- #3134 Fix missing routing parent call
- #3129 Fix TinyMce file upload
- #3128 Fix backoffice breadcrumb for depth > 1
- #3126 Feat Add array key on loop result add row method
- #3122 Fix error message in preview mail
- #3121 Feat Update webpack.config.js
- #3120 Fix external-schema path in cached model generation
- #3118 Fix docker root user
- #3080 Feat Add encore_entry_preload_script_tags smarty function
# 2.5.3
- #3117 Improve unmatchable condition message
- #3116 Prevent many "NPE may occur here" phpstorm EA warnings
- #3115 Fix orderProduct rounding
- #3114 Remove twitter feed
- #3113 Fix: Contact form body + add translations
- #3109 Improve asset manifest path loading
- #3106 Fix autowiring for coupon type
- #3105 Fixed refresh crash if update directory is missing
- #3104 Update Symfony components to 6.3
- #3103 Fix autoconfigure for Coupon condition
- #3098 Prevent error on index.php in public directory
- #3097 Fix for old form who are not services
- #3095 no content seo on page 2
- #3094 Add tax engine as service and allow new tax type by module
- #3093 Allow autowiring in loop constructor
- #3092 Replacement of the superglobable by in services.php
- #3091 Allow to add autowired service in form construct
- #3090 Fix manifest path when not accessible from URL (docker)
- #3082 Fixed HookNavigation module
- #3081 Update MailingSystemController.php - testAction
- #3079 Fix format_money remove_zero_decimal parameter implementation
- #3078 Send proper event type when confirming customer account creation
- #3074 Use the PDO connection instead of the wrapped connection
# 2.5.2
- #3072 Allow to hide smarty "undefined" errors
- #3071 Better module configuration route check
- #3068 Update Symfony dependencies to 6.2
- #3064 Fix wrong mail content type
- #3063 Fix csv serializer
# 2.5.1
- #3057 Fix current view
- #3056 Feat/new frontoffice template
- #3055 Add smarty plugin to prefetch js assets
- #3054 Add isInFolder and isInCategory
- #3053 Fix doc links
- #3052 Apply resize to all layers and prevent flatten to keep animation
- #3051 Add pre order pay total calculation
- #3049 Change smtp password field type + add warning message
- #3048 Licences
- #3047 Fix smtp with special chars
- #3046 Reset html2pdf to spipu vendor and fix his version
- #3045 Add mailhog to docker
- #3044 Fix tax engine getter when session is null
- #3043 Fix versions in composer.json
- #3042 Move the product visibility switch to inner toolbar
- #3041 Disable always debug for update script
- #3040 Remove default debug log on propel init
- #3039 Template configuration consistency
- #3037 Fix remote smtp is disabled and no dsn in env
- #3036 Fix module config button for route in routing.xml
- #3035 Update FrontUtils
- #3034 ApiUtils accessible globaly on window object
- #3032 Added missing hooks in modern layout.tpl
# 2.5.0
- #3020 Item image edition hook
- #2979 Upgrade pdf invoice template
- #2974 Feature symfony encore
- #2968 Autowire Hooks
- #2964 Add image size to the loop
- #2962 Fix svg render function when using absolute url
- #2960 Carousel: add format argument
- #2957 Allow to override config in .env
- #2951 Add a function to erase a customer password
- #2949 Add a smarty block to display a component
- #2948 Better phone and cellphone input validation
- #2944 Add WebP compatibility
- #2932 Improve Thelia version list in back office
- #2918 Change session path to be more symfony compliant
- #2909 Handle exif rotation meta-data
- #2908 Add weight in smarty plugin pse
- #2906 Fix filterByIsEnabled in Coupon
- #2902 Change module skeleton to add update function and external-schema
- #2891 Move propel cache to specific env cache directory
- #2888 Add database configuration to .env
- #2879 Add pagination for order product list on order edit page
- #2874 Add infinite Scroll if complex_pagination is set in BO
- #2872 Add untaxed promo price in smarty plugin
- (Multiple PR) Update Symfony, Propel and Smarty to their latest release
# 2.4.5
- #2834 Add svg support
- #3015 RestoreCurrentCart also restore currency
- #3014 Fix docker for 2.4
- #2909 Protecting content from preg_replace errors
- #2858 Adding IDs in product SEO export
- #2846 Change link tag and append javascript init on order payment gateway
- #2841 Fix Spelling Mistakes
- #2832 Fix export with no date
- #2831 Fix case where lang is null
- #2830 Fix language when multi domain is enabled
- #2828 Fix wrong id in ProductSaleElementsDocument Loop
# 2.4.4
- #2821 Fix can set not active lang in front office
- #2820 Add translations page for customer titles
- #2819 Add a more modern template for Thelia front office
- #2818 Update Smarty and Markdown
- #2817 Update email-layout.tpl
- #2815 Fix license to be detectable by github
- #2814 Fix export end date calculation
- #2813 Fix csv export for multiline
- #2812 Export locale
- #2810 Removed wrong parameter in findOne()
- #2808 Add localPickup as delivery mode allowed list
- #2807 Add i18n for HookAnalytics configurations
- #2801 Add a command to switch the front template from CLI
- #2800 Fix missing state in CartPostage getDeliveryInformation
- #2799 Deep cloning of template definition
- #2797 Added template type to template paths cache cache key
- #2796 Fix delivery when state is null
- #2795 Fix new delivery with state interface
- #2794 Fix filter by product_image_id loop parameter
# 2.4.3
- #2792 Add GithubActions
- #2791 Fix compatibility to composer 2
- #2790 Fix state not checked in deliveries modules
- #2788 Fix issue #2787 TinyMce add link to img doesn't work
- #2785 Add config value to disallow module install by ZIP
- #2784 Fix TinyMce - display preview thumbs in a subfolder
- #2783 Fix state tax in product loop
- #2782 Fix typo in base tax rule names
- #2781 Improved the readme file
- #2780 Fix a bug where the path isn't correct if the template is in a directory
- #2779 Force return to page 1 after changing the limit of displayed products…
- #2777 The checkbox not appear on the product page - Tab Image
- #2776 Fix issue #2516 loop search_in param doesn't work
- #2775 Added tweeter feed to admin home
# 2.4.2
- #2773 Add description to module composer skeleton
- #2772 Update default config values
- #2771 Better default tax rules names
- #2770 Add shared option for services (from SF 2.8)
# 2.4.1
- #2765 Tax and Taxed price variables are now rounded in OrderProduct loop
- #2764 Fix MoneyFormat when have space in number
- #2763 Improvement on delivery events
- #2762 Fix total prices and taxes in order edit page
- #2761 Improve DeliveryPostage event to get more data
- #2760 Improve Pickup locations
- #2758 add a new event MODULE_DELIVERY_GET_PICKUP_LOCATION
- #2757 Fix model generation at module activation
- #2756 Fix Url are not rewritten if no locale in url
- #2754 Fix remove zero decimal on number > 1000
- #2752 Order by alpha_reverse returns an error in feature-availability loops
- #2748 Upgrade docker compose to a more mordern stack
# 2.4.0
- #2740 Fix defaultErrorFallback templateDfinition replacement
- #2739 Fix ignored_module_hook table update
- #2738 removed versionnable from schema example
- #2737 Add php < 7.4 requirements
- #2736 Optimized exports with JSON cache file and SQL request
- #2735 Tax calculation fixes, revamped
- #2734 Use select instead of input fields to choose template in B.O configuration parameter
- #2733 Carousel module improvements
- #2732 Sales are now considered done a invoice date
- #2731 Fix #2693 contents url on search page
- #2730 Fix issue #2698 bug on sales management
- #2729 Discount field is no longer require in CustomUpdateForm
- #2728 Fixed casperjs path
- #2724 Better discount calculation for untaxed prices
- #2721 Fix template delete issue
- #2718 Bump symfony/security from 2.8.47 to 2.8.50
- #2717 Fix coupon condition matching
- #2716 New reference related parameters to order loop
- #2715 Fix bad success url for image form
- #2713 Bump symfony/http-foundation from 2.8.47 to 2.8.52
- #2712 Bump symfony/cache from 3.4.18 to 3.4.35
- #2710 Bump symfony/dependency-injection from 2.8.47 to 2.8.50
- #2707 Improve product edit
- #2706 Fix mailing export col names
- #2705 Move date filtering to query initialization
- #2704 Fix double "[]" on choice render multiple
- #2697 Fix missing event in isStockManagedOnOrderCreation
- #2696 Add ID and ORDER_PRODUCT_ID to order_product_attribute_combination loop
- #2695 An empty cart is not a virtual cart
- #2691 Modules documentation display improvements
- #2687 Added a findAllChildId() method
- #2685 Added arrow navigation to documents and images management
- #2683 Fix tax rule collection query build when a state ID is passed to getTaxCalculatorCollection()
- #2681 Fix I18n when strictly mode is enable and only one I18n is present
- #2676 Fix Tlog on reponse when ConfigQuery is not generated on cache
- #2677 Profile management improvement
- #2673 Added quantity parameter to "Added to cart" popup url
- #2672 Improved import/export loops
- #2671 A 'change.pse' event is triggered on PSE value change
- #2670 Add company on BO customer address information
- #2665 Fix bad translation key
- #2664 Add BO brand search
- #2663 Improve SHOW_HOOK
- #2661 Fix issue #2660
- #2659 Improved ajax management in CartController
- #2658 Customer email language fix when sent from the BO
- #2657 Add option to show/Hide stats bloc
- #2655 Update var name error MailerFactory.php
- #2651 Allow to load tax rule without country
- #2650 Fix attribute-edit.html smarty error
- #2649 Docker & Docker compose update
- #2648 Fix #2647 Wrong edition URL in message template
- #2646 Fix #2592 Add Delivery address in order loop search in
- #2645 Add new event on contact submit
- #2644 Change two redirection from 302 to 301
- #2642 Fix missing parent preSave and postSave in Models
- #2640 Update address-update.html
- #2638 Remove Tlog in Propel init
- #2637 New "visible" parameter to pse loop
- #2634 Add phone on create customer modal
- #2633 Update propel dependency
- #2630 Change travis configuration, composer propel repo, root-namespace special compiled PHP functions
- #2629 Improve invoice and delivery interface
- #2628 Fix bo search order status color
- #2626 Front template improvements
- #2625 Disabled output compression when generating site map
- #2624 Exclude base_url from URL parameters
- #2623 Fix Tax calculator on country with state
- #2622 Propel schema generation is now protected from concurrency
- #2621 Add sort options to PSE loop, and allow to return all PSE
- #2618 Fix smarty cache default value, add country and customer_discount
- #2617 Added missing argument 'code' to the Coupon loop
- #2616 Allow use of CDN (e.g. alternate URL) on assets and documents
- #2615 feature_values filter is now working in Product loop
- #2614 Shipping zone configuration improvement
- #2613 Pagination fix
- #2611 Fixed loop arguments cache initialisation
- #2610 MailerFactory::send() is now wrapped in an exception handler
- #2609 Order details improvements
- #2608 Fix required fields for form smtp configuration
- #2607 Fix postage update when cart or coupon changes
- #2606 Added 3 new outputs to order loop
- #2605 Fix wrong order total (issue #2604)
- #2603 Added tax rule ID parameters to product loop
- #2602 Shipping zones button is no longer extra small in module list
- #2600 Check if symlink() is working when installing Thelia
- #2595 Fix Issue #2504
- #2593 Add css class "pse_id_field"
- #2590 Fix non-numeric values in PDF templates
- #2589 Added invoice-date order criteria
- #2587 Add cache for loop ArgDefinitions
- #2586 Fix for #2505 BackOffice dashboard refresh button
- #2585 Improve propel cache
- #2584 Fix for getting choices options in forms
- #2582 Fix loop feature, filter template
- #2581 Composer remove useless dependency ramsey/array_column
- #2580 Fix module postActivation with new propel integration
- #2579 Composer remove symfony/icu on thelia core
- #2577 Set the error URL of the payment form
- #2576 Add deprecated model event
- #2575 Remove symfony/icu
- #2574 A missing hook will throw an error in dev mode only
- #2573 BO UI Fix btn edit content
- #2571 Fix thelia migration 2.3.4 -> 2.4.0-alpha2
- #2570 Fix count null value php7.2
- #2569 Prepare version 2.4.0-alpha2
- #2568 Implementation symfony dotenv
- #2567 fix invalid exception
- #2566 Update composer file core
- #2565 Change Thelia dev ip protection
- #2564 Added call to parent method in model's event dispatching methods
- #2563 Removed all round() from the code
- #2561 Order status management improvement
- #2560 Lang should be active and currency visible to be used in front office
- #2558 BO UI renderer btn create
- #2557 BO UI Add possibility to remove btn text
- #2556 Update propel with event dispatcher
- #2555 The 'zip' extension is required to install modules
- #2553 PHP 7.2 forms buildForm() signature fix
- #2552 Minor back-office UI improvements
- #2551 Fix #2525 microdata
- #2547 BO new buttons integration
- #2546 Undefined loop should be in the loop stack
- #2545 Customer preferred language selection
- #2544 Fix constants propel deprecated
- #2543 Changed Travis CI config to use trusty distribution
- #2542 Smarty upgrade to version 3.1.33
- #2541 add remove_zero_decimal parameter
- #2540 Add gitignore .DS_Store
- #2538 Minor code style fixes
- #2537 test if the module exists on the file system before generation cache
- #2536 Propel generation path fix
- #2535 Fix not countable args
- #2534 Composer update dependencies, fix symfony/var-sumper required version and add polyfill php7.3
- #2533 Fix an infinite loop when the cache is cleared
- #2532 Add url sitemap.xml
- #2528 Patch for PRODUCT page
- #2524 wrong class name on Contact subject field
- #2522 Add email with mailto directly on order
- #2521 Add a new export "product I18n"
- #2519 Added ordering by PSE reference in PSE loop
- #2518 Fixed multiple times the same line in results
- #2517 fix/choice-render-multiple
- #2512 add missing formError use on 2.3 branch
- #2509 A "tinymce-editor-setup" event is sent when TinyMCE is ready
- #2507 Fix sale activation after sale update
- #2503 Added an explanatory message to disconnected exception
- #2502 Added category and brand ID in sidebar hooks
- #2501 PHP 7.1 compatibility fix in ExportHandler
- #2500 Fix newsletter unsubscribe/subscribe
- #2499 Using better headers to generate PDF response
- #2498 Fix for PHP 7.1 warning A non-numeric value encountered
- #2497 Remove unnecessary openssl extension install step
- #2495 Prevent setting the only default PSE to non-default
- #2494 Fixed Carousel module translations
- #2493 Keep product price & information when deleting the last PSE of a product
- #2492 Removed useless notice log
- #2489 fix order color issue in customer edit form
- #2487 Update composer.json of Thelia core

# 2.4.0-alpha2

- #2486 Add compatibility with php 7.2
- #2486 Update to Symfony 2.8.35
- #2486 Add Symfony VarDumper for dev environment
- #2486 Update to Propel Alpha 8, special thanks to @bcbrr
- #2486 Update to Html2Pdf 5.2
- #2483 Fix color status in search order
- #2482 Fix FreeOrder: round total amount to avoid problems with floats

# 2.4.0-alpha1

- (related to #2266) Fix #2226 : Bad parsing of web version in db update script
- (related to #2265) Fix #2225 : Wrong version displayed in db update script
- (related to #2264) Fix #2263 : check php extension "dom" is installed
- (related to #2261) Cast position_delegate virtual column to number in product loop
- (related to #2259) Coupon - fix cart contains products & cart contains categories conditions
- (related to #2257) Coupon - fix order coupon amount
- (related to #2256) Coupon - add available on special offers in remove x amount type
- (related to #2255) Moved php shebang in the right file
- (related to #2254) Add parameter "module_code" to "modules.table-row"
- (related to #2251) Remove a forgotten debug instruction
- (related to #2250) Fix hook register listener that throws unucessary exceptions
- (related to #2249) Fix identical queries in the productSaleElement loop and the Product loop
- (related to #2243) Fixed and optimized content and product loops
- (related to #2240) Fixes #2229 : bad resource code in MailingSystemController class
- (related to #2239) Fixes #2233 : customer profile update
- (related to #2238) New method to save order transaction ref
- (related to #2237) Fixed cancelPayment method in BasePaymentModuleController class
- (related to #2235) Add amount in order coupon loop parse results
- (related to #2232) Moved to container-based infrastructure for Travis CI
- (related to #2231) Fix #2215 : loop pagination cache
- (related to #2230) Hook fixes
- (related to #2227) Fix for two problems with CART_FINDITEM event processing
- (related to #2224) Added simple message processing to MailerFactory
- (related to #2222) Fix duplicates in country loop when used with "with_area" argument
- (related to #2221) Completed default email template FR and EN translations
- (related to #2220) French translations
- (related to #2219) Fix coupons issues
- (related to #2217) Protected/hidden modules
- (related to #2214) Fix for #2213 : Nesting loops with the same argument set is now working
- (related to #2208) Fix missing model on LoopResultRow
- (related to #2207) Add delimiter and enclosure for header insertion
- (related to #2206) Add reset array pointer if $data is an array.
- (related to #2205) Fixed sale edit form
- (related to #2204) Add isEmpty(), to check if $data is empty.
- (related to #2203) Check if $error exist, specific for submit type
- (related to #2202) Fix currency creation modal (The currency field is missing in the html template)
- (related to #2201) Deprecated class NotFountHttpException because typo and removed deprecated classes
- (related to #2198) Cancel coupon usage on order cancel
- (related to #2197) Pagination of coupon list
- (related to #2191) Update BO typo
- (related to #2190) Home stats improvements
- (related to #2189) Huge performance improvement in feature-availability loop
- (related to #2188) A more effective way to solve issue #2061
- (related to #2187) Merge versions 2.1.10 2.2.4 2.3.2 in master branch
- (related to #2181) Fix CSV export cached file size
- (related to #2178) Add a coupon type to offer a product
- (related to #2174) PSR-6 + thelia.cache service and smarty cache block
- (related to #2173) Fix customer discount apply on backoffice
- (related to #2171) Fix sql syntax in setup/update/tpl/2.4.0-alpha1.sql.tpl
- (related to #2170) Fix #2166 : array to string conversion when php setup/update.php
- (related to #2168) Router redirect to last rewriting_url
- (related to #2167) Add global variable `app` to Smarty
- (related to #2166) Fixed the update process when thelia.net is out of order
- (related to #2165) Add replyTo parameter in mailer factory
- (related to #2164) Add confirmation email option after customer creation
- (related to #2163) Update fr_FR.php
- (related to #2161) State Fixes
- (related to #2160) Added missing home.block 'class' parameter
- (related to #2157) Prevent an infinite loop in new product dialog
- (related to #2155) Injects versions 2.1.9 2.2.3 2.3.1 in the master branch
- (related to #2154) Test range dates exists before testing type
- (related to #2153) Lighten placeholders color
- (related to #2150) Fix form and validator translations
- (related to #2149) Fixed status_id parameter access
- (related to #2148) Added search by EAN code to product sale elements loop
- (related to #2147) Fixed help text display if show_label is false
- (related to #2146) Fix search in i18n fields when backend_context=1, and search improvements
- (related to #2145) Fix for taxes & tax rules description display in Taxes rules page.
- (related to #2144) Fix sql_mode
- (related to #2143) Change order_adress in account-order
- (related to #2142) Force utf8 on thelia update
- (related to #2139) Start page correction
- (related to #2135) Fix ressources check for translation view
- (related to #2133) Add ORDER_UPDATE_TRANSACTION_REF event
- (related to #2132) Fix change default category and default folder
- (related to #2129) Fix order export date interval
- (related to #2128) Fix checkout issues
- (related to #2127) Fix for 2.3.0 BC break.
- (related to #2125) fix construct in GenerateRewrittenUrlEvent
- (related to #2123) Init Version 2.4.0-alpha1
- (related to #2109) Module routers priority improvement (issue #2108)
- (related to #2107) Add create function for AlphaNumStringType argument
- (related to #2106) Added order-invoice form hooks
- (related to #2093) Fix #1662 add of hooks in pdf email and account-order
- (related to #2082) Fix issue #2003 : product and pse ref in invoice template
- (related to #2081) Order Status


# 2.3.3

- (related to #2243) Fixed and optimized content and product loops
- (related to #2240) Fix #2229 : bad resource code in MailingSystemController class
- (related to #2239) Fix #2233 : customer profile update
- (related to #2237) Fixed cancelPayment method in BasePaymentModuleController class
- (related to #2231) Fix #2215 : loop pagination cache
- (related to #2230) Hook fixes
- (related to #2222) Fix duplicates in country loop when used with "with_area" argument
- (related to #2219) Fix coupons issues
- (related to #2214) Fix for #2213 : Nesting loops with the same argument set is now working
- (related to #2207) Add delimiter and enclosure for header insertion
- (related to #2206) Add reset array pointer if $data is an array.
- (related to #2205) Fixed sale edit form
- (related to #2204) Add isEmpty(), to check if $data is empty.
- (related to #2203) Check if $error exist, specific for submit type
- (related to #2202) Fix currency creation modal (The currency field is missing in the html template)
- (related to #2191) Update BO typo

# 2.3.2

- (related to #2182) Fix compatibility with sql_mode STRICT_ALL_TABLES
- (related to #2181) Fix CSV export cached file size
- (related to #2173) Fix customer discount apply on backoffice. The custome permanentr discount is also applied on the back office if the user is logged in front office
- (related to #2168) Fix router redirect to last rewriting_url
- (related to #2166) Fixed the update process when thelia.net is out of order
- (related to #2160) Added missing home.block 'class' parameter
- (related to #2157) Prevent an infinite loop in new product dialog
- (related to #2154) Add Test range dates exists before testing type

# 2.3.1

- (related to #2150) Fix form and validator translations
- (related to #2147) Fixed help text display if show_label is false
- (related to #2145) Fix for taxes & tax rules description display in Taxes rules page
- (related to #2144) Fix automatic configuration for the sql_mode
- (related to #2142) Force utf8 on thelia update
- (related to #2139) Start page correction for the loops
- (related to #2135) Fix ressources check for translation view
- (related to #2132) Fix change default category and default folder. Since the pull request #2066, it's no longer possible to change the default category of a product or the default folder of a content.
- (related to #2129) Fix order export date interval
- (related to #2128) Fix address state check in delivery cost estimation and fix login error due to symfony update
- (related to #2127) Fix 2.3.0 major BC break in Thelia\Core\Event\Order\OrderPaymentEvent
- (related to #2125) Fix construct in GenerateRewrittenUrlEvent

# 2.3.0

- #2121 Fix possible Compile Error in delivery loop
- #2117 Fix Admin update, the password is no longer required for update of an admin
- #2118 Module TinyMCE, fix the path for the Java uploader
- #2120 Fix {count} in search context, {count} doesn't work when searching (since 2.3.0 alpha-1)
- #2116 Updated translations from Crowdin
- #2110 Added a way to set specific date/time format for lang, fixed date/time format for fr_FR

# 2.3.0-beta2

- #2030 Fix ziparchive not found, add a message to prevent that the zip extension was not found on the server
- #2104 Fixed update function issue in Colissimo module
- #2096 #2103 Fix currency change, an exception was thrown if the currency does not exist
- #2097 Fixed and improved cancel order processing
- #2095 Updated translations from Crowdin
- #2092 Fix Module TheliaSmarty, replace the request service by requestStack service
- #2091 Fixed NO_ENGINE_SUBSTITUTION setting for MariaDB
- #2090 Fix GenerateRewrittenUrlEvent, add getters and setters
- #2084 Check if customer exist in coupon builder

# 2.3.0-beta1

- #2062 Remove composer dependency leafo/lessphp
- #2060 Fix BC, TaxRule action introduces a compatibility break
- #2080 Fix missing function `addoutputfields` in the loops
- #2078 Fixed checkbox and radio automatic rendrering. The "checked" status of checkboxes and radios was not correctly managed by form-field-attributes-renderer.html
- #2079 BackOffice : UX improvements on tablets, the right menu was too broad
- #2067 Fix esi render. The sub-request was not a Thelia request
- #2066 Fix the problem of position if a product or content in several sections and folders
- #2073 Use template default fallback in View Listener. Module views was not properly processed when the active front template is not "default"
- #2068 Fix customer edit view ACL, replace `update` by `view` for edit a customer
- #2063 Fix, when deleting a product with a free text feature value, the free text feature value was not removed
- #2058 Fix bug when sending the attribute combination builder form if the user had not selected attribute
- #2056 Fix UX bug on product list in the frontOffice, the grid icon or the list icon do not lock
- #2040 Fix bug when change image position on the module config page. The trait `PositionManagementTrait` was missing in `ModuleImage`
- #2054 Fix the update process for the Collissimo module

# 2.3.0-alpha2

- #1985 Add delivery and payment events `MODULE_PAYMENT_IS_VALID`, `MODULE_PAYMENT_MANAGE_STOCK`, `MODULE_DELIVERY_GET_POSTAGE`
- #2045 Moves the backOffice statistics in the new module HookAdminHome
- #2044 Add possibility to change number by default of results per page for the product list, the order list and the customer list in the backOffice
- #2042 Avoid having too many results in the backOffice search page
- #2021 Fixes hooks `mini-cart`, `sale.top`, `sale.bottom`, `sale.main-top`, `sale.main-bottom`, `sale.content-top`, `sale.content-bottom`, `sale.stylesheet`, `sale.after-javascript-include`, `sale.javascript-initialization`, `account-order.invoice-address-bottom`, `account-order.delivery-address-bottom`
- #2041 Fix possible circular reference for category tree and folder tree
- #2039 Disable the output of the url by the loops on the BackOffice
- #2034 Add column position in attribute combination table
- #2028 Fixed translation regexp prefix for templates
- #2027 Confirmation email when subscribing to newsletter, and subscription cancel page
- #2017 Add constraint of unicity in create and update hook form
- #2012 Checking MySQL version to set sql_mode automatically, this fixed the compatibility with MySQL > 5.6 for modes `STRICT_TRANS_TABLES`, `NO_ENGINE_SUBSTITUTION`
- #2009 Display PSE ref in backOffice order edit for the product list
- #2001 Check PHP version before trying to do anything in install process
- #1999 Fix Folder breadcrumb, the parent url was not good if you edit a picture in a folder or a content
- #1998 Add not blank constraint on zipcode in address create form
- #1988 Fix hide module-install if auth are not right in the BackOffice
- #1907 Administrators should now have an email address. They may use login name or email to log in the back-office. They could now create a new a password if they forgot it. New minimum_admin_password_length and enable_lost_admin_password_recovery configuration variable.
- #1962 Fix exception when cloning a product if the i18n in specific locale does not exist
- #1933 #2006 #2016 #2033 Upgrade Symfony 2.3 to Symfony 2.8
- #1995 Added order search options, improved search page in the backOffice
- #1994 Allow coupon in first cart step
- #1993 Fix the default language isocode link in backOffice languages page
- #1992 Add method to find category path `Thelia/Model/CategoryQuery::getPathToCategory`
- #1977 Fixed translation domain in NewsletterController
- #1980 Update database schema to increase module version field to 25 chars.
- #1971 #1973 Adds an address email to the administrator profile and adds the password lost functionality for administrators
- #1970 Add `CartDuplicationEvent` which provide both original and duplicated cart to listeners
- #1967 Module Colissimo : Replace country title by isoalpha2 in export for expeditor
- #1964 Fixed cart not deleted after an order placed
- #1960 Add events `CART_ITEM_CREATE_BEFORE` and `CART_ITEM_UPDATE_BEFORE`
- #1959 Add the ability to format an address by country
- #1907 Administrator email management and features
    - adds an address email to the administrator profile
    - This address email can now be used to login just like the login name
    - An administrator could now recover a lost password, just like a regular customer
- #1958 Fix missing success_url on Brand SEO update
- #1956 Fix UX right class in brand products pagination in the frontOffice
- #1948 Allow to define custom delimiter and enclosure char for CSV serializer
- #1947 Added a way to get category/product from related content ID
- #1946 Fix l'inclusion automatique of the TaxType class only if extension == php
- #1939 Add `visible` and `visible_reverse` values in Product Loop order argument
- #1936 Fixed the module name vefication for command `module:position`
- #1931 Add a optional parameters CC and BCC in method `\Thelia\Mailer\MailerFactory::sendEmailMessage`
- #1929 Mod: BaseController useFallbackTemplate set to true by default
- #1928 Hook DI alert messages thrown as exceptions in dev. mode
- #1926 Fix redirection after coupon consume
- #1923 Re enabled functional tests for back office
- #1922 Colissimo Move the prices from a json to a config
- #1921 Modules 'configuration' and 'hook' buttons behavior fix
- #1920 Fixed coupons conditions label translation
- #1917 Fixed translations bug in user mode with view only missing translations activated
- #1916 Fix upload document. The document title is missing after upload
- #1914 The module list in the translation page is now ordered by module code instead of module title
- #1913 Conservation the emails after unsubscribe on newsletter
- #1911 Add 'admin_current_location' arg for 'main.in-top-menu-items' Hook
- #1908 A fix for "terms & conditions" bootbox height
- #1906 Fix coupon create form data
- #1904 Update tinyMCE
- #1903 Added missing generateErrorRedirect()
- #1895 Add a link to the contact page in the front footer and update bootstrap
- #1881 Display only the zones affected to Colissimo in the backOffice
- #1853 Coupon, add condition match for cart item include quantity
- #1815 #1963 #1984 #1989 #1997 #2013 #2019 Import/export complete rework

# 2.3.0-alpha1

- #1907 Administrators should now have an email address. They may use login name or email to log in the back-office. They could now create a new a password if they forgot it. New ```minimum_admin_password_length``` and ```enable_lost_admin_password_recovery``` configuration variable.
- #1902 Update Colissimo export, add link to order and to customer, add package weight
- #1801 Fixed cart duplication conditions at user login/logout
- #1892 Add a name verification when creating a module with a command
- #1891 Add primary key in ```coupon_customer_count``` and ```ignored_module_hook``` tables.
- #1701 This PR improves the Order::createOrder() so that the method could be used to duplicate an order by re-using the delivery and invoice addresses defined in the original order.
- #1823 Add states/provinces concept. The objective of this PR is to separate states/provinces of countries. For now, the concept of states/provinces was managed in country model which was not the best way.
- #1878 Add module code in the lists of the BackOffice for a better understanding.
- #1832 Language improvement. Add the possibility to disable a language. It's possible to disable the language only for the front.
- #1851 Add in the module Tinymce, the possibility to choose in which text areas the editor will be used.
- #1840 Add the possibility to generate an url with the arguments ```router``` and ```route_id``` in the smarty function ```url```. Documentation ```http://doc.thelia.net/en/documentation/templates/urls-and-paths.html```
- #1872 Add next/prev buttons for orders and customers. Modify the loops of brands, categories, folders and contents so that the queries to get the next and previous objects are sent only when it is needed.
- #1850 #1859 Add hooks for email template
- #1845 Add price including taxes in the combination creation pop-up in the BackOffice
- #1868 Allow to open order-edit.html template with a specific module tab
- #1861 Add links to the appropriate pages
- #1860 Change version of Symfony Yaml components
- #1843 Fix smarty form_collection_field, a performance problem was introduced after this PR: #1613 because ​the Form::createView() method create all form view on each call.
- #1856 Convert order.invoice_date to datetime column
- #1852 Add the possibility to disable the generation of url for the loops, adds argument ```return_url``` in loops, the default value for argument ```return_url``` is ```true```
- #1857 Fix of hookblack : order.tab
- #1792 Update module Carousel, change the location of saving of the images
- #1844 #1848 Added hooks in the right column part of the edtion form of brand, content, category folder and product templates :
    - ```brand.modification.form-right.top```, ```brand.modification.form-right.bottom```
    - ```category.modification.form-right.top```, ```category.modification.form-right.bottom```
    - ```content.modification.form-right.top```, ```content.modification.form-right.bottom```
    - ```folder.modification.form-right.top```, ```folder.modification.form-right.bottom```
    - ```product.modification.form-right.top```, ```product.modification.form-right.bottom```
- #1835 Add the product combination in PDF delivery
- #1788 Remove all the AdminIncludes from the core modules.
- #1841 Add the possibility to create a product combination with several same attribute inside (2 colors in one product sales elements).
- #1830 Fix attribute title in the modal "create a new combination"
- #1780 Currency improvements. Add the possibility to disable a currency. Add the possibility to change the position of the currency symbol. Resolve #1446
- #1825 Add message if thelia project is not installed
- #1714 #1839 #1833 Hook improvements
    - Add new syntax to hook on a hook. Documentation ```http://doc.thelia.net/en/documentation/modules/hooks/index.html```
    - Add command ```php Thelia hook```
- #1824 #1829 Fix the admin home stats, On page load, the month sent to Thelia was bad
- #1821 Fix the value for constant ```AdminForm::LANG_DEFAULT_BEHAVIOR```, Resolve ##1820
- #1818 Fix BackOffice menu, hook block to integrate main link if it's used
- #1816 Fix the total price of cart if the items have a quantity greater than one, Resolve #1772, add new methods ```getTotalRealTaxedPrice```, ```getTotalTaxedPrice```, ```getTotalTaxedPromoPrice``` in the model ```Thelia\Model\CartItem```
- #1783 Fix product price exports. Resolve #1078 #1610
- #1808 Add customer's company in order mails and PDF
- #1780 Adds the ability to disable a currency and change the position of the currency symbol
- #1806 Fix the event dispatched before decoding of the import, ```TheliaEvents::IMPORT_AFTER_DECODE``` to ```TheliaEvents::IMPORT_BEFORE_DECODE```
- #1799 Fixed the redirection to rewritten URL
- #1725 Added new attributes and some aliases to the {cart} substitution
    - A new `weight` attribute is added, to get the cart total weight.
    - A new `total_price_without_discount` attribute is added, to get the cart total amount without taxes, excluding discount.
    - The following aliases of existing attributes are added, to provide a better english syntax, or a more accurate name :
        - `product_count`, alias of `count_product`
        - `item_count`, alias of `count_item`
        - `total_price_with_discount` alias of `total_price`
        - `total_taxed_price_with_discount` alias of `total_taxed_price`
        - `contains_virtual_product` alias of `is_virtual`
        - `total_tax_amount` alias of `total_vat`
- #1802 After upload, The image file name is no longer the default image title
- #1805 Add a new parameter ```locale``` for the module_config smarty plugin
- #1796 Fix regression in OrderAddressEvent cell phone can not be required in the constructor
- #1787 Add loop Overriding, Documentation ```http://doc.thelia.net/en/documentation/loop/extend.html```
- #1785 Fix undesirable carts, persist only non empty carts
- #1790 Update the default PSE ref when the product ref is updated
- #1778 #1797 Add ```manual``` and ```manuel_reverse``` order in attributeCombination loop
- #1766 Add order by ```id``` and ```id_reverse``` in product_sale_element loop
- #1760 Set order status as paid when the FreeOrder module is used to "pay" an order
- #1751 Fix for undefined currency exchange rate, add error message in the currency configuration page when an exchange rate could not be found
- #1769 Increase API key size to 48
- #1771 Add argument ```customer_id``` for hook customer.edit-js
- #1753 Fix the rounding of prices in the order product loop
- #1768 Update composer.lock file, update of the dependency thelia/currency-converter to version 1.0.1
- #1752 Add addValues method in EnumListType
- #1746 Removes deprecated classes and methods for the version 2.3
- #1745 Fix output value IS_DEFAULT in the product_sale_elements loop
- #1754 Add homepage redirection on /admin/login if the admin is already authenticate. Before this change, there was a render
- #1765 Fix for prev/next queries in Category and Content loops, and add prev/next in Product and Folder loop
- #1759 Fix for parent attribute and new exclude_parent attribute of Category loop
- #1750 Add EQUAL to product loop filter by min or max
- #1727 Add template & stock inputs on product creation
- #1722 Replaced parameter "locale" with "lang" in generated URL
- #1732 Update sql constraint for table product_sale_elements_product_image and product_sale_elements_product_document
- #1730 Change layout to only cache assets/dist
- #1734 Fix critical performance issue on ProductController HydrateObjectForm
- #1733 Fix order attribute in BaseHook
- #1729 Fix all useless DIRECTORY_SEPARATOR
- #1726 Fix method setRangeDate variable
- #1718 Autocomplete combination generation form with default pse values
- #1699 Fix missing use for BirthdayType
- #1713 Add more options for content, folder and order in search results
- #1706 Fix form coupon not found in frontOffice order invoice
- #1700 Fix source priority in ```ParserContext::getForm```
- #1588 Add document tab in frontOffice product page
- #1668 Add height limit for the select fields in the Attributes and Features tab of the admin product edit page
- #1669 Add options ```exclude_status, status_code, exclude_status_code``` and output value ```STATUS_CODE``` in Order loop
- #1674 Add options ```free_text, exclude_free_text``` in FeatureValue loop
- #1725 Add `weight` and `total_price_without_discount` attributes to the `{cart}` substitution, and some aliases to provide a better english syntax, or a more accurate name to existing attributes : `product_count`, alias of `count_product`, `item_count`, alias of `count_item`, `total_price_with_discount` alias of `total_price`, `total_taxed_price_with_discount` alias of `total_taxed_price`, `contains_virtual_product` alias of `is_virtual`, `total_tax_amount` alias of `total_vat`


# 2.2.6

- (related to #2240) Fix #2229 : bad resource code in MailingSystemController class
- (related to #2237) Fix cancelPayment method in BasePaymentModuleController class
- (related to #2231) Fix #2215 : loop pagination cache
- (related to #2219) Fix coupons issues
- (related to #2214) Fix for #2213 : Nesting loops with the same argument set is now working
- (related to #2208) Fix missing model on LoopResultRow
- (related to #2205) Fixed sale edit form

# 2.2.5

- (related to #2188) A more effective way to solve issue #2061
- #2194 Fix change currency on 2.2.x

# 2.2.4

- (related to #2182) Fix compatibility with sql_mode STRICT_ALL_TABLES
- (related to #2173) Fix customer discount apply on backoffice. The custome permanentr discount is also applied on the back office if the user is logged in front office
- (related to #2168) Router redirect to last rewriting_url
- (related to #2160) Added missing home.block 'class' parameter

# 2.2.3

- (related to #2147) Fixed help text display if show_label is false
- (related to #2144) Fix automatic configuration for the sql_mode
- (related to #2142) Force utf8 on thelia update
- (related to #2139) Start page correction for the loops
- (related to #2135) Fix ressources check for translation view
- (related to #2125) Fix construct in GenerateRewrittenUrlEvent
- (related to #2118) Module TinyMCE, fix the path for the Java uploader
- (related to #2096) Fix currency change, an exception was thrown if the currency does not exist
- (related to #2090) Fix GenerateRewrittenUrlEvent, add getters and setters
- (related to #2084) Check if customer exist in coupon builder
- (related to #2080) Fix missing function `addoutputfields` in the loops
- (related to #2078) Fixed checkbox and radio automatic rendrering. The "checked" status of checkboxes and radios was not correctly managed by form-field-attributes-renderer.html
- (related to #2068) Use template default fallback in View Listener. Module views was not properly processed when the active front template is not "default"
- (related to #2068) Fix customer edit view ACL, replace `update` by `view` for edit a customer
- (related to #2058) Fix bug when sending the attribute combination builder form if the user had not selected attribute
- (related to #2052) Fix #2040 Missing trait PositionManagementTrait in ModuleImage
- (related to #2041) Fix possible circular reference for category tree and folder tree
- (related to #2017) Add constraint of unicity in create and update hook form
- (related to #2012) Checking MySQL version to set sql_mode automatically, this fixed the compatibility with MySQL > 5.6 for modes `STRICT_TRANS_TABLES`, `NO_ENGINE_SUBSTITUTION`
- (related to #2010) Improve product price edition tab
- (related to #2005) Use a wider version requirement on thelia/installer for setup/
- (related to #1999) Fix Folder breadcrumb, the parent url was not good if you edit a picture in a folder or a content
- (related to #1980) Update database schema to increase module version field to 25 chars.
- (related to #1967) Module Colissimo : Replace country title by isoalpha2 in export for expeditor
- (related to #1962) Fix exception when cloning a product if the i18n in specific locale does not exist
- (related to #1958) Fix missing success_url on Brand SEO update
- (related to #1956) Fix UX right class in brand products pagination in the frontOffice
- (related to #1946) Fix the automatic inclusion of the TaxType class only if extension == php
- (related to #1939) Add `visible` and `visible_reverse` values in Product Loop order argument
- (related to #1936) Fixed the module name verification for command `module:position`
- (related to #1928) Hook DI alert messages thrown as exceptions in dev. mode
- (related to #1921) Modules 'configuration' and 'hook' buttons behavior fix
- (related to #1920) Fixed coupons conditions label translation
- (related to #1917) Fixed translations bug in user mode with view only missing translations activated
- (related to #1914) The module list in the translation page is now ordered by module code instead of module title
- (related to #1908) A fix for "terms & conditions" bootbox height
- (related to #1906) Fix coupon create form data
- (related to #1799) Fixed the redirection to rewritten URL
- (related to #1797) Fix order manual and manual_reverse in AttributeCombination loop
- #1901 Update Colissimo export, add link to order and to customer, add package weight

# 2.2.2

- #1901 Update Colissimo export, add link to order and to customer, add package weight
- (related to #1857) Fix of hookblack : order.tab
- (related to #1843) Fix smarty form_collection_field, a performance problem was introduced after this PR: #1613 because ​the Form::createView() method create all form view on each call.
- (related to #1830) Fix attribute title in the modal "create a new combination"
- (related to #1825) Add message if thelia project is not installed
- (related to #1824 #1829) Fix the admin home stats, On page load, the month sent to Thelia was bad
- (related to #1821) Fix the value for constant AdminForm::LANG_DEFAULT_BEHAVIOR, Resolve ##1820
- (related to #1818) Fix menu hook block to integrate main link if it's used #1818
- (related to #1806) Fix the event dispatched before decoding of the import, TheliaEvents::IMPORT_AFTER_DECODE to TheliaEvents::IMPORT_BEFORE_DECODE
- (related to #1796) Fix regression in OrderAddressEvent cell phone can not be required in the constructor
- (related to #1790) Update the default PSE ref when the product ref is updated
- (related to #1783) Fix product price exports. Resolve #1078 #1610
- (related to #1771) Add argument customer_id for hook customer.edit-js
- (related to #1769) Increase API key size to 48
- (related to #1768) Update composer.lock file, update of the dependency thelia/currency-converter to version 1.0.1
- (related to #1760) Set order status as paid when the FreeOrder module is used to "pay" an order
- (related to #1753) Fix the rounding of prices in the order product loop
- (related to #1751) Fix for undefined currency exchange rate, add error message in the currency configuration page when an
- (related to #1750) Add EQUAL to product loop filter by min or max
- (related to #1747) Fixed success_url check for contact form
- (related to #1745) Fix output value IS_DEFAULT in the product_sale_elements loop

# 2.2.1

- (related to #1699) Fix missing use for BirthdayType
- (related to #1700) Fix form retrieving
- (related to #1706) Fix coupon form
- (related to #1713) Add more options for content, folder and order in search results
- (related to #1722) Replaced parameter "locale" with "lang" in URL generated
- (related to #1724) Fix customer update input ID and indentation
- (related to #1726) Fix method setRangeDate variable in ExportHandler
- (related to #1729) Fix all useless DIRECTORY_SEPARATOR
- (related to #1730) Change layout to only cache assets/dist
- (related to #1732) Update sql constraint for table product_sale_elements_product_image and product_sale_elements_product_document
- (related to #1733) Fix order attribute in BaseHook
- (related to #1734) Fix critical performance issue on ProductController HydrateObjectForm
- (related to #1727) Add template & stock inputs on product creation

# 2.2.0

- #1692 Fix amounts displayed on the PDF invoice when a postage with tax is used (fixes #1693 and #1694)
- #1692 Fix translations for HookNavigation module
- #1692 Update hooktest-template and hooktest-module to prevent thelia-installer conflicts
- #1692 Update French, German, Italian translations
- #1692 Add Turkish translation
- #1688 Fix the permission messages in Thelia installer
- #1686 Use createForm method for front forms ```thelia.coupon.code, thelia.order.delivery, thelia.order.payment```
- #1667 Fix #1666 Display an error when trying to delete a customer which has orders
- #1665 Fix form field type date in Smarty plugin form, checks if the field type is a BirthdayType for assign a smarty variable [years, month, days]
- #1659 Fix Administrator edit action in the BackOffice, it was impossible to edit an administrator

# 2.2.0-beta3

- #1653 Remove ```AdminIncludes``` folder in the module generation
- #1649 Add index in table rewriting_url
- #1644 Allow relative path use with Tlog
- #1640 Add docker and docker-compose configuration
- #1637 Fix admin API edit button
- #1635 Add unit tests for the routing files (admin, api, front)
- #1634 Remove leftover uncallable routes (admin)
- #1631 Remove duplicate route (admin)
- #1629 Fix errors reporting of admin hooks
- #1632 Fix pagination infinite URL ; redirect on page 1 when changing products per page limit to avoid having no product on the page
- #1616 Improve statistic on homepage, add datetimepicker and fix first order
- #1601 Add set error in TheliaFormValidator when form is not valid
- #1585 Add parameters in frontOffice hooks
- #1587 Fix redirect url for the folder image and folder document
- #1590 Fix Thelia request initialization
- #1593 Fix form serialization in session that contain uploaded files
- #1594 update symfony/validator version to 2.3.31
- #1598 composer.json update dependency fzaninotto/faker to stable version 1.5
- #1583 Add German translations
- #1615 New TheliaEvents::CART_FINDITEM event to improve cart management flexibility
- #1618 Configurable faker
- #1581 Fix the prices precision
    - Not round the prices without tax in back office
    - Change the type for the price columns in database. New type : decimal(16,6)

##DEPRECATED

- Deprecated AdminIncludes, it's better to use the hooks

# 2.2.0-beta2

- Add module image edition in backoffice
- The language change links should now use the locale instead of the language code, e.g. http://www.yourshop/some-page?lang=fr_FR instead if http://www.yourshop/some-page?lang=fr. Backward compatibility is provided.
- Order status added by modules have their CSS label color handled or have a default color
- New login page style
- New general style of backoffice
- New dashboard arrangement

# 2.2.0-beta1

- Fix currency create action to set the by_default field properly.
- Add missing column default_template_id in category_version table
- The product parameter of the feature_value loop is no longer mandatory
- The product parameter new $PRODUCT variable is deprecated. $PRODUCT_ID should be used instead.
- Fix smarty `format_date` function to use consistent format when `locale` attribute is used.
- A product and all it's dependencies can now be cloned
- Fix index form error information session cleaning
- Feature's free text values now handle i18n
- URLs now have no problem with accents or case
- Add order by ```weight``` and ```weight_reverse``` in  product sale elements loop
- Add the ability to remove arguments in loops.
- new back-office is enhanced with a group button actions and a new layout
- Added an optional 'ajax-view' parameter to card add form
- Add validation groups in form from parser context
- Feature value are not translatable
- Allow multiple authors in module.xml file. Fixed #1459
- Display the mini cart with a hook. Fixed #1233
- Add date range for order export
- Klik&Pay is no more a submodule

# 2.2.0-alpha2

- Add a front office way to make an address the default one
- New translation domain that allows to redefine translation strings globally or specifically to a domain. By the way, we can safely update Thelia, modules, templates without overwriting specific translations.
- Remove ```currency_rate_update_url``` in ```setup/insert.sql```
- Add Cellphone to order address
- Add AnyListTypeArgument for loop argument
- New command ```module:position```. This command can changes module position
- Fix session serialisation
- Create a template context
- Allow relative path for the file logger from THELIA_ROOT constant
- Form error information are stored in the user session
- Fix redirection with slash ended uri. Fix #1331
- Config ```images_library_path``` and ```documents_library_path``` are now used everywhere
- Messages dispatched before and after content creation
- Add link to open pdf directly in browser in BO order/update
- Added wysiwyg.js hook where it was missing.
- Fix hook attribute in pdf template. The hook was never called.
- Cellphone column Added in order_address table
- Default front office template revamped :
    - bower and grunt can be used (but not mandatory, you can still use assetic)
    - less than 4095 css selectors (IE9 compatibility)
    - bootstrap is now fully used
    - this template is documented in its readme
- Force locale in session when loading a rewriten url
- Thelia is now fully usable with HTTPS protocol
- Do not delete the default product_sale_elements when the template of a product change
- Added standard 'error_url' parameter, like 'success_url'
- controller type can be found in the request (#1238)
- new helper to get order weight
- update selected delivery address in order process when customer change it
- new hooks for delivery modules in backoffice and pdf to add extra information

# 2.2.0-alpha1

- Add module code ($CODE variable) into payment loop outputs
- Add the 'images-folder' tag into module.xml file to deploy the modules images
- Add the 'module:list' command, that shows the modules state
- Update Admin Logs to add the resource ID when available.
- Add render smarty function, that executes the controller given in the action parameter.
- Allow modules to use document and image loop with the ```query_namespace``` argument
- Enable image zoom in image loop before cropping to guarantee that the resulting image will match the required size, even if the original image is smaller. This feature is active only if the ```allow_zoom``` parameter is true.
- When in development mode, an exception is thrown when an error occurs when processing assets, thus helping to diagnose missing files, LESS syntax errors, and the like.
- Change default order for cart loop
- New module_config Smarty function: {module_config module="module-code" key="parameter-name}
- Do not register previous url on XmlHttpRequest
- Add ACL on documents and images tabs.
- Add confirmation modal on documents deletion
- Add shop language choice on install wizard
- Remove redundant * on product-edit
- Add parameter "page_param_name" for template admin pagination.html. if "page_param_name" is empty, then the name of the parameter is "page"
- Add "Refunded" order status
- Add environment specific config file loading in modules
- Add the possibility for customers to change their email, backoffice configuration variables customer_change_email
- Add confirmation email for customers, backoffice configuration variables customer_confirm_email
- Refactor ```Thelia\Controller\BaseController::createForm``` into a factory service ```Thelia\Core\Form\TheliaFormFactory```
- Refactor ```Thelia\Controller\BaseController::validateForm``` and ```Thelia\Controller\BaseController::getErrorMessages``` into a service ```Thelia\Core\Form\TheliaFormValidator```
- Add the `failsafe=[true|false]` parameter to the assets Smarty functions (stylesheets, images, javascripts).
- A country could belong to more than one shipping zone.
- Add the `exclude_area` parameter to the Country loop.
- The Country loop now returns a proper country ISO code, left-padded with zeros, e.g. '004' instead of '4'
- The Country::getAreaId() method is DEPRECATED.
- Add the `country` and `order` parameters to Area loop
- Add the `area` parameter to Module loop
- Improved Shipping zones management
- Add cache on the graph of the home page, possibility to disable cache or change ttl cache, with the configuration variable admin_cache_home_stats_ttl
- New feature: a default product template could be defined in categories. Products created in this category will get this default product template. If no default product template is defined in a given category, it will be searched in parent categories.
- New main navigation style and position
- jquery.ui.datepicker is now DEPRECATED and will be REMOVED in 2.3. Please use boostrap-datepicker
- Add ```thelia.logger``` service to prepare the transition with another logger.
- Add 62 new admin hook
- Add stacked current form into parser context. It allows to have nested forms while using the new way to write forms.
- Module information and documentation could be viewed directly from the module list
- Add the possibility to translate text in the sql files (insert.sql, update/sql/\*.sql). to generate sql files use command `php Thelia generate:sql`. Translation can be made in the back office, in the translation page.
- format_date smarty function now handle symfony form type ```date```, ```datetime``` and ```time``` view value.
- Allow BaseController::generateOrderPdf to generate a pdf without having the rights
- SHOW_HOOK now displays parameters
- Add fallback for email template for mails sent from a module. If the template file does not exist in the current email template, it will use the one that comes with the module.
- Add dispatch of console events
- Refactor VirtualProductDelivery module. The email sending is now triggered from a new event to gain more flexibility. Now, email messages use smarty file templates located in `templates/email/default`.
- Added capability to use translator in module functions `preActivation` and `postActivation`
- Add environment aware database connection
- new 'asset' Smarty function, to get the URL of an arbitrary file from template assets, such as a video or a font.
- Imagine package is updated to 0.6.2, which provides a better support for transparency.
- Default border color of images resized with resize_mode="border" is now transparent instead of opaque white.
- The TemplateHelper class is deprecated. You should now use the thelia.template_helper service. TemplateHelperInterface has been introduced, so that modules may implement alternate versions


# 2.1.11

- (related to #2240) Fix #2229 : bad resource code in MailingSystemController class
- (related to #2237) Fixed cancelPayment method in BasePaymentModuleController class
- (related to #2231) Fix #2215 : loop pagination cache
- (related to #2214) Fix for #2213 : Nesting loops with the same argument set is now working
- (related to #2205) Fixed sale edit form

# 2.1.10

- (related to #2182) Fix compatibility with sql_mode STRICT_ALL_TABLES
- (related to #2173) Fix customer discount apply on backoffice. The custome permanentr discount is also applied on the back office if the user is logged in front office

# 2.1.9

- (related to #2144) Fix automatic configuration for the sql_mode
- (related to #2139) Start page correction for the loops
- (related to #2135) Fix ressources check for translation view
- (related to #2125) fix construct in GenerateRewrittenUrlEvent
- (related to #1920) Fixed coupons conditions label translation
- (related to #1946) Fix TaxType class only if extension == php
- (related to #1958) Missing success_url on Brand SEO update
- (related to #1967) Replace country title by isoalpha2 in export for expeditor
- (related to #1999) Update FolderBreadcrumbTrait.php
- (related to #2005) Use a wider version requirement on thelia/installer for setup
- (related to #2091) Checking MySQL version to set sql_mode automatically
- (related to #2041) Fix possible circular reference for category tree and folder tree
- (related to #2058) Fix Bug on submit combination builder empty form
- (related to #2068) Fix customer edit access
- (related to #2073) Use template default fallback in View Listener

# 2.1.8

- Fix Colissimo module external-schema (related to #1838)
- Fix attribute title in the modal "create a new combination" (related to #1830)
- Add message if thelia project is not installed (related to #1825)
- Fix the event dispatched before decoding of the import, TheliaEvents::IMPORT_AFTER_DECODE to TheliaEvents::IMPORT_BEFORE_DECODE (related to #1806)
- Update the default PSE ref when the product ref is updated (related to #1790)
- Sanitize the get arguments for admin stats (related to #1782)
- Add argument customer_id for hook customer.edit-js (related #1771)
- Increase API key size to 48 (related #1769)
- Fix for undefined currency exchange rate, add error message in the currency configuration page when an exchange rate could not be found (related #1751)
- Fix the rounding of prices in the order product loop (related to #1753)
- Add EQUAL to product loop filter by min or max (related to #1750)
- Fix output value IS_DEFAULT in the product_sale_elements loop (related to #1745)

# 2.1.7

- Fix all useless DIRECTORY_SEPARATOR (related to #1729)
- Update sql constraint for table product_sale_elements_product_image and product_sale_elements_product_document (related to #1732)
- Fix order attribute in BaseHook (related to #1733)
- Fix critical performance issue on ProductController HydrateObjectForm (related to #1734)
- Replaced parameter "locale" with "lang" in URL generated (related to #1722)

# 2.1.6

- Fix amounts displayed on the PDF invoice when a postage with tax is used (fixes #1693 and #1694).
- Check virtualProducts of order before send mail ```mail_virtualproduct```
- Add 'step' to input type number to be able to create and edit weight slices price
- Fix pagination infinite URL ; redirect on page 1 when changing products per page limit to avoid having no product on the page
- Allow relative path use with Tlog
- Prevent obscure "[] this value cannot be null" messages.
- Prevent short research and keep research in input
- Fix meta return array
- Fix hook position
- Fix Protocol-relative URL for HTTPS
- Update Copyright
- Fix translations and standardize Import and Export texts
- Fix the prices precision

# 2.1.5

- Klik&Pay is no more a submodule
- default category's parent is now 0
- check specific role in security module instead of checking if a user is logged in
- add a customer page parameter for the order loop on the customer page
- keep break line in ACE editor

# 2.1.4

- Add ```export.top``` and ```export.bottom``` hooks
- Fix slash ended rewritten url redirection
- Remove ```currency_rate_update_url``` in ```setup/insert.sql```
- Allow relative path for the file logger from THELIA_ROOT
- Fixed product loop behavior when category_default is set
- Force locale in session when loading a rewriten url
- Add port parameter for installing thelia with cli tools
- Change default param of the isPaid function, true is the good default parameter.

# 2.1.3

- Add ```\Thelia\Model\OrderProduct::setCartItemId``` and ```\Thelia\Model\OrderProduct::getCartItemId``` to remove the typo with ```cartIemId```
- A notice is displayed when the product's template is changed
- Security fix on authentication
- Rename cookie related config variables. They were prefixed with "thelia_" on insert, but not in the code

## DEPRECATED

- ```\Thelia\Model\OrderProduct::setCartIemId``` Because of a typo
- ```\Thelia\Model\OrderProduct::getCartIemId``` Because of a typo too

# 2.1.2

- Add the possibility to delete a coupon from the backoffice.
- module list is now reversed. Delivery modules appear first, then payment and finally classic modules.
- display a loader when a module is uploaded
- Change product prices export and import format to be compatible, now using product_sale_elements id as key to identify PSE.
- Fix unused variable in ```Thelia\Controller\Api\CustomerController::getDeleteEvent```
- change default order for cart loop.
- Add missing static keyword for ```Thelia\Core\HttpFoundation\JsonResponse::createError```
- Do not register previous url on XmlHttpRequest
- Fix deploy image directory destination
- Fix redirect response if a AuthenticationException is catched
- The PaymentModule log default level is now INFO instead of ERROR
- Direct instantiations of Thelia forms is deprecated. BaseController::createForm() should be used instead.
- Prevent XSS injection in error.html template
- The hook method is now stored in the ignored_module_hook table
- Allow to hardlink TinyMCE rather than symlink
- Add bootstrap paths for thelia-project
- Enlarge order dropdown menu to prevent wrapping in some languages
- Fixed langugage when previewing e-mails

# 2.1.1

- Fix update process from Thelia 2.0.* to 2.1.*

# 2.1.0

- abilities to translate email and pdf templates in modules
- support of taxes for postage amount
- sales modify price on update only if the sale is currently active
- cart can be used without thelia cart cookie. Set cart.use_persistent_cookie to 0 in your config variable panel.
- hook contains more information like the id of the current object you are working on.
- fix module skeleton location


# 2.1.0-beta2

- config :
    - environment variable can be used in the database.yml file. See [https://github.com/thelia/thelia/pull/968](https://github.com/thelia/thelia/pull/968)
    - Allow other projects to override thelia directories constants by using composer "autoload"["file"] entries
- smarty:
    - Add the "current" argument on smarty "url" function that allows you to get the same page but with differant url parameters
- new method ```manageStockOnCreation``` in PaymentModuleInterface. If return false, the stock will be decreased on paid status instead of order creation.
- Thelia:
    - Split Thelia on multiple repositories to allow a better version management with composer. For creating a new project, see [https://github.com/thelia/thelia-project]
    - Extract all the default modules into other repositories
    - Field type :
        - added area_id, category_id, folder_id, content_id
        - thelia type support render_form_field
- loop `product_sale_elements` : added `ref` argument and implemented `SearchLoopInterface`
- Updated `hasVirtualProduct`  in `Order` model to not test the presence of filename, as modules could implement the process differently
- new method ```Thelia\Model\Module::getDeliveryModuleInstance()``` return the delivery module instance for the current record.
- 'freesans' is now the default font of PDF documents
- Anonymous cart is no longer duplicated on customer login


# 2.1.0-beta1

- Autoload : the autoloader can be cached with Apc or XCache. See new index.php file.
- Update : add missing API table creation
- The default Tlog level is now TLog::ERROR instead of Tlog::DEBUG
- Add error message pages instead of white pages. But you can disable them by setting 0 into the config variable "error_message.show".
- Front Office Template: new page to display the details of an order
- email can be previewed in the back office
- some smarty classes are still present in the core of thelia not to break backward compatibility. Those classes will be deleted in version 2.3 :
    * Thelia\Core\Template\Smarty\AbstractSmartyPlugin
    * Thelia\Core\Template\Smarty\SmartyPluginDescriptor
- the default address label is now translated
- fixed "strictly use the requested language"
- new config variable :
    * session_config.lifetime : Life time of the session cookie in the customer browser, in seconds
    * error_message.show : Show error message instead of a white page on a server error
    * error_message.page_name : Filename of the error page. Default : error.html
- All cs issues are fixed, Thelia is now fully PSR2 compliant
- Allow possibility to upload a module with github suffix (eg : paypal-master.zip)
- Added a fallback for template to use the default template. it's useful for modules that are used on a website that doesn't use the default template

# 2.1.0-alpha2

- Update Process :
    - update command has been removed and replaced by a php script and a web wizard. Read the UPDATE.md file
- Templating :
    - Smarty is now a dedicated Module and no more present in the core of Thelia
    - All the template logic works now with abstracted class or interface, so it is possible to create a new Module for
an other template engine
    - A new interface has been introduced, the ParserHelperInterface : its purpose is to parse a string and get all
parser's function and block with theirs arguments.
    - A new service has been introduced : thelia.parser.helper and it must be the implementation of ParserHelperInterface
    - If you want to create a new Template module, you must declare those services :
        - thelia.parser : the class that implements ParserInterface
        - thelia.parser.helper : the class that implements ParserHelperInterface
        - thelia.parser.asset.resolver : the class that implements AssetResolverInterface
- Routing :
    - new notation ```a:b:c``` => ```Foo:Bar:Baz``` will execute ```Foo\Controller\BarController::BazAction``` method
- Module :
    - New schema for modules
    - Module installation from back office
    - Dependency check to Thelia version and other modules during installation, activation, deactivation and deletion
- Smarty :
    - new plugin ```flash``` to support symfony flash message.
    - new plugin ```default_locale```. This function is used for forcing the usage of a specific locale in all your template. Useful for email and pdf. eg : ```{default_locale locale="en_US"}```
    - function ```intl``` has a new argument : ```locale```. If used, this locale will be used instead of session's locale
- Loop :
    - new method addOutputFields in order to add custom fields in an overridden loop
- Tests:
    - Move tests from ```core/lib/Thelia/Tests``` to ```tests/phpunit/Thelia/Tests```
    - Update PHPUnit from 4.1.3 to 4.1.6
- Symfony components:
    - Update from 2.3.* to 2.3.21
- REST API:
    - Implement the first version of the REST API. You can find the documentation [here](http://doc.thelia.net/en/documentation/api/authentication.html)
- Forms: New implementation of Symfony form component that now handles form types, form extensions and form type extensions
    - You can use the tags ```thelia.form.type```, ```thelia.form.extension``` and ```thelia.form.type_extension``` to declare yours
    - Implementation of many form types for thelia, see the namespace Thelia\Core\Form\Type

## DEPRECATED

- ```\Thelia\Core\HttpFoundation\Session\Session::getCart``` is deprecated. Use ```getSessionCart``` instead.
- ```\Thelia\Cart\CartTrait``` trait is deprecated. Use ```\Thelia\Core\HttpFoundation\Session\Session::getSessionCart``` for retrieving a valid cart.

#2.1.0-alpha1

- Added sale management feature
- Added `module_id` parameter to Area loop
- Added "Shipping configuration" button to the delivery module list, with a warning if no shipping zone is assigned to the module.
- Added the `show_label` parameter to the `render_form_field Smarty` function.
- Added the `exclude` parameter to `form_hidden_field` function.
- Added the `product` parameter to the `attribute_availability` loop.
- Added the `sale` parameter to the `product` loop.
- Added visible argument to image/document classes
- Added `new`, `promo` and `default` parameters to `product_sale_elements` loop
- Added `store_notification_emails`, which contains the recipients of shop notification (such as order placed)
- Added admin notification e-mail for order placed
- Improved other emails (specially text versions)
- Added ORDER_SEND_NOTIFICATION_EMAIL event
- class-loader component is removed, it was not used anymore.
- Updating stock when changing order : canceled status
- Added virtual products feature.
    - Added new delivery module for virtual products.
- Added meta data feature to associate core elements and various data.
- Added `allow_negative_stock` configuration variable to allow negative stock or not (default is no)
- Added the ModuleConfig table, to provide modules an easy way to store their configuration parameters, with I18n if required.
- Added the `module-config` loop
- Added getConfigValue() and setConfigValue() static helper methods to BaseModule to offer an easy way to get/set a module parameters
- Refactored the Cheque module, to use the new ModuleConfig, and send an email to the customer when its payment is received.
- Added the wysywig.js hook to official hooks, so that any page which needs a WYSYWIG editor will only have to put this hook in the JS section to get one.
- Refactored Tynimce module according to wysywig.js hook
- Moved cart and order flush in the Order action, triggered by the ORDER_CART_CLEAR event. Payment modules which redirects to a non-strandard route (e.g., not /order/placed/{order_id}) should fire this event.
- Refactored assets generation.
- `file` parameter of asset related smarty functions (`stylesheets`, `javascripts`, ìmages`, ...) should not contains ../
- Added remember me feature for customer sign in process

##DEPRECATED

Redirect methods are deprecated. You have now two ways for generating a redirect response :
- Throwing a Thelia\Core\HttpKernel\Exception\RedirectException with a given URL
- If you are in a controller, return an instance of \Symfony\Component\HttpFoundation\RedirectResponse
- Never ever send a response. Only the HttpKernel class is allowed to do that.

### Deprecated methods :

- Thelia\Controller\BaseController::redirect
- Thelia\Controller\BaseController::redirectSuccess
- Thelia\Controller\BaseController::redirectToRoute

# 2.0.12

- Sanitize the get arguments for admin stats (related to #1782)
- Add EQUAL to product loop filter by min or max (related to #1750)
- Fix output value IS_DEFAULT in the product_sale_elements loop (related to #1745)

# 2.0.11

- Fix critical performance issue on ProductController HydrateObjectForm (related to #1734)

# 2.0.10

- Add 'step' to input type number to be able to create and edit weight slices price
- Fix pagination infinite URL ; redirect on page 1 when changing products per page limit to avoid having no product on the page
- Allow relative path use with Tlog
- Prevent obscur "[] this value cannot be null" messages.
- Prevent short research and keep research in input
- Fix Protocol-relative URL for HTTPS
- Fix fatal error that occurs when store does not use the default order_configuration email

# 2.0.9

- Klik&Pay is no more a submodule

# 2.0.8

- Allow relative path from thelia root for the file logger (by default log/log-thelia.txt)
- Force rediction on admin login even when connected to the front

# 2.0.7

- Change TokenProvider behavior to be more flexible
- More secure csrf token
- Fix ```templates/backOffice/default/includes/inner-form-toolbar.html``` change currency destination
- Fix install bug if the admin password doesn't match

# 2.0.6

- Do not register previous url on XmlHttpRequest

# 2.0.5

- add new function to smarty ```set_previous_url```. The parameter ```ignore_current``` allows you to ignore the current url and it will not be store as a previous url
- 'freesans' is now the default font of PDF documents
- fix bug with cart foreign key constraint #926
- fix typo with '}' #999
- add missing 'admin.search' resource
- add default translation for '/ajax/mini-cart'
- fix product add to cart
- fix form firewall variable name
- add more module includes in order-edit.html
- do not allow failure anymore on travis php5.6

#2.0.4

- Updating stock when changing order : canceled status
- order table is versionnable now.
- product_sale_elements_id is added to order_product table.

#2.0.3

- Fix js syntax in order-delivery template
- price are now save without any round.
 /!\ Check in your templates if you are using format_money or format_number function. Don't display prices directly.
- change Argument type for ref parameter in Product loop
- Fix export template
- [Tinymce]fix invisible thumb in file manager

#2.0.3-beta2

- fix update process
- fix coupons trait
- update schema adding new constraints on foreign keys
- previous url is now saved in session. use ```{navigate to="previous"}``` in your template

#2.0.3-beta

- New coupon type: Free product if selected products are in the cart.
- New feature: Product Brands / Suppliers management
- New 'brand' loop and substitution. product, image and document loop have been updated.
- Images and document processing have been refactored.
- Added store description field for SEO
- Added code editor on textarea on email templates page
- Fixed issues on position tests
- Fixed issues on RSS feed links
- Update SwiftMailer
- Fix bugs on customer change password form and module "order by title"
- Add the ability to place a firewall on forms. To use this in a module, extend Thelia\Form\FirewallForm instead of BaseForm
- Add Exports and Imports management
- Default front office template:
     - Display enhancement
     - Optimization of the uses of Thelia loops to gain performances and consistency
     - Optimization for SEO : meta description fallback, title on category page, ...
     - new PSE layout in product page, attributes are separated
     - Support of 'check-available-stock' config variable
     - Terms and conditions agreement is now in the order process
- Default pdf template:
     - Added list of amount by tax rule
     - Display enhancement
     - Added legal information about the store
- Demo:
     - Support for brand
     - Added folders and contents data.

#2.0.2

- Coupon UI has been redesigned.
- New coupon types:
    - Constant discount on selected products
    - Constant discount on products of selected categories
    - Percentage discount on selected products
    - Percentage discount on products of selected categories
- New coupon conditions :
    - Start date
    - Billing country
    - Shipping country
    - Cart contains product
    - Cart contains product from category
    - For specific customers
- Free shipping can now be restricted to some countries and/or shipping methods
- session initialization use now event dispatcher :
    - name event : thelia_kernel.session (see Thelia\Core\TheliakernelEvents::SESSION
    - class event : Thelia\Core\Event\SessionEvent
    - example : Thelia\Core\EventListener\SessionListener
- Creation of Thelia\Core\TheliakernelEvents class for referencing kernel event
- Add new command line that refresh modules list `Thelia module:refresh`
- Coupon internals have been simplified and improved.
- Error messages are displayed in install process
- Add pagination on catalog page in Back-Office
- Add Hong Kong to country list
- Fixed issue #452 when installing Thelia on database with special characters
- implement search on content, folder and category loop.
- all form are documented
- template exists for managing google sitemap : sitemap.html

#2.0.1

- possibility to apply a permanent discount on a customer
- display estimated shipping on cart page
- export newsletter subscribers list
- Fix redirect issues
- enhancement of coupon UI
- enhancement of admin menu. Coupon is now in Tools menu
- front office, email and pdf templates are translated in Russian and Czech
- fix bugs : https://github.com/thelia/thelia/issues?milestone=4&page=1&state=closed

#2.0.0

- Coupons values are re-evaluated when a product quantity is changed in the shopping cart
- You can declare new compilerPass in modules. See Thelia\Module\BaseModule::getCompilers phpDoc
- Add ability to load assets from another template. See https://gist.github.com/lunika/9365180
- allow possibility to use Dependency Injection compiler in Thelia modules
- Add Deactivate Module Command Line
- Add indexes to  database to improve performance
- Order and customer references are more human readable than before
- Refactor intl process. A domain is created for each templates and modules :
    - core => for thelia core translations
    - bo.template_name (eg : bo.default) => for each backoffice template
    - fo.template_name (eg : fo.default) => for each frontoffice template
    - pdf.template_name (eg : pdf.default) => for each pdf template
    - email.template_name (eg : email.default) => for each email template
    - modules :
        - module_code (eg : paypal) => fore module core translations
        - module_code.ai (eg : paypal.ai) => used in AdminIncludes templates
        - bo.module_code.template_name (eg : bo.paypal.default) => used in back office template
        - fo.module_code.template_name (eg : fo.paypal.default) => used in front office template
- new parameter for smarty ```intl``` function. The parameter ```d``` allow you to specify the translation domain (as explain before). This parameter is optional
- the ```d``` can be omitted if you use ```{default_translation_domain domain='bo.default'}``` in your layout. If you use this smarty function, the ```d``` parameter is automatically set with the domain specify in ```default_translation_domain``` function
- We changed Thelia's license. Thelia is published  under the LGPL 3.0+ License


#2.0.0-RC1

- Remove container from BaseAction.
- fix sending mail on order creation
- less files in default templates are already compiled in css.
- all validator message are translated
- type argument is now a default argument and used for generating loop cache
- fix total amount without discount in backoffice. Fix #235
- description is not required anymore in coupon form. Fix #233
- Do not allow to cumulate the same coupon many times. Fix #217
- colissimo module is now fully configurable
- test suite are executed on PHP 5.4, 5.5, 5.6 and HHVM. Thelia is not fully compatible with HHVM
- add new attributes to loop pager (http://doc.thelia.net/en/documentation/loop/index.html#page-loop)
- we created a new github repo dedicated for modules : https://github.com/thelia-modules

#2.0.0-beta4

- Tinymce is now a dedicated module. You need to activate it.
- Fix PDF creation. Bug #180
- Fix many translation issues.
- The TaxManager is now a service
- Loop output is now put in cache for better performance
- loop count is refactored. It used now count propel method instead of classic loop method
- UTF-8 is used during install process, no more encoding problem in database now
- an admin can now choose a prefered locale and switch language in admin panel
- module repository is available on github : https://github.com/thelia-modules
- import module from Thelia 1 is available. It works from Thelia 1.4.2 : https://github.com/thelia-modules/importT1

#2.0.0-beta3

- Coupon effect inputs are now more customisable (input text, select, ajax, etc.. are usable) and unlimited amount of input for coupon effect are now possible too
- when a category is deleted, all subcategories are deleted
- delete products when categories are removed. Works only when the category is the default one for this product
- Manager update exists now. Run ```php Thelia thelia:update```
- Coupon works now
- Improved tax rule configuration

#2.0.0-beta2

- http://doc.thelia.net is available in beta.
- Increase performance in prod mode.
- Front part (routes and controller) are now a dedicated module.
- allow to create a customer in admin panel
- translation is implemented :
	- I18n directory in template or module.
	- multiple extensions are available. We choose to use php but you can use other.
	- You can translate your template or module from the admin.
- Admin hooks exist. With this hooks, a module can insert code in admin pages
- Admin hooks can be display using SHOW_INCLUDE=1 in your query string and in dev mode (http://doc.thelia.net/en/documentation/modules/hook.html)
- change memory_limit parameter in installation process. 128M is now needed
- assets can be used from template directory and from module
- Product, Category, Folder and Content have a dedicated SEO panel
- Allow to configure store information like email, address, phone number, etc.
- email management : http://doc.thelia.net/en/documentation/templates/emails.html
- "How to contribute ?" see http://doc.thelia.net/en/documentation/contribute.html
-Cache http (use it carefully, default template is not compatible with this cache) :
	- if you don't know http specification, learn it first http://www.w3.org/Protocols/rfc2616/rfc2616.html
	- esi tag integrated, use {render_esi path="http://your-taget.tld/resource"}
	- if no reverse proxy detected, html is render instead of esi tag
	- if you can't install a reverse proxy like varnish, use the HttpCache (just uncomment line 14 in web/index.php file)
	- resources :
		- http://www.mnot.net/cache_docs/ (fr)
		- http://tomayko.com/writings/things-caches-do (en)
		- http://symfony.com/doc/current/book/http_cache.html#http-cache-introduction (en and fr)
