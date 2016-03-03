<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Hook;

use Thelia\Core\Template\ParserHelperInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

/**
 * Class HookHelper
 * @package Thelia\Core\Hook
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookHelper
{
    /** @var array messages used to build title for hooks */
    protected $messages = array();

    /**
     * @var ParserHelperInterface
     */
    protected $parserHelper;

    public function __construct(ParserHelperInterface $parserHelper)
    {
        $this->parserHelper = $parserHelper;
    }

    public function parseActiveTemplate($templateType = TemplateDefinition::FRONT_OFFICE)
    {
        switch ($templateType) {
            case TemplateDefinition::FRONT_OFFICE:
                $tplVar = 'active-front-template';
                break;
            case TemplateDefinition::BACK_OFFICE:
                $tplVar = 'active-admin-template';
                break;
            case TemplateDefinition::PDF:
                $tplVar = 'active-pdf-template';
                break;
            case TemplateDefinition::EMAIL:
                $tplVar = 'active-mail-template';
                break;
        }

        return $this->parseTemplate($templateType, ConfigQuery::read($tplVar, 'default'));
    }

    public function parseTemplate($templateType, $template)
    {
        $templateDefinition = new TemplateDefinition($template, $templateType);

        $hooks = array();
        $this->walkDir($templateDefinition->getAbsolutePath(), $hooks);

        // load language message
        $locale = Lang::getDefaultLanguage()->getLocale();
        $this->loadTrans($templateType, $locale);

        $ret = array();
        foreach ($hooks as $hook) {
            try {
                $ret[] = $this->prepareHook($hook);
            } catch (\UnexpectedValueException $ex) {
                Tlog::getInstance()->warning($ex->getMessage());
            }
        }

        return $ret;
    }

    /**
     * Recursively examine files in a directory tree, and extract translatable strings.
     *
     * Returns an array of translatable strings, each item having with the following structure:
     * 'files' an array of file names in which the string appears,
     * 'text' the translatable text
     * 'translation' => the text translation, or an empty string if none available.
     * 'dollar'  => true if the translatable text contains a $
     *
     * @param string $directory the path to the directory to examine
     * @param        $hooks
     *
     * @internal param string $walkMode type of file scanning: WALK_MODE_PHP or WALK_MODE_TEMPLATE
     * @internal param \Thelia\Core\Translation\Translator $translator the current translator
     * @internal param string $currentLocale the current locale
     * @internal param string $domain the translation domain (fontoffice, backoffice, module, etc...)
     * @internal param array $strings the list of strings
     * @return number the total number of translatable texts
     */
    public function walkDir($directory, &$hooks)
    {
        $allowed_exts = array('html', 'tpl', 'xml', 'txt');

        try {
            /** @var \DirectoryIterator $fileInfo */
            foreach (new \DirectoryIterator($directory) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                if ($fileInfo->isDir()) {
                    $this->walkDir($fileInfo->getPathName(), $hooks);
                }

                if ($fileInfo->isFile()) {
                    $ext = $fileInfo->getExtension();

                    if (in_array($ext, $allowed_exts)) {
                        if ($content = file_get_contents($fileInfo->getPathName())) {
                            foreach ($this->parserHelper->getFunctionsDefinition($content, array("hook", "hookblock")) as $hook) {
                                $hook['file'] = $fileInfo->getFilename();
                                $hooks[] = $hook;
                            }
                        }
                    }
                }
            }
        } catch (\UnexpectedValueException $ex) {
            // Directory does not exists => ignore/
        }
    }

    protected function prepareHook($hook)
    {
        $ret = array();
        if (!array_key_exists("attributes", $hook)) {
            throw new \UnexpectedValueException("The hook should have attributes.");
        }

        $attributes = $hook['attributes'];

        if (array_key_exists("name", $attributes)) {
            $ret['block'] = ($hook['name'] !== 'hook');

            $ret['code'] = $attributes['name'];
            $params      = explode(".", $attributes['name']);

            if (count($params) != 2) {
                // the hook does not respect the convention
                if (false === strpos($attributes['name'], "$")) {
                    $ret['context'] = $attributes['name'];
                    $ret['type']    = '';
                } else {
                    throw new \UnexpectedValueException("skipping hook as name contains variable : " . $attributes['name']);
                }
            } else {
                $ret['context'] = $params[0];
                $ret['type']    = $params[1];
            }
            unset($attributes['name']);

            $ret['module'] = false;
            if (array_key_exists("module", $attributes)) {
                $ret['module'] = true;
                unset($attributes['module']);
            }

            // vars
            if ($ret['block'] && array_key_exists("vars", $attributes)) {
                unset($attributes['vars']);
                $ret['vars'] = explode(",", $attributes['vars']);
            }

            // get a title
            $contextTitle = $this->trans("context", $ret['context']) ? : $ret['context'];
            $typeTitle    = $this->trans("type", $ret['type']) ? : $ret['type'];
            $ret['title'] = sprintf("%s - %s", $contextTitle, $typeTitle);
            $ret['file'] = $hook['file'];
            $ret['attributes'] = $attributes;
        } else {
            throw new \UnexpectedValueException("The hook should have a name attribute.");
        }

        return $ret;
    }

    protected function normalizePath($path)
    {
        $path = str_replace(
            str_replace('\\', '/', THELIA_ROOT),
            '',
            str_replace('\\', '/', realpath($path))
        );

        return ltrim($path, '/');
    }

    protected function trans($context, $key)
    {
        $message = "";

        if (array_key_exists($context, $this->messages)) {
            if (array_key_exists($key, $this->messages[$context])) {
                $message = $this->messages[$context][$key];
            }
        }

        return $message;
    }

    protected function loadTrans($templateType, $locale)
    {
        switch ($templateType) {
            case TemplateDefinition::FRONT_OFFICE:
                $this->loadFrontOfficeTrans($locale);
                break;
            case TemplateDefinition::BACK_OFFICE:
                $this->loadBackOfficeTrans($locale);
                break;
            case TemplateDefinition::PDF:
                $this->loadPdfOfficeTrans($locale);
                break;
            case TemplateDefinition::EMAIL:
                $this->loadEmailTrans($locale);
                break;
        }
    }

    protected function loadFrontOfficeTrans($locale)
    {
        $t = Translator::getInstance();

        $this->messages["context"]["404"]                   = $t->trans("Page 404", array(), "core", $locale);
        $this->messages["context"]["account"]               = $t->trans("customer account", array(), "core", $locale);
        $this->messages["context"]["account-password"]      = $t->trans("Change password", array(), "core", $locale);
        $this->messages["context"]["account-update"]        = $t->trans("Update customer account", array(), "core", $locale);
        $this->messages["context"]["address-create"]        = $t->trans("Address creation", array(), "core", $locale);
        $this->messages["context"]["address-update"]        = $t->trans("Address update", array(), "core", $locale);
        $this->messages["context"]["badresponseorder"]      = $t->trans("Payment failed", array(), "core", $locale);
        $this->messages["context"]["cart"]                  = $t->trans("Cart", array(), "core", $locale);
        $this->messages["context"]["category"]              = $t->trans("Category page", array(), "core", $locale);
        $this->messages["context"]["contact"]               = $t->trans("Contact page", array(), "core", $locale);
        $this->messages["context"]["content"]               = $t->trans("Content page", array(), "core", $locale);
        $this->messages["context"]["currency"]              = $t->trans("Curency selection page", array(), "core", $locale);
        $this->messages["context"]["folder"]                = $t->trans("Folder page", array(), "core", $locale);
        $this->messages["context"]["home"]                  = $t->trans("Home page", array(), "core", $locale);
        $this->messages["context"]["language"]              = $t->trans("language selection page", array(), "core", $locale);
        $this->messages["context"]["login"]                 = $t->trans("Login page", array(), "core", $locale);
        $this->messages["context"]["main"]                  = $t->trans("HTML layout", array(), "core", $locale);
        $this->messages["context"]["newsletter"]            = $t->trans("Newsletter page", array(), "core", $locale);
        $this->messages["context"]["order-delivery"]        = $t->trans("Delivery choice", array(), "core", $locale);
        $this->messages["context"]["order-failed"]          = $t->trans("Order failed", array(), "core", $locale);
        $this->messages["context"]["order-invoice"]         = $t->trans("Invoice choice", array(), "core", $locale);
        $this->messages["context"]["order-payment-gateway"] = $t->trans("Payment gateway", array(), "core", $locale);
        $this->messages["context"]["order-placed"]          = $t->trans("Placed order", array(), "core", $locale);
        $this->messages["context"]["password"]              = $t->trans("Lost password", array(), "core", $locale);
        $this->messages["context"]["product"]               = $t->trans("Product page", array(), "core", $locale);
        $this->messages["context"]["register"]              = $t->trans("Register", array(), "core", $locale);
        $this->messages["context"]["search"]                = $t->trans("Search page", array(), "core", $locale);
        $this->messages["context"]["singleproduct"]         = $t->trans("Product loop", array(), "core", $locale);
        $this->messages["context"]["sitemap"]               = $t->trans("Sitemap", array(), "core", $locale);
        $this->messages["context"]["viewall"]               = $t->trans("All Products", array(), "core", $locale);

        $this->messages["type"]["additional"]                      = $t->trans("additional information", array(), "core", $locale);
        $this->messages["type"]["after-javascript-include"]        = $t->trans("after javascript include", array(), "core", $locale);
        $this->messages["type"]["after-javascript-initialization"] = $t->trans("after javascript initialisation", array(), "core", $locale);
        $this->messages["type"]["body"]                            = $t->trans("main area", array(), "core", $locale);
        $this->messages["type"]["body-bottom"]                     = $t->trans("before the end body tag", array(), "core", $locale);
        $this->messages["type"]["body-top"]                        = $t->trans("after the opening of the body tag", array(), "core", $locale);
        $this->messages["type"]["bottom"]                          = $t->trans("at the bottom", array(), "core", $locale);
        $this->messages["type"]["content"]                         = $t->trans("content area", array(), "core", $locale);
        $this->messages["type"]["content-bottom"]                  = $t->trans("after the main content area", array(), "core", $locale);
        $this->messages["type"]["content-top"]                     = $t->trans("before the main content area", array(), "core", $locale);
        $this->messages["type"]["delivery-address"]                = $t->trans("delivery address", array(), "core", $locale);
        $this->messages["type"]["details-bottom"]                  = $t->trans("at the bottom of the detail area", array(), "core", $locale);
        $this->messages["type"]["details-top"]                     = $t->trans("at the top of the detail", array(), "core", $locale);
        $this->messages["type"]["extra"]                           = $t->trans("extra area", array(), "core", $locale);
        $this->messages["type"]["footer-body"]                     = $t->trans("footer body", array(), "core", $locale);
        $this->messages["type"]["footer-bottom"]                   = $t->trans("bottom of the footer", array(), "core", $locale);
        $this->messages["type"]["footer-top"]                      = $t->trans("at the top of the footer", array(), "core", $locale);
        $this->messages["type"]["form-bottom"]                     = $t->trans("at the bottom of the form", array(), "core", $locale);
        $this->messages["type"]["form-top"]                        = $t->trans("at the top of the form", array(), "core", $locale);
        $this->messages["type"]["gallery"]                         = $t->trans("photo gallery", array(), "core", $locale);
        $this->messages["type"]["head-bottom"]                     = $t->trans("before the end of the head tag", array(), "core", $locale);
        $this->messages["type"]["head-top"]                        = $t->trans("after the opening of the head tag", array(), "core", $locale);
        $this->messages["type"]["header-bottom"]                   = $t->trans("at the bottom of the header", array(), "core", $locale);
        $this->messages["type"]["header-top"]                      = $t->trans("at the top of the header", array(), "core", $locale);
        $this->messages["type"]["javascript"]                      = $t->trans("javascript", array(), "core", $locale);
        $this->messages["type"]["javascript-initialization"]       = $t->trans("javascript initialization", array(), "core", $locale);
        $this->messages["type"]["main-bottom"]                     = $t->trans("at the bottom of the main area", array(), "core", $locale);
        $this->messages["type"]["main-top"]                        = $t->trans("at the top of the main area", array(), "core", $locale);
        $this->messages["type"]["navbar-primary"]                  = $t->trans("primary navigation", array(), "core", $locale);
        $this->messages["type"]["navbar-secondary"]                = $t->trans("secondary navigation", array(), "core", $locale);
        $this->messages["type"]["payment-extra"]                   = $t->trans("extra payment zone", array(), "core", $locale);
        $this->messages["type"]["sidebar-body"]                    = $t->trans("the body of the sidebar", array(), "core", $locale);
        $this->messages["type"]["sidebar-bottom"]                  = $t->trans("at the bottom of the sidebar", array(), "core", $locale);
        $this->messages["type"]["sidebar-top"]                     = $t->trans("at the top of the sidebar", array(), "core", $locale);
        $this->messages["type"]["stylesheet"]                      = $t->trans("CSS stylesheet", array(), "core", $locale);
        $this->messages["type"]["success"]                         = $t->trans("if successful response", array(), "core", $locale);
        $this->messages["type"]["top"]                             = $t->trans("at the top", array(), "core", $locale);
    }

    protected function loadBackOfficeTrans($locale)
    {
        $t = Translator::getInstance();

        $this->messages["context"]["admin-logs"]             = $t->trans("Logs", array(), "core", $locale);
        $this->messages["context"]["administrator"]          = $t->trans("Administrator", array(), "core", $locale);
        $this->messages["context"]["administrators"]         = $t->trans("Administrators", array(), "core", $locale);
        $this->messages["context"]["attribute"]              = $t->trans("Attribut", array(), "core", $locale);
        $this->messages["context"]["attribute-value"]        = $t->trans("Attribute value", array(), "core", $locale);
        $this->messages["context"]["attributes"]             = $t->trans("Attributes", array(), "core", $locale);
        $this->messages["context"]["attributes-value"]       = $t->trans("Attributes value", array(), "core", $locale);
        $this->messages["context"]["catalog"]                = $t->trans("Catalog", array(), "core", $locale);
        $this->messages["context"]["catalog-configuration"]  = $t->trans("Catalog configuration", array(), "core", $locale);
        $this->messages["context"]["categories"]             = $t->trans("Categories", array(), "core", $locale);
        $this->messages["context"]["category"]               = $t->trans("Category", array(), "core", $locale);
        $this->messages["context"]["config-store"]           = $t->trans("Store Information", array(), "core", $locale);
        $this->messages["context"]["configuration"]          = $t->trans("Configuration", array(), "core", $locale);
        $this->messages["context"]["content"]                = $t->trans("Content", array(), "core", $locale);
        $this->messages["context"]["contents"]               = $t->trans("Contents", array(), "core", $locale);
        $this->messages["context"]["countries"]              = $t->trans("Countries", array(), "core", $locale);
        $this->messages["context"]["country"]                = $t->trans("Country", array(), "core", $locale);
        $this->messages["context"]["coupon"]                 = $t->trans("Coupon", array(), "core", $locale);
        $this->messages["context"]["currencies"]             = $t->trans("Currencies", array(), "core", $locale);
        $this->messages["context"]["currency"]               = $t->trans("Currency", array(), "core", $locale);
        $this->messages["context"]["customer"]               = $t->trans("Customer", array(), "core", $locale);
        $this->messages["context"]["customers"]              = $t->trans("Customers", array(), "core", $locale);
        $this->messages["context"]["document"]               = $t->trans("Document", array(), "core", $locale);
        $this->messages["context"]["export"]                 = $t->trans("Export", array(), "core", $locale);
        $this->messages["context"]["feature"]                = $t->trans("Feature", array(), "core", $locale);
        $this->messages["context"]["features"]               = $t->trans("Features", array(), "core", $locale);
        $this->messages["context"]["features-value"]         = $t->trans("Features value", array(), "core", $locale);
        $this->messages["context"]["folder"]                 = $t->trans("Folder", array(), "core", $locale);
        $this->messages["context"]["folders"]                = $t->trans("Folder", array(), "core", $locale);
        $this->messages["context"]["home"]                   = $t->trans("Home", array(), "core", $locale);
        $this->messages["context"]["hook"]                   = $t->trans("Hook", array(), "core", $locale);
        $this->messages["context"]["hooks"]                  = $t->trans("Hooks", array(), "core", $locale);
        $this->messages["context"]["image"]                  = $t->trans("Image", array(), "core", $locale);
        $this->messages["context"]["index"]                  = $t->trans("Dashboard", array(), "core", $locale);
        $this->messages["context"]["language"]               = $t->trans("Language", array(), "core", $locale);
        $this->messages["context"]["languages"]              = $t->trans("Languages", array(), "core", $locale);
        $this->messages["context"]["mailing-system"]         = $t->trans("Mailing system", array(), "core", $locale);
        $this->messages["context"]["main"]                   = $t->trans("Layout", array(), "core", $locale);
        $this->messages["context"]["message"]                = $t->trans("Message", array(), "core", $locale);
        $this->messages["context"]["messages"]               = $t->trans("Messages", array(), "core", $locale);
        $this->messages["context"]["module"]                 = $t->trans("Module", array(), "core", $locale);
        $this->messages["context"]["module-hook"]            = $t->trans("Module hook", array(), "core", $locale);
        $this->messages["context"]["modules"]                = $t->trans("Modules", array(), "core", $locale);
        $this->messages["context"]["order"]                  = $t->trans("Order", array(), "core", $locale);
        $this->messages["context"]["orders"]                 = $t->trans("Orders", array(), "core", $locale);
        $this->messages["context"]["product"]                = $t->trans("Product", array(), "core", $locale);
        $this->messages["context"]["products"]               = $t->trans("Products", array(), "core", $locale);
        $this->messages["context"]["profile"]                = $t->trans("Profile", array(), "core", $locale);
        $this->messages["context"]["profiles"]               = $t->trans("Profiles", array(), "core", $locale);
        $this->messages["context"]["search"]                 = $t->trans("Search", array(), "core", $locale);
        $this->messages["context"]["shipping-configuration"] = $t->trans("Shipping configuration", array(), "core", $locale);
        $this->messages["context"]["shipping-zones"]         = $t->trans("Delivery zone", array(), "core", $locale);
        $this->messages["context"]["system"]                 = $t->trans("System", array(), "core", $locale);
        $this->messages["context"]["system-configuration"]   = $t->trans("System configuration", array(), "core", $locale);
        $this->messages["context"]["tax"]                    = $t->trans("Tax", array(), "core", $locale);
        $this->messages["context"]["tax-rule"]               = $t->trans("tax rule", array(), "core", $locale);
        $this->messages["context"]["taxes"]                  = $t->trans("Taxes", array(), "core", $locale);
        $this->messages["context"]["taxes-rules"]            = $t->trans("Taxes rules", array(), "core", $locale);
        $this->messages["context"]["template"]               = $t->trans("Template", array(), "core", $locale);
        $this->messages["context"]["templates"]              = $t->trans("Templates", array(), "core", $locale);
        $this->messages["context"]["tools"]                  = $t->trans("Tools", array(), "core", $locale);
        $this->messages["context"]["translations"]           = $t->trans("Translations", array(), "core", $locale);
        $this->messages["context"]["variable"]               = $t->trans("Variable", array(), "core", $locale);
        $this->messages["context"]["variables"]              = $t->trans("Variables", array(), "core", $locale);
        $this->messages["context"]["zone"] = $t->trans("Zone", array(), "core", $locale);
        $this->messages["context"]["brand"] = $t->trans("Brand", array(), "core", $locale);
        $this->messages["context"]["brands"] = $t->trans("Brands", array(), "core", $locale);
        $this->messages["context"]["home"] = $t->trans("Home", array(), "core", $locale);
        $this->messages["context"]["main"] = $t->trans("Layout", array(), "core", $locale);
        $this->messages["type"]["block"] = $t->trans("block", array(), "core", $locale);
        $this->messages["type"]["bottom"] = $t->trans("bottom", array(), "core", $locale);
        $this->messages["type"]["create-form"] = $t->trans("create form", array(), "core", $locale);
        $this->messages["type"]["delete-form"] = $t->trans("delete form", array(), "core", $locale);
        $this->messages["type"]["edit-js"] = $t->trans("Edit JavaScript", array(), "core", $locale);
        $this->messages["type"]["js"] = $t->trans("JavaScript", array(), "core", $locale);
        $this->messages["type"]["table-header"] = $t->trans("table header", array(), "core", $locale);
        $this->messages["type"]["table-row"] = $t->trans("table row", array(), "core", $locale);
        $this->messages["type"]["top"] = $t->trans("at the top", array(), "core", $locale);
        $this->messages["type"]["top-menu-catalog"] = $t->trans("in the menu catalog", array(), "core", $locale);
        $this->messages["type"]["top-menu-configuration"] = $t->trans("in the menu configuration", array(), "core", $locale);
        $this->messages["type"]["top-menu-content"] = $t->trans("in the menu folders", array(), "core", $locale);
        $this->messages["type"]["top-menu-customer"] = $t->trans("in the menu customers", array(), "core", $locale);
        $this->messages["type"]["top-menu-modules"] = $t->trans("in the menu modules", array(), "core", $locale);
        $this->messages["type"]["top-menu-order"] = $t->trans("in the menu orders", array(), "core", $locale);
        $this->messages["type"]["top-menu-tools"] = $t->trans("in the menu tools", array(), "core", $locale);
        $this->messages["type"]["topbar-bottom"] = $t->trans("at the bottom of the top bar", array(), "core", $locale);
        $this->messages["type"]["topbar-top"] = $t->trans("at the top of the top bar", array(), "core", $locale);
        $this->messages["type"]["accessories-table-header"] = $t->trans("accessories table header", array(), "core", $locale);
        $this->messages["type"]["accessories-table-row"] = $t->trans("accessories table row", array(), "core", $locale);
        $this->messages["type"]["add-to-all-form"]           = $t->trans("add to all form", array(), "core", $locale);
        $this->messages["type"]["address-create-form"]       = $t->trans("address create form", array(), "core", $locale);
        $this->messages["type"]["address-delete-form"]       = $t->trans("address delete form", array(), "core", $locale);
        $this->messages["type"]["address-update-form"]       = $t->trans("address update form", array(), "core", $locale);
        $this->messages["type"]["after-combinations"]        = $t->trans("after combinations", array(), "core", $locale);
        $this->messages["type"]["after-footer"]              = $t->trans("after footer", array(), "core", $locale);
        $this->messages["type"]["after-top-menu"]            = $t->trans("after top menu", array(), "core", $locale);
        $this->messages["type"]["after-topbar"]              = $t->trans("after top bar", array(), "core", $locale);
        $this->messages["type"]["attributes-table-header"]   = $t->trans("attributes table header", array(), "core", $locale);
        $this->messages["type"]["attributes-table-row"]      = $t->trans("attributes table row", array(), "core", $locale);
        $this->messages["type"]["before-combinations"]       = $t->trans("before combinations", array(), "core", $locale);
        $this->messages["type"]["before-footer"]             = $t->trans("before footer", array(), "core", $locale);
        $this->messages["type"]["before-top-menu"]           = $t->trans("before top menu", array(), "core", $locale);
        $this->messages["type"]["before-topbar"]             = $t->trans("before topbar", array(), "core", $locale);
        $this->messages["type"]["bottom"]                    = $t->trans("bottom", array(), "core", $locale);
        $this->messages["type"]["caption"]                   = $t->trans("caption", array(), "core", $locale);
        $this->messages["type"]["catalog-bottom"]            = $t->trans("at the bottom of the catalog", array(), "core", $locale);
        $this->messages["type"]["catalog-top"]               = $t->trans("at the top of the catalog area", array(), "core", $locale);
        $this->messages["type"]["categories-table-header"]   = $t->trans("categories table header", array(), "core", $locale);
        $this->messages["type"]["categories-table-row"]      = $t->trans("categories table row", array(), "core", $locale);
        $this->messages["type"]["col1-bottom"]               = $t->trans("at the bottom of column 1", array(), "core", $locale);
        $this->messages["type"]["col1-top"]                  = $t->trans("at the top of the column", array(), "core", $locale);
        $this->messages["type"]["combination-delete-form"]   = $t->trans("combination delete form", array(), "core", $locale);
        $this->messages["type"]["combinations-list-caption"] = $t->trans("combinations list caption", array(), "core", $locale);
        $this->messages["type"]["config-js"]                 = $t->trans("configuration JavaScript", array(), "core", $locale);
        $this->messages["type"]["configuration"]             = $t->trans("configuration", array(), "core", $locale);
        $this->messages["type"]["configuration-bottom"]      = $t->trans("configuration bottom", array(), "core", $locale);
        $this->messages["type"]["configuration-top"]         = $t->trans("at the top of the configuration", array(), "core", $locale);
        $this->messages["type"]["content-create-form"]       = $t->trans(" content create form", array(), "core", $locale);
        $this->messages["type"]["content-delete-form"]       = $t->trans("content delete form", array(), "core", $locale);
        $this->messages["type"]["content-edit-js"]           = $t->trans("content edit JavaScript", array(), "core", $locale);
        $this->messages["type"]["contents-table-header"]     = $t->trans("contents table header", array(), "core", $locale);
        $this->messages["type"]["contents-table-row"]        = $t->trans("contents table row", array(), "core", $locale);
        $this->messages["type"]["country-delete-form"]       = $t->trans("country delete form", array(), "core", $locale);
        $this->messages["type"]["create-form"]               = $t->trans("create form", array(), "core", $locale);
        $this->messages["type"]["create-js"]                 = $t->trans("create JavaScript", array(), "core", $locale);
        $this->messages["type"]["delete-form"]               = $t->trans("delete form", array(), "core", $locale);
        $this->messages["type"]["details-details-form"]      = $t->trans("stock edit form", array(), "core", $locale);
        $this->messages["type"]["details-pricing-form"]      = $t->trans("details pricing form", array(), "core", $locale);
        $this->messages["type"]["details-promotion-form"]    = $t->trans("details promotion form", array(), "core", $locale);
        $this->messages["type"]["edit"]                      = $t->trans("Edit", array(), "core", $locale);
        $this->messages["type"]["edit-js"]                   = $t->trans("Edit JavaScript", array(), "core", $locale);
        $this->messages["type"]["features-table-header"]     = $t->trans("features-table-header", array(), "core", $locale);
        $this->messages["type"]["features-table-row"]        = $t->trans("features table row", array(), "core", $locale);
        $this->messages["type"]["folders-table-header"]      = $t->trans("folders table header", array(), "core", $locale);
        $this->messages["type"]["folders-table-row"]         = $t->trans("folders table row", array(), "core", $locale);
        $this->messages["type"]["footer-js"]                 = $t->trans("JavaScript", array(), "core", $locale);
        $this->messages["type"]["head-css"]                  = $t->trans("CSS", array(), "core", $locale);
        $this->messages["type"]["header"]                    = $t->trans("header", array(), "core", $locale);
        $this->messages["type"]["hook-create-form"]          = $t->trans("Hook create form", array(), "core", $locale);
        $this->messages["type"]["hook-delete-form"]          = $t->trans("hook delete form", array(), "core", $locale);
        $this->messages["type"]["hook-edit-js"]              = $t->trans("hook edit JavaScript", array(), "core", $locale);
        $this->messages["type"]["id-delete-form"]            = $t->trans("id delete form", array(), "core", $locale);
        $this->messages["type"]["in-footer"]                 = $t->trans("in footer", array(), "core", $locale);
        $this->messages["type"]["in-top-menu-items"]         = $t->trans("in top menu items", array(), "core", $locale);
        $this->messages["type"]["inside-topbar"]             = $t->trans("inside top bar", array(), "core", $locale);
        $this->messages["type"]["js"]                        = $t->trans("JavaScript", array(), "core", $locale);
        $this->messages["type"]["list-caption"]              = $t->trans("list caption", array(), "core", $locale);
        $this->messages["type"]["list-header"]               = $t->trans("list header", array(), "core", $locale);
        $this->messages["type"]["list-js"]                   = $t->trans("list JavaScript", array(), "core", $locale);
        $this->messages["type"]["list-row"]                  = $t->trans("list row", array(), "core", $locale);
        $this->messages["type"]["logs-js"]                   = $t->trans("logs JavaScript", array(), "core", $locale);
        $this->messages["type"]["mailing-system-js"]         = $t->trans("email system JavaScript", array(), "core", $locale);
        $this->messages["type"]["main"]                      = $t->trans("Main area", array(), "core", $locale);
        $this->messages["type"]["middle"]                    = $t->trans("middle", array(), "core", $locale);
        $this->messages["type"]["product-list"]              = $t->trans("product list", array(), "core", $locale);
        $this->messages["type"]["remove-to-all-form"]        = $t->trans("remove to all form", array(), "core", $locale);
        $this->messages["type"]["row"]                       = $t->trans("row", array(), "core", $locale);
        $this->messages["type"]["rule-create-form"]          = $t->trans("rule create form", array(), "core", $locale);
        $this->messages["type"]["rule-delete-form"]          = $t->trans("rule delete form", array(), "core", $locale);
        $this->messages["type"]["rule-edit-js"]              = $t->trans("rule edit JavaScript", array(), "core", $locale);
        $this->messages["type"]["shipping-bottom"]           = $t->trans("at the bottom of the shipping area", array(), "core", $locale);
        $this->messages["type"]["shipping-top"]              = $t->trans("at the top of the shipping area", array(), "core", $locale);
        $this->messages["type"]["system-bottom"]             = $t->trans("at the bottom of the system area", array(), "core", $locale);
        $this->messages["type"]["system-top"]                = $t->trans("at the top of the system area", array(), "core", $locale);
        $this->messages["type"]["tab-content"]               = $t->trans("content", array(), "core", $locale);
        $this->messages["type"]["table-header"]              = $t->trans("table header", array(), "core", $locale);
        $this->messages["type"]["table-row"]                 = $t->trans("table row", array(), "core", $locale);
        $this->messages["type"]["top"]                       = $t->trans("at the top", array(), "core", $locale);
        $this->messages["type"]["update-form"]               = $t->trans("update form", array(), "core", $locale);
        $this->messages["type"]["update-js"]                 = $t->trans("update JavaScript", array(), "core", $locale);
        $this->messages["type"]["value-create-form"]         = $t->trans("Value create form", array(), "core", $locale);
        $this->messages["type"]["value-table-header"]        = $t->trans("value table header", array(), "core", $locale);
        $this->messages["type"]["value-table-row"]           = $t->trans("value table row", array(), "core", $locale);
    }

    protected function loadPdfOfficeTrans($locale)
    {
        $t = Translator::getInstance();

        $this->messages["context"]["delivery"]       = $t->trans("Delivery", array(), "core", $locale);
        $this->messages["context"]["invoice"]        = $t->trans("Invoice", array(), "core", $locale);
        $this->messages["type"]["after-addresses"]   = $t->trans("after addresse area", array(), "core", $locale);
        $this->messages["type"]["after-information"] = $t->trans("after the information area", array(), "core", $locale);
        $this->messages["type"]["after-products"]    = $t->trans("after product listing", array(), "core", $locale);
        $this->messages["type"]["after-summary"]     = $t->trans("after the order summary", array(), "core", $locale);
        $this->messages["type"]["css"]               = $t->trans("CSS", array(), "core", $locale);
        $this->messages["type"]["delivery-address"]  = $t->trans("delivery address", array(), "core", $locale);
        $this->messages["type"]["footer-bottom"]     = $t->trans("at the bottom of the footer", array(), "core", $locale);
        $this->messages["type"]["footer-top"]        = $t->trans("at the top of the footer", array(), "core", $locale);
        $this->messages["type"]["header"]            = $t->trans("in the header", array(), "core", $locale);
        $this->messages["type"]["imprint"]           = $t->trans("imprint", array(), "core", $locale);
        $this->messages["type"]["information"]       = $t->trans("at the bottom of information area", array(), "core", $locale);
    }

    /**
     * This method do nothing for now
     *
     * @param $locale
     */
    protected function loadEmailTrans($locale)
    {
    }
}
