#2.1.0-alpha2
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

- Module :
    - New schema for modules
    - Module installation from back office
    - Dependency check to Thelia version and other modules during installation, activation, deactivation and deletion
    - update command has been removed and replaced by a php script and a web wizard.
- Smarty :
    - new plugin `flash` to support symfony flash message.
- Loop : new method addOutputFields in order to add custom fields in an overridden loop
- Tests:
    - Move tests from ```core/lib/Thelia/Tests``` to ```tests/phpunit/Thelia/Tests```
    - Update PHPUnit from 4.1.3 to 4.1.6
- Symfony components:
    - Update from 2.3.* to 2.3.21


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
