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
use Thelia\Exception\TheliaProcessException;
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
    protected $messages = [];

    /**
     * @var ParserHelperInterface
     */
    protected $parserHelper;

    public function __construct(ParserHelperInterface $parserHelper)
    {
        $this->parserHelper = $parserHelper;
    }

    /**
     * @param int $templateType
     * @return array
     * @throws \Exception
     */
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
            default:
                throw new TheliaProcessException("Unknown template type: $templateType");
        }

        return $this->parseTemplate($templateType, ConfigQuery::read($tplVar, 'default'));
    }

    /**
     * @param int $templateType
     * @param string $template
     * @return array an array of hooks descriptors
     * @throws \Exception
     */
    public function parseTemplate($templateType, $template)
    {
        $templateDefinition = new TemplateDefinition($template, $templateType);

        $hooks = [];
        $this->walkDir($templateDefinition->getAbsolutePath(), $hooks);

        // Also parse parent templates
        /** @var TemplateDefinition $parentTemplate */
        foreach ($templateDefinition->getParentList() as $parentTemplate) {
            $this->walkDir($parentTemplate->getAbsolutePath(), $hooks);
        }

        // load language message
        $locale = Lang::getDefaultLanguage()->getLocale();
        $this->loadTrans($templateType, $locale);

        $ret = [];
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
     */
    public function walkDir($directory, &$hooks)
    {
        $allowed_exts = ['html', 'tpl', 'xml', 'txt'];

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

                    if (\in_array($ext, $allowed_exts)) {
                        if ($content = file_get_contents($fileInfo->getPathName())) {
                            foreach ($this->parserHelper->getFunctionsDefinition($content, ["hook", "hookblock"]) as $hook) {
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
        $ret = [];
        if (!\array_key_exists("attributes", $hook)) {
            throw new \UnexpectedValueException("The hook should have attributes.");
        }

        $attributes = $hook['attributes'];

        if (\array_key_exists("name", $attributes)) {
            $ret['block'] = ($hook['name'] !== 'hook');

            $ret['code'] = $attributes['name'];
            $params      = explode(".", $attributes['name']);

            if (\count($params) != 2) {
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
            if (\array_key_exists("module", $attributes)) {
                $ret['module'] = true;
                unset($attributes['module']);
            }

            // vars
            if ($ret['block'] && \array_key_exists("vars", $attributes)) {
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

    /**
     * Translate Hook labels
     *
     * @param $context
     * @param $key
     * @return string
     */
    protected function trans($context, $key)
    {
        $message = "";

        if (\array_key_exists($context, $this->messages)) {
            if (\array_key_exists($key, $this->messages[$context])) {
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

        $this->messages["context"]["404"]                   = $t->trans("Page 404", [], "core", $locale);
        $this->messages["context"]["account"]               = $t->trans("customer account", [], "core", $locale);
        $this->messages["context"]["account-password"]      = $t->trans("Change password", [], "core", $locale);
        $this->messages["context"]["account-update"]        = $t->trans("Update customer account", [], "core", $locale);
        $this->messages["context"]["address-create"]        = $t->trans("Address creation", [], "core", $locale);
        $this->messages["context"]["address-update"]        = $t->trans("Address update", [], "core", $locale);
        $this->messages["context"]["badresponseorder"]      = $t->trans("Payment failed", [], "core", $locale);
        $this->messages["context"]["cart"]                  = $t->trans("Cart", [], "core", $locale);
        $this->messages["context"]["category"]              = $t->trans("Category page", [], "core", $locale);
        $this->messages["context"]["contact"]               = $t->trans("Contact page", [], "core", $locale);
        $this->messages["context"]["content"]               = $t->trans("Content page", [], "core", $locale);
        $this->messages["context"]["currency"]              = $t->trans("Curency selection page", [], "core", $locale);
        $this->messages["context"]["folder"]                = $t->trans("Folder page", [], "core", $locale);
        $this->messages["context"]["home"]                  = $t->trans("Home page", [], "core", $locale);
        $this->messages["context"]["language"]              = $t->trans("language selection page", [], "core", $locale);
        $this->messages["context"]["login"]                 = $t->trans("Login page", [], "core", $locale);
        $this->messages["context"]["main"]                  = $t->trans("HTML layout", [], "core", $locale);
        $this->messages["context"]["newsletter"]            = $t->trans("Newsletter page", [], "core", $locale);
        $this->messages["context"]["order-delivery"]        = $t->trans("Delivery choice", [], "core", $locale);
        $this->messages["context"]["order-failed"]          = $t->trans("Order failed", [], "core", $locale);
        $this->messages["context"]["order-invoice"]         = $t->trans("Invoice choice", [], "core", $locale);
        $this->messages["context"]["order-payment-gateway"] = $t->trans("Payment gateway", [], "core", $locale);
        $this->messages["context"]["order-placed"]          = $t->trans("Placed order", [], "core", $locale);
        $this->messages["context"]["password"]              = $t->trans("Lost password", [], "core", $locale);
        $this->messages["context"]["product"]               = $t->trans("Product page", [], "core", $locale);
        $this->messages["context"]["register"]              = $t->trans("Register", [], "core", $locale);
        $this->messages["context"]["search"]                = $t->trans("Search page", [], "core", $locale);
        $this->messages["context"]["singleproduct"]         = $t->trans("Product loop", [], "core", $locale);
        $this->messages["context"]["sitemap"]               = $t->trans("Sitemap", [], "core", $locale);
        $this->messages["context"]["viewall"]               = $t->trans("All Products", [], "core", $locale);

        $this->messages["type"]["additional"]                      = $t->trans("additional information", [], "core", $locale);
        $this->messages["type"]["after-javascript-include"]        = $t->trans("after javascript include", [], "core", $locale);
        $this->messages["type"]["after-javascript-initialization"] = $t->trans("after javascript initialisation", [], "core", $locale);
        $this->messages["type"]["body"]                            = $t->trans("main area", [], "core", $locale);
        $this->messages["type"]["body-bottom"]                     = $t->trans("before the end body tag", [], "core", $locale);
        $this->messages["type"]["body-top"]                        = $t->trans("after the opening of the body tag", [], "core", $locale);
        $this->messages["type"]["bottom"]                          = $t->trans("at the bottom", [], "core", $locale);
        $this->messages["type"]["content"]                         = $t->trans("content area", [], "core", $locale);
        $this->messages["type"]["content-bottom"]                  = $t->trans("after the main content area", [], "core", $locale);
        $this->messages["type"]["content-top"]                     = $t->trans("before the main content area", [], "core", $locale);
        $this->messages["type"]["delivery-address"]                = $t->trans("delivery address", [], "core", $locale);
        $this->messages["type"]["details-bottom"]                  = $t->trans("at the bottom of the detail area", [], "core", $locale);
        $this->messages["type"]["details-top"]                     = $t->trans("at the top of the detail", [], "core", $locale);
        $this->messages["type"]["extra"]                           = $t->trans("extra area", [], "core", $locale);
        $this->messages["type"]["footer-body"]                     = $t->trans("footer body", [], "core", $locale);
        $this->messages["type"]["footer-bottom"]                   = $t->trans("bottom of the footer", [], "core", $locale);
        $this->messages["type"]["footer-top"]                      = $t->trans("at the top of the footer", [], "core", $locale);
        $this->messages["type"]["form-bottom"]                     = $t->trans("at the bottom of the form", [], "core", $locale);
        $this->messages["type"]["form-top"]                        = $t->trans("at the top of the form", [], "core", $locale);
        $this->messages["type"]["gallery"]                         = $t->trans("photo gallery", [], "core", $locale);
        $this->messages["type"]["head-bottom"]                     = $t->trans("before the end of the head tag", [], "core", $locale);
        $this->messages["type"]["head-top"]                        = $t->trans("after the opening of the head tag", [], "core", $locale);
        $this->messages["type"]["header-bottom"]                   = $t->trans("at the bottom of the header", [], "core", $locale);
        $this->messages["type"]["header-top"]                      = $t->trans("at the top of the header", [], "core", $locale);
        $this->messages["type"]["javascript"]                      = $t->trans("javascript", [], "core", $locale);
        $this->messages["type"]["javascript-initialization"]       = $t->trans("javascript initialization", [], "core", $locale);
        $this->messages["type"]["main-bottom"]                     = $t->trans("at the bottom of the main area", [], "core", $locale);
        $this->messages["type"]["main-top"]                        = $t->trans("at the top of the main area", [], "core", $locale);
        $this->messages["type"]["navbar-primary"]                  = $t->trans("primary navigation", [], "core", $locale);
        $this->messages["type"]["navbar-secondary"]                = $t->trans("secondary navigation", [], "core", $locale);
        $this->messages["type"]["payment-extra"]                   = $t->trans("extra payment zone", [], "core", $locale);
        $this->messages["type"]["sidebar-body"]                    = $t->trans("the body of the sidebar", [], "core", $locale);
        $this->messages["type"]["sidebar-bottom"]                  = $t->trans("at the bottom of the sidebar", [], "core", $locale);
        $this->messages["type"]["sidebar-top"]                     = $t->trans("at the top of the sidebar", [], "core", $locale);
        $this->messages["type"]["stylesheet"]                      = $t->trans("CSS stylesheet", [], "core", $locale);
        $this->messages["type"]["success"]                         = $t->trans("if successful response", [], "core", $locale);
        $this->messages["type"]["top"]                             = $t->trans("at the top", [], "core", $locale);
    }

    protected function loadBackOfficeTrans($locale)
    {
        $t = Translator::getInstance();

        $this->messages["context"]["admin-logs"]             = $t->trans("Logs", [], "core", $locale);
        $this->messages["context"]["administrator"]          = $t->trans("Administrator", [], "core", $locale);
        $this->messages["context"]["administrators"]         = $t->trans("Administrators", [], "core", $locale);
        $this->messages["context"]["attribute"]              = $t->trans("Attribut", [], "core", $locale);
        $this->messages["context"]["attribute-value"]        = $t->trans("Attribute value", [], "core", $locale);
        $this->messages["context"]["attributes"]             = $t->trans("Attributes", [], "core", $locale);
        $this->messages["context"]["attributes-value"]       = $t->trans("Attributes value", [], "core", $locale);
        $this->messages["context"]["catalog"]                = $t->trans("Catalog", [], "core", $locale);
        $this->messages["context"]["catalog-configuration"]  = $t->trans("Catalog configuration", [], "core", $locale);
        $this->messages["context"]["categories"]             = $t->trans("Categories", [], "core", $locale);
        $this->messages["context"]["category"]               = $t->trans("Category", [], "core", $locale);
        $this->messages["context"]["config-store"]           = $t->trans("Store Information", [], "core", $locale);
        $this->messages["context"]["configuration"]          = $t->trans("Configuration", [], "core", $locale);
        $this->messages["context"]["content"]                = $t->trans("Content", [], "core", $locale);
        $this->messages["context"]["contents"]               = $t->trans("Contents", [], "core", $locale);
        $this->messages["context"]["countries"]              = $t->trans("Countries", [], "core", $locale);
        $this->messages["context"]["country"]                = $t->trans("Country", [], "core", $locale);
        $this->messages["context"]["coupon"]                 = $t->trans("Coupon", [], "core", $locale);
        $this->messages["context"]["currencies"]             = $t->trans("Currencies", [], "core", $locale);
        $this->messages["context"]["currency"]               = $t->trans("Currency", [], "core", $locale);
        $this->messages["context"]["customer"]               = $t->trans("Customer", [], "core", $locale);
        $this->messages["context"]["customers"]              = $t->trans("Customers", [], "core", $locale);
        $this->messages["context"]["document"]               = $t->trans("Document", [], "core", $locale);
        $this->messages["context"]["export"]                 = $t->trans("Export", [], "core", $locale);
        $this->messages["context"]["feature"]                = $t->trans("Feature", [], "core", $locale);
        $this->messages["context"]["features"]               = $t->trans("Features", [], "core", $locale);
        $this->messages["context"]["features-value"]         = $t->trans("Features value", [], "core", $locale);
        $this->messages["context"]["folder"]                 = $t->trans("Folder", [], "core", $locale);
        $this->messages["context"]["folders"]                = $t->trans("Folder", [], "core", $locale);
        $this->messages["context"]["home"]                   = $t->trans("Home", [], "core", $locale);
        $this->messages["context"]["hook"]                   = $t->trans("Hook", [], "core", $locale);
        $this->messages["context"]["hooks"]                  = $t->trans("Hooks", [], "core", $locale);
        $this->messages["context"]["image"]                  = $t->trans("Image", [], "core", $locale);
        $this->messages["context"]["index"]                  = $t->trans("Dashboard", [], "core", $locale);
        $this->messages["context"]["language"]               = $t->trans("Language", [], "core", $locale);
        $this->messages["context"]["languages"]              = $t->trans("Languages", [], "core", $locale);
        $this->messages["context"]["mailing-system"]         = $t->trans("Mailing system", [], "core", $locale);
        $this->messages["context"]["main"]                   = $t->trans("Layout", [], "core", $locale);
        $this->messages["context"]["message"]                = $t->trans("Message", [], "core", $locale);
        $this->messages["context"]["messages"]               = $t->trans("Messages", [], "core", $locale);
        $this->messages["context"]["module"]                 = $t->trans("Module", [], "core", $locale);
        $this->messages["context"]["module-hook"]            = $t->trans("Module hook", [], "core", $locale);
        $this->messages["context"]["modules"]                = $t->trans("Modules", [], "core", $locale);
        $this->messages["context"]["order"]                  = $t->trans("Order", [], "core", $locale);
        $this->messages["context"]["orders"]                 = $t->trans("Orders", [], "core", $locale);
        $this->messages["context"]["product"]                = $t->trans("Product", [], "core", $locale);
        $this->messages["context"]["products"]               = $t->trans("Products", [], "core", $locale);
        $this->messages["context"]["profile"]                = $t->trans("Profile", [], "core", $locale);
        $this->messages["context"]["profiles"]               = $t->trans("Profiles", [], "core", $locale);
        $this->messages["context"]["search"]                 = $t->trans("Search", [], "core", $locale);
        $this->messages["context"]["shipping-configuration"] = $t->trans("Shipping configuration", [], "core", $locale);
        $this->messages["context"]["shipping-zones"]         = $t->trans("Delivery zone", [], "core", $locale);
        $this->messages["context"]["system"]                 = $t->trans("System", [], "core", $locale);
        $this->messages["context"]["system-configuration"]   = $t->trans("System configuration", [], "core", $locale);
        $this->messages["context"]["tax"]                    = $t->trans("Tax", [], "core", $locale);
        $this->messages["context"]["tax-rule"]               = $t->trans("tax rule", [], "core", $locale);
        $this->messages["context"]["taxes"]                  = $t->trans("Taxes", [], "core", $locale);
        $this->messages["context"]["taxes-rules"]            = $t->trans("Taxes rules", [], "core", $locale);
        $this->messages["context"]["template"]               = $t->trans("Template", [], "core", $locale);
        $this->messages["context"]["templates"]              = $t->trans("Templates", [], "core", $locale);
        $this->messages["context"]["tools"]                  = $t->trans("Tools", [], "core", $locale);
        $this->messages["context"]["translations"]           = $t->trans("Translations", [], "core", $locale);
        $this->messages["context"]["variable"]               = $t->trans("Variable", [], "core", $locale);
        $this->messages["context"]["variables"]              = $t->trans("Variables", [], "core", $locale);
        $this->messages["context"]["zone"] = $t->trans("Zone", [], "core", $locale);
        $this->messages["context"]["brand"] = $t->trans("Brand", [], "core", $locale);
        $this->messages["context"]["brands"] = $t->trans("Brands", [], "core", $locale);
        $this->messages["context"]["home"] = $t->trans("Home", [], "core", $locale);
        $this->messages["context"]["main"] = $t->trans("Layout", [], "core", $locale);
        $this->messages["type"]["block"] = $t->trans("block", [], "core", $locale);
        $this->messages["type"]["bottom"] = $t->trans("bottom", [], "core", $locale);
        $this->messages["type"]["create-form"] = $t->trans("create form", [], "core", $locale);
        $this->messages["type"]["delete-form"] = $t->trans("delete form", [], "core", $locale);
        $this->messages["type"]["edit-js"] = $t->trans("Edit JavaScript", [], "core", $locale);
        $this->messages["type"]["js"] = $t->trans("JavaScript", [], "core", $locale);
        $this->messages["type"]["table-header"] = $t->trans("table header", [], "core", $locale);
        $this->messages["type"]["table-row"] = $t->trans("table row", [], "core", $locale);
        $this->messages["type"]["top"] = $t->trans("at the top", [], "core", $locale);
        $this->messages["type"]["top-menu-catalog"] = $t->trans("in the menu catalog", [], "core", $locale);
        $this->messages["type"]["top-menu-configuration"] = $t->trans("in the menu configuration", [], "core", $locale);
        $this->messages["type"]["top-menu-content"] = $t->trans("in the menu folders", [], "core", $locale);
        $this->messages["type"]["top-menu-customer"] = $t->trans("in the menu customers", [], "core", $locale);
        $this->messages["type"]["top-menu-modules"] = $t->trans("in the menu modules", [], "core", $locale);
        $this->messages["type"]["top-menu-order"] = $t->trans("in the menu orders", [], "core", $locale);
        $this->messages["type"]["top-menu-tools"] = $t->trans("in the menu tools", [], "core", $locale);
        $this->messages["type"]["topbar-bottom"] = $t->trans("at the bottom of the top bar", [], "core", $locale);
        $this->messages["type"]["topbar-top"] = $t->trans("at the top of the top bar", [], "core", $locale);
        $this->messages["type"]["accessories-table-header"] = $t->trans("accessories table header", [], "core", $locale);
        $this->messages["type"]["accessories-table-row"] = $t->trans("accessories table row", [], "core", $locale);
        $this->messages["type"]["add-to-all-form"]           = $t->trans("add to all form", [], "core", $locale);
        $this->messages["type"]["address-create-form"]       = $t->trans("address create form", [], "core", $locale);
        $this->messages["type"]["address-delete-form"]       = $t->trans("address delete form", [], "core", $locale);
        $this->messages["type"]["address-update-form"]       = $t->trans("address update form", [], "core", $locale);
        $this->messages["type"]["after-combinations"]        = $t->trans("after combinations", [], "core", $locale);
        $this->messages["type"]["after-footer"]              = $t->trans("after footer", [], "core", $locale);
        $this->messages["type"]["after-top-menu"]            = $t->trans("after top menu", [], "core", $locale);
        $this->messages["type"]["after-topbar"]              = $t->trans("after top bar", [], "core", $locale);
        $this->messages["type"]["attributes-table-header"]   = $t->trans("attributes table header", [], "core", $locale);
        $this->messages["type"]["attributes-table-row"]      = $t->trans("attributes table row", [], "core", $locale);
        $this->messages["type"]["before-combinations"]       = $t->trans("before combinations", [], "core", $locale);
        $this->messages["type"]["before-footer"]             = $t->trans("before footer", [], "core", $locale);
        $this->messages["type"]["before-top-menu"]           = $t->trans("before top menu", [], "core", $locale);
        $this->messages["type"]["before-topbar"]             = $t->trans("before topbar", [], "core", $locale);
        $this->messages["type"]["bottom"]                    = $t->trans("bottom", [], "core", $locale);
        $this->messages["type"]["caption"]                   = $t->trans("caption", [], "core", $locale);
        $this->messages["type"]["catalog-bottom"]            = $t->trans("at the bottom of the catalog", [], "core", $locale);
        $this->messages["type"]["catalog-top"]               = $t->trans("at the top of the catalog area", [], "core", $locale);
        $this->messages["type"]["categories-table-header"]   = $t->trans("categories table header", [], "core", $locale);
        $this->messages["type"]["categories-table-row"]      = $t->trans("categories table row", [], "core", $locale);
        $this->messages["type"]["col1-bottom"]               = $t->trans("at the bottom of column 1", [], "core", $locale);
        $this->messages["type"]["col1-top"]                  = $t->trans("at the top of the column", [], "core", $locale);
        $this->messages["type"]["combination-delete-form"]   = $t->trans("combination delete form", [], "core", $locale);
        $this->messages["type"]["combinations-list-caption"] = $t->trans("combinations list caption", [], "core", $locale);
        $this->messages["type"]["config-js"]                 = $t->trans("configuration JavaScript", [], "core", $locale);
        $this->messages["type"]["configuration"]             = $t->trans("configuration", [], "core", $locale);
        $this->messages["type"]["configuration-bottom"]      = $t->trans("configuration bottom", [], "core", $locale);
        $this->messages["type"]["configuration-top"]         = $t->trans("at the top of the configuration", [], "core", $locale);
        $this->messages["type"]["content-create-form"]       = $t->trans(" content create form", [], "core", $locale);
        $this->messages["type"]["content-delete-form"]       = $t->trans("content delete form", [], "core", $locale);
        $this->messages["type"]["content-edit-js"]           = $t->trans("content edit JavaScript", [], "core", $locale);
        $this->messages["type"]["contents-table-header"]     = $t->trans("contents table header", [], "core", $locale);
        $this->messages["type"]["contents-table-row"]        = $t->trans("contents table row", [], "core", $locale);
        $this->messages["type"]["country-delete-form"]       = $t->trans("country delete form", [], "core", $locale);
        $this->messages["type"]["create-form"]               = $t->trans("create form", [], "core", $locale);
        $this->messages["type"]["create-js"]                 = $t->trans("create JavaScript", [], "core", $locale);
        $this->messages["type"]["delete-form"]               = $t->trans("delete form", [], "core", $locale);
        $this->messages["type"]["details-details-form"]      = $t->trans("stock edit form", [], "core", $locale);
        $this->messages["type"]["details-pricing-form"]      = $t->trans("details pricing form", [], "core", $locale);
        $this->messages["type"]["details-promotion-form"]    = $t->trans("details promotion form", [], "core", $locale);
        $this->messages["type"]["edit"]                      = $t->trans("Edit", [], "core", $locale);
        $this->messages["type"]["edit-js"]                   = $t->trans("Edit JavaScript", [], "core", $locale);
        $this->messages["type"]["features-table-header"]     = $t->trans("features-table-header", [], "core", $locale);
        $this->messages["type"]["features-table-row"]        = $t->trans("features table row", [], "core", $locale);
        $this->messages["type"]["folders-table-header"]      = $t->trans("folders table header", [], "core", $locale);
        $this->messages["type"]["folders-table-row"]         = $t->trans("folders table row", [], "core", $locale);
        $this->messages["type"]["footer-js"]                 = $t->trans("JavaScript", [], "core", $locale);
        $this->messages["type"]["head-css"]                  = $t->trans("CSS", [], "core", $locale);
        $this->messages["type"]["header"]                    = $t->trans("header", [], "core", $locale);
        $this->messages["type"]["hook-create-form"]          = $t->trans("Hook create form", [], "core", $locale);
        $this->messages["type"]["hook-delete-form"]          = $t->trans("hook delete form", [], "core", $locale);
        $this->messages["type"]["hook-edit-js"]              = $t->trans("hook edit JavaScript", [], "core", $locale);
        $this->messages["type"]["id-delete-form"]            = $t->trans("id delete form", [], "core", $locale);
        $this->messages["type"]["in-footer"]                 = $t->trans("in footer", [], "core", $locale);
        $this->messages["type"]["in-top-menu-items"]         = $t->trans("in top menu items", [], "core", $locale);
        $this->messages["type"]["inside-topbar"]             = $t->trans("inside top bar", [], "core", $locale);
        $this->messages["type"]["js"]                        = $t->trans("JavaScript", [], "core", $locale);
        $this->messages["type"]["list-caption"]              = $t->trans("list caption", [], "core", $locale);
        $this->messages["type"]["list-header"]               = $t->trans("list header", [], "core", $locale);
        $this->messages["type"]["list-js"]                   = $t->trans("list JavaScript", [], "core", $locale);
        $this->messages["type"]["list-row"]                  = $t->trans("list row", [], "core", $locale);
        $this->messages["type"]["logs-js"]                   = $t->trans("logs JavaScript", [], "core", $locale);
        $this->messages["type"]["mailing-system-js"]         = $t->trans("email system JavaScript", [], "core", $locale);
        $this->messages["type"]["main"]                      = $t->trans("Main area", [], "core", $locale);
        $this->messages["type"]["middle"]                    = $t->trans("middle", [], "core", $locale);
        $this->messages["type"]["product-list"]              = $t->trans("product list", [], "core", $locale);
        $this->messages["type"]["remove-to-all-form"]        = $t->trans("remove to all form", [], "core", $locale);
        $this->messages["type"]["row"]                       = $t->trans("row", [], "core", $locale);
        $this->messages["type"]["rule-create-form"]          = $t->trans("rule create form", [], "core", $locale);
        $this->messages["type"]["rule-delete-form"]          = $t->trans("rule delete form", [], "core", $locale);
        $this->messages["type"]["rule-edit-js"]              = $t->trans("rule edit JavaScript", [], "core", $locale);
        $this->messages["type"]["shipping-bottom"]           = $t->trans("at the bottom of the shipping area", [], "core", $locale);
        $this->messages["type"]["shipping-top"]              = $t->trans("at the top of the shipping area", [], "core", $locale);
        $this->messages["type"]["system-bottom"]             = $t->trans("at the bottom of the system area", [], "core", $locale);
        $this->messages["type"]["system-top"]                = $t->trans("at the top of the system area", [], "core", $locale);
        $this->messages["type"]["tab-content"]               = $t->trans("content", [], "core", $locale);
        $this->messages["type"]["table-header"]              = $t->trans("table header", [], "core", $locale);
        $this->messages["type"]["table-row"]                 = $t->trans("table row", [], "core", $locale);
        $this->messages["type"]["top"]                       = $t->trans("at the top", [], "core", $locale);
        $this->messages["type"]["update-form"]               = $t->trans("update form", [], "core", $locale);
        $this->messages["type"]["update-js"]                 = $t->trans("update JavaScript", [], "core", $locale);
        $this->messages["type"]["value-create-form"]         = $t->trans("Value create form", [], "core", $locale);
        $this->messages["type"]["value-table-header"]        = $t->trans("value table header", [], "core", $locale);
        $this->messages["type"]["value-table-row"]           = $t->trans("value table row", [], "core", $locale);
    }

    protected function loadPdfOfficeTrans($locale)
    {
        $t = Translator::getInstance();

        $this->messages["context"]["delivery"]       = $t->trans("Delivery", [], "core", $locale);
        $this->messages["context"]["invoice"]        = $t->trans("Invoice", [], "core", $locale);
        $this->messages["type"]["after-addresses"]   = $t->trans("after addresse area", [], "core", $locale);
        $this->messages["type"]["after-information"] = $t->trans("after the information area", [], "core", $locale);
        $this->messages["type"]["after-products"]    = $t->trans("after product listing", [], "core", $locale);
        $this->messages["type"]["after-summary"]     = $t->trans("after the order summary", [], "core", $locale);
        $this->messages["type"]["css"]               = $t->trans("CSS", [], "core", $locale);
        $this->messages["type"]["delivery-address"]  = $t->trans("delivery address", [], "core", $locale);
        $this->messages["type"]["footer-bottom"]     = $t->trans("at the bottom of the footer", [], "core", $locale);
        $this->messages["type"]["footer-top"]        = $t->trans("at the top of the footer", [], "core", $locale);
        $this->messages["type"]["header"]            = $t->trans("in the header", [], "core", $locale);
        $this->messages["type"]["imprint"]           = $t->trans("imprint", [], "core", $locale);
        $this->messages["type"]["information"]       = $t->trans("at the bottom of information area", [], "core", $locale);
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
