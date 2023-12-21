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
