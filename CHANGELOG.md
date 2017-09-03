# 2.3.4

**WARNING** : Minimum PHP version for Thelia 2.3.4 is now **PHP 5.5**. 
Do not upgrade if you're server is currently using a lower PHP version

- (related to #2422) Installation with PHP 7.1 is now working properly. The mcrypt dependency is now replaced by openssl
- (related to #2413) Requesting the view of a not visible product, category, contents, folder or brand now causes an HTTP 404 error instead of displaying the view 
- (related to #2416) Favicon, store logo and e-mail banner are now managed from the back-office -> Configuration -> Store
- (related to #2420) The cheque instructions defined in the back-office were displayed only for en_US locales. This PR fixes this problem, the instructions are now displayed for every locale.
- (related to #2391) When creating a customer from the back offcie, the state field is now present.
- (related to #2384) Fix tinymce_lang guess helper
- (related to #2377) Added missing translation in OrderPayment.php
- (related to #2351) Changed ModelCriteriaTools to be less restrictive in checking column. The old code only checked if there was a translation available (a row present) and not the content of a column that could be NULL.
- (related to #2335) Fix behavior of translate page in back office : "custom_fallback" and "global_fallback" were ignored when "view_missing_traductions_only" was on
- (related to #2301) "view" authorization is now checked for Thelia feed list and last version number on the back-offcie home page
- (related to #2286) Fix AbstractExport array rewind bug
- (related to #2278) Fix i18n typo : Amñinistración => Administración
- (related to #2275) Fixed select option rendereing in render_form_field.html
- (related to #2273) Fixed number input in coupon percentage html5 fragment
- (related to #2262) success_url is now cosidered when deleting a cart_item
- (related to #2026) regexp significant characters are now escaped whe n processing URL parameters
- (related to #1949) $CONTENT_COUNT in folder loop now returns the number of visible contents, and not the number of all (including not visible) contents.
- (related to #1877) Update shipping-configuration-edit.html to escape markup in shipping zone name
- (related to #1876) Update form.html to escape coupon code HtML
- (related to #1875) Update coupon-update.html to escape coupon code HtML
- (related to #1822) To prevent token errors when maniplating cart items, the cart token is no longer refreshed in the delete token code
- (related to #1743) Back-Office template sale edit
- (related to #1717) The stock of a virtual product is now checked
- (related to #2414) In the cart, the first PSE image if nos displayed, if there is one. The product image is displayed otherwise. 
- (related to #2408) Code style fixes and translations improvements
- (related to #2408) <br> cound now be used in text email templates to force a line break
- (related to #2272) Form fields error information are now saved in the global form error context, and remain available after a redirection.
- (related to #2276) Product loop fix: addOutputFields() method may now to manipulate fields that were set in associateValues()
- (related to #2293) performance improvement for calculating order total. This calculation uses now only one request instead of (1 + number of products).
- (related to #2345) fix and improvement of counting sub categories and sub folders. The number of request is reduced, and the visibility status of products or contents is now considered. Category and subcategory products are now deleted by dispatching a TheliaEvents::PRODUCT_DELETE instead of calling Product::delete()
- (related to #2349) New feature: It is now possible to send a test email message from the Preview tab of mailing template modification page
- (related to #2380) Fix for wrong product reference on cart and order-invoice pages
- (related to #2402) "Coupon" substitutions are now available in the templates
- (related to #2400) Back-office HTML typo
- (related to #2394) The customer state is now used to calculate cart taxes
- (related to #2357) When the resize mode is "none", and required image is bigger than original image in both dimensions, the image is now zoomed if allow_zoom is true.
- (related to #2403) Fix for wrong or missing types in ProductCloneEvent
- (related to #2363) Improved products templates features. 1) When a product's template is modified, only the obsolete attribute combinations are deleted, this is much better than deleting all attribute combinations. 2) When an attribute is removed from a template, all related products are also updated, and obsolete attribute combinations are removed. 3) When a feature is removed from a template, the related products are updated, and the feature is removed from the product. 4) The template management page now displays the related products and categories, making finding which products or categories uses the template easier. 5) It is now possible to duplicate a product template. 6) Product and Category loops have now a template_id parameter, to filter results on one ore more template IDs. 
- (related to #2369) PHP7 related fix: $return was declared as string, and used as array
- (related to #2372) When an URL for a disabled language is invoked, the page in the disabled locale was displayed. The user is now redirected to the default language URL.
- (related to #2396) Fix for domain-based language selection
- (related to #2395) In RemoveXPercent coupon type, the discount is no longer rounded to 2 decimals.
- (related to #2389) `Thelia export --list-archiver` will not return an error if one of the supported archiver extensions is not installed
- (related to #2379) When creating a product, the default tax rule is used if none is defined in the ProductCreateEvent event. 
- (related to #2378) When used in the back-office context (backend_context=1), the feature loop will return all features
- (related to #2296) &lt;option&gt; label margin using CSS is not working in IE and Safari, making category tree selection hard to use. &amp;nbsp; is now used, via the `{option_offset l=n label='some label'}` Smarty custon function  
- (related to #2375) Force an update of Cart model after deleting an item, so that the listeners will not notice the change
- (related to #2374) Image position field is now available in Carousel module configuration
- (related to #2365) en_US translation improvements
- (related to #2364) On the contact page, the map is now fetched using https
- (related to #2356) Parameter `product_count_visible_only` added to `category` loop
- (related to #2283) Typo which prevents to save the custom-css.less file in TinyMCE module
- (related to #2338) All exceptions are now logged in the Thelia log file
- (related to #2348) fix customer delete in back-offcie search view
- (related to #2339) Fix for #2337 to always get a correct LOOP_TOTAL 
- (related to #2334) Fix "Class not found" in AbstractArchiver
- (related to #2327) Add cart success_url, which is missing for mobile
- (no related PR) Thelia version is now fetched using https insteade of http 
- (related to #2326) Fix the "visible" argument of folder-path and category-path loops, which was not working after the first iteration. The code for the "depth" element was missing in these loops, and is now written.
- (related to #2325) Cart discount is now updated after login
- (related to #2323) Fix possible html break if the database contains a product that has multiple product sale element by default.
- (related to #2277) Module information is now displaying properly module information of 2.2 module.xml files, and specially authors.
- (related to #2309) Added missing hooks in back-office search results tables head and body
- (related to #2320) Fix the sorting feature in categories and folders back-office views: some sorting criteria, such as visible or ID where not implemented in the loops.
- (related to #2310) Fix for of array based loops extensions, to allow listeners to change array content.
- (related to #2312) Added order ID to 'customer.orders-table-row' hook parameters
- (related to #2313) The manual order is now working properly in the content loop.
- (related to #2308) Use service in ContainerAwareCommand to get URL instance. It will prevent further calls to this service to override request context.
- (related to #2291) Fix currency change to default where no currency in request. Change to default only if requested currency not exists.
- (related to #2287) Fix for not working decimals=0 in {format_number} or {format_money} 
- (related to #2306) fr_FR translation imrpovement
- (related to #2303) en_US translation imrpovement
- (related to #2300) Back-office HTML typo
- (related to #2299) Back-office HTML tag typo for displaying current lang flag
- (related to #2295) Fix method initRequest on the ContainerAwareCommand
- (related to #2281) Fixed PECL extentions install script in docker setup
- (related to #2271) Invisible (off-line) categories are now available in coupon conditions and types
- (related to #2264) Check if php extension "dom" is installed (issue #2263)
- (related to #2265) Fix #2225 wrong version displayed in db update script (issue #2225) 
- (related to #2266) Bad parsing of web version in DB update script (issue #2226)
- (related to #2259) Fixed "cart contains products" & "cart contains categories" coupon conditions
- (related to #2261) The manual order is now working properly in the product loop.
- (related to #2257) Fix for wrong order coupon amount  
- (related to #2256) Check if a RemoveXAmount type coupon is valid for special offers 
- (related to #2255) Moved php shebang in the right file
- (related to #2254) add parameter "module_code" to "modules.table-row" hook
- (related to #2238) New method BasePaymentModuleController::saveTransactionRef() to save order transaction reference
- (related to #2232) Moved to container-based infrastructure for Travis CI
- (related to #2235) Add `DISCOUNT_AMOUNT` variable to `order_coupon` loop
- (related to #2227) Fix for two problems with CART_FINDITEM event processing: 1) The CartEvent dispatched with CART_FINDITEM was the one received by Thelia\Action\Cart::addItem(), thus stopping event propagation will stop the whole cart add process. 2) Thelia\Action\Cart::findCartItem() was not aware of a cart item set in the event by event listeners with a higher priority, thus this cart item is always overwritten.
- (related to #2221) Completed default email template FR and EN translations
- (related to #2224) Added simple messages (no Message object required) processing to the MailerFactory, throug the sendSimpleEmailMessage() and createSimpleEmailMessage() methods. A new getMessageInstance() method has been added, to prevent direct usage of \Swift_Message::newInstance()
- (related to #2217) New feature: Protected and hidden modules
- (related to #2197) Pagination of coupon list
- (related to #2198) Cancel coupon usage on order cancel
- (related to #2190) Admin Home page statistics improvements
- (related to #2189) Performance improvement in feature-availability loop
- (related to #2188) A more effective way to solve issue free text feature problem (see #2061)
- (related to #2174) New PSR-6 implementation + thelia.cache service and smarty cache block
- (related to #2167) Add global variable `app` to Smarty to be consistent with Twig
- (related to #2165) Add replyTo parameter in mailer factory
- (related to #2081) Order Status improvements
- (related to #2164) New confirmation email option after customer creation 
- (related to #2153) Lighten placeholders color to be more different than filled inputs
- (related to #2149) Fixed status_id parameter access
- (related to #2148) Added search by EAN code to product sale elements loop
- (related to #2146) Fix search in i18n fields when backend_context=1, and search improvements. The loop search system is imporved, by providing the StandardI18nFieldsSearchTrait, which manages searches in the standard internationalized fields (title, chapo, description and postscriptum). In the back-office, customer search is now in sentence (%term%) mode, to allow incomplete word matches, such as part of an email address.
- (related to #2140) Added missing "calendar" PHP extension to docker config
- (related to #2107) Add create function for AlphaNumStringType argument
- (related to #2082) Product and PSE references added to invoice template
- (related to #2093) Fix #1662 add of hooks in pdf email and account-order
- (related to #2106) Added order-invoice form hooks
- (related to #2109) Module routers priority improvement
- (related to #1912) Fix for feature loop with product filter

# 2.3.3

- (related to #2249) Fix identical queries in the productSaleElement loop and the Product loop
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
