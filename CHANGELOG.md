#2.0.0-beta4
- Tinymce is now a dedicated module. You need to activate it.
- Fix PDF creation. Bug #180
- Fix many translation issues.

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