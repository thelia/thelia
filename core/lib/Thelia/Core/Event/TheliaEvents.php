<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event;

/**
 *
 * This class contains all Thelia events identifiers used by Thelia Core
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */

final class TheliaEvents
{
    // -- CORE EVENTS ---------------------------------------------------------
    /**
     * sent at the beginning
     */
    const BOOT = "thelia.boot";
    /**
     * Kernel View Check Handle
     */
    const VIEW_CHECK = "thelia.view_check";
    // -- END CORE EVENTS ---------------------------------------------------------
    // -- ADDRESS EVENTS ---------------------------------------------------------

    /**
     * sent for address creation
     */
    const ADDRESS_CREATE = "action.createAddress";
    const ADDRESS_UPDATE = "action.updateAddress";
    const ADDRESS_DELETE = "action.deleteAddress";

    /**
     * sent when an address is tag as default
     */
    const ADDRESS_DEFAULT = "action.defaultAddress";

    // -- END ADDRESS EVENTS ---------------------------------------------------------

    // -- ADMIN EVENTS ---------------------------------------------------------
    /**
     * Sent before the logout of the administrator.
     */
    const ADMIN_LOGOUT = "action.admin_logout";
    /**
     * Sent once the administrator is successfully logged in.
     */
    const ADMIN_LOGIN  = "action.admin_login";

    // -- END ADMIN EVENTS --------------------------------------------------------

    // -- AREA EVENTS ---------------------------------------------------------

    const AREA_CREATE = 'action.createArea';
    const AREA_UPDATE = 'action.updateArea';
    const AREA_DELETE = 'action.deleteArea';

    const AREA_REMOVE_COUNTRY = 'action.area.removeCountry';
    const AREA_POSTAGE_UPDATE = 'action.area.postageUpdate';
    const AREA_ADD_COUNTRY = 'action.area.addCountry';

    // -- END AREA EVENTS ---------------------------------------------------------

    // -- CATEGORIES EVENTS -----------------------------------------------

    const CATEGORY_CREATE            = "action.createCategory";
    const CATEGORY_UPDATE            = "action.updateCategory";
    const CATEGORY_DELETE            = "action.deleteCategory";

    const CATEGORY_TOGGLE_VISIBILITY = "action.toggleCategoryVisibility";
    const CATEGORY_UPDATE_POSITION   = "action.updateCategoryPosition";

    const CATEGORY_ADD_CONTENT      = "action.categoryAddContent";
    const CATEGORY_REMOVE_CONTENT   = "action.categoryRemoveContent";

    const CATEGORY_UPDATE_SEO        = "action.updateCategorySeo";

    const VIEW_CATEGORY_ID_NOT_VISIBLE = "action.viewCategoryIdNotVisible";
    // -- END CATEGORIES EVENTS -----------------------------------------------

    // -- CONTENT EVENTS -----------------------------------------------

    const CONTENT_CREATE            = "action.createContent";
    const CONTENT_UPDATE            = "action.updateContent";
    const CONTENT_DELETE            = "action.deleteContent";

    const CONTENT_TOGGLE_VISIBILITY = "action.toggleContentVisibility";
    const CONTENT_UPDATE_POSITION   = "action.updateContentPosition";
    const CONTENT_UPDATE_SEO        = "action.updateContentSeo";

    const CONTENT_ADD_FOLDER      = "action.contentAddFolder";
    const CONTENT_REMOVE_FOLDER   = "action.contentRemoveFolder";

    const VIEW_CONTENT_ID_NOT_VISIBLE = "action.viewContentIdNotVisible";
    // -- END CONTENT EVENTS ---------------------------------------------------------

    // -- COUNTRY EVENTS -----------------------------------------------

    const COUNTRY_CREATE            = "action.state.create";
    const COUNTRY_UPDATE            = "action.state.update";
    const COUNTRY_DELETE            = "action.state.delete";

    const COUNTRY_TOGGLE_DEFAULT = "action.toggleCountryDefault";
    const COUNTRY_TOGGLE_VISIBILITY = "action.state.toggleVisibility";
    // -- END COUNTRY EVENTS ---------------------------------------------------------

    // -- STATE EVENTS -----------------------------------------------

    const STATE_CREATE            = "action.createState";
    const STATE_UPDATE            = "action.updateState";
    const STATE_DELETE            = "action.deleteState";

    const STATE_TOGGLE_VISIBILITY = "action.toggleCountryVisibility";
    // -- END STATE EVENTS ---------------------------------------------------------

    // -- CUSTOMER EVENTS ---------------------------------------------------------
    /**
     * Sent before the logout of the customer.
     */
    const CUSTOMER_LOGOUT = "action.customer_logout";
    /**
     * Sent once the customer is successfully logged in.
     */
    const CUSTOMER_LOGIN  = "action.customer_login";
    /**
     * sent on customer account creation
     */
    const CUSTOMER_CREATEACCOUNT = "action.createCustomer";
    /**
     * sent on customer account update
     */
    const CUSTOMER_UPDATEACCOUNT = "action.updateCustomer";
    /**
     * sent on customer account update profile
     */
    const CUSTOMER_UPDATEPROFILE = "action.updateProfileCustomer";
    /**
     * sent on customer removal
     */
    const CUSTOMER_DELETEACCOUNT = "action.deleteCustomer";

    /**
     * sent when a customer need a new password
     */
    const LOST_PASSWORD = "action.lostPassword";

    /**
     * Send the account ccreation confirmation email
     */
    const SEND_ACCOUNT_CONFIRMATION_EMAIL = "action.customer.sendAccountConfirmationEmail";

    // -- END CUSTOMER EVENTS ---------------------------------------------------------

    // -- FOLDER EVENTS -----------------------------------------------

    const FOLDER_CREATE            = "action.createFolder";
    const FOLDER_UPDATE            = "action.updateFolder";
    const FOLDER_DELETE            = "action.deleteFolder";

    const FOLDER_TOGGLE_VISIBILITY = "action.toggleFolderVisibility";
    const FOLDER_UPDATE_POSITION   = "action.updateFolderPosition";
    const FOLDER_UPDATE_SEO        = "action.updateFolderSeo";

    const VIEW_FOLDER_ID_NOT_VISIBLE = "action.viewFolderIdNotVisible";
    // -- END FOLDER EVENTS ---------------------------------------------------------

    // -- PRODUCT EVENTS -----------------------------------------------

    const PRODUCT_CREATE            = "action.createProduct";
    const PRODUCT_UPDATE            = "action.updateProduct";
    const PRODUCT_DELETE            = "action.deleteProduct";

    const PRODUCT_TOGGLE_VISIBILITY = "action.toggleProductVisibility";
    const PRODUCT_UPDATE_POSITION   = "action.updateProductPosition";
    const PRODUCT_UPDATE_SEO        = "action.updateProductSeo";

    const PRODUCT_ADD_CONTENT             = "action.productAddContent";
    const PRODUCT_REMOVE_CONTENT          = "action.productRemoveContent";
    const PRODUCT_UPDATE_CONTENT_POSITION = "action.updateProductContentPosition";

    const PRODUCT_ADD_PRODUCT_SALE_ELEMENT    = "action.addProductSaleElement";
    const PRODUCT_DELETE_PRODUCT_SALE_ELEMENT = "action.deleteProductSaleElement";
    const PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT = "action.updateProductSaleElement";

    const PRODUCT_COMBINATION_GENERATION = "action.productCombinationGeneration";

    const PRODUCT_SET_TEMPLATE = "action.productSetTemplate";

    const PRODUCT_ADD_ACCESSORY             = "action.productAddProductAccessory";
    const PRODUCT_REMOVE_ACCESSORY          = "action.productRemoveProductAccessory";
    const PRODUCT_UPDATE_ACCESSORY_POSITION = "action.updateProductAccessoryPosition";

    const PRODUCT_FEATURE_UPDATE_VALUE = "action.updateProductFeatureValue";
    const PRODUCT_FEATURE_DELETE_VALUE = "action.deleteProductFeatureValue";

    const PRODUCT_ADD_CATEGORY    = "action.addProductCategory";
    const PRODUCT_REMOVE_CATEGORY = "action.deleteProductCategory";

    const VIRTUAL_PRODUCT_ORDER_HANDLE = "action.virtualProduct.handleOrder";
    const VIRTUAL_PRODUCT_ORDER_DOWNLOAD_RESPONSE = "action.virtualProduct.downloadResponse";

    const VIEW_PRODUCT_ID_NOT_VISIBLE = "action.viewProductIdNotVisible";
    // -- END PRODUCT EVENTS ---------------------------------------------------------

    // -- CLONE EVENTS ------------------------------------------------------------

    const PRODUCT_CLONE = "action.cloneProduct";
    const FILE_CLONE = "action.cloneFile";
    const PSE_CLONE = "action.clonePSE";

    // -- END CLONE EVENTS ------------------------------------------------------------

    // -- SHIPPING ZONE MANAGEMENT

    const SHIPPING_ZONE_ADD_AREA = 'action.shippingZone.addArea';
    const SHIPPING_ZONE_REMOVE_AREA = 'action.shippingZone.removeArea';

    // -- END SHIPPING ZONE MANAGEMENT

    /** Persist a cart */
    const CART_PERSIST = "cart.persist";

    /** Restore a current cart in the session, either by reloading it from the database, or creating a new one */
    const CART_RESTORE_CURRENT = "cart.restore.current";

    /** Create a new, empty cart in the session, and attach it to the current customer, if any. */
    const CART_CREATE_NEW = "cart.create.new";

    /**
     * sent when a new existing cat id duplicated. This append when current customer is different from current cart
     * The old cart is already deleted from the database when this event is dispatched.
     */
    const CART_DUPLICATE = "cart.duplicate";

    /**
     * Sent when the cart is duplicated, but not yet deleted from the database.
     */
    const CART_DUPLICATED = "cart.duplicated";

    /**
     * Sent when a cart item is duplicated
     */
    const CART_ITEM_DUPLICATE = "cart.item.duplicate";

    /**
     * sent when a new item is added to current cart
     */
    const AFTER_CARTADDITEM = "cart.after.addItem";

    /**
     * sent for searching an item in the cart
     */
    const CART_FINDITEM = "cart.findItem";

    /**
     * sent when a cart item is modify
     */
    const AFTER_CARTUPDATEITEM = "cart.updateItem";

    /**
     * sent for addArticle action
     */
    const CART_ADDITEM = "action.addArticle";

    /**
     * sent on modify article action
     */
    const CART_UPDATEITEM = "action.updateArticle";
    const CART_DELETEITEM = "action.deleteArticle";
    const CART_CLEAR = "action.clear";

    /** before inserting a cart item in database */
    const CART_ITEM_CREATE_BEFORE = "action.cart.item.create.before";

    /** before updating a cart item in database */
    const CART_ITEM_UPDATE_BEFORE = "action.cart.item.update.before";

    /**
     * Order linked event
     */
    const ORDER_SET_DELIVERY_ADDRESS = "action.order.setDeliveryAddress";
    const ORDER_SET_DELIVERY_MODULE = "action.order.setDeliveryModule";
    const ORDER_SET_POSTAGE = "action.order.setPostage";
    const ORDER_SET_INVOICE_ADDRESS = "action.order.setInvoiceAddress";
    const ORDER_SET_PAYMENT_MODULE = "action.order.setPaymentModule";
    const ORDER_PAY = "action.order.pay";
    const ORDER_BEFORE_PAYMENT = "action.order.beforePayment";
    const ORDER_CART_CLEAR = "action.order.cartClear";

    const ORDER_CREATE_MANUAL = "action.order.createManual";

    const ORDER_UPDATE_STATUS = "action.order.updateStatus";

    const ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE = "action.order.getStockUpdateOperationOnOrderStatusChange";

    const ORDER_SEND_CONFIRMATION_EMAIL = "action.order.sendOrderConfirmationEmail";

    const ORDER_SEND_NOTIFICATION_EMAIL = "action.order.sendOrderNotificationEmail";

    const ORDER_UPDATE_DELIVERY_REF = "action.order.updateDeliveryRef";
    const ORDER_UPDATE_TRANSACTION_REF = "action.order.updateTransactionRef";
    const ORDER_UPDATE_ADDRESS = "action.order.updateAddress";

    const ORDER_PRODUCT_BEFORE_CREATE = "action.orderProduct.beforeCreate";
    const ORDER_PRODUCT_AFTER_CREATE = "action.orderProduct.afterCreate";

    /**
     * Sent on image processing
     */
    const IMAGE_PROCESS = "action.processImage";

    /**
     * Sent just after creating the image object from the image file
     */
    const IMAGE_PREPROCESSING = "action.preProcessImage";

    /**
     * Sent just before saving the processed image object on disk
     */
    const IMAGE_POSTPROCESSING = "action.postProcessImage";

    /**
     * Sent on image cache clear request
     */
    const IMAGE_CLEAR_CACHE = "action.clearImageCache";

    /**
     * Save given images
     */
    const IMAGE_SAVE = "action.saveImages";

    /**
     * Save given images
     */
    const IMAGE_UPDATE = "action.updateImages";
    const IMAGE_UPDATE_POSITION = "action.updateImagePosition";
    const IMAGE_TOGGLE_VISIBILITY = "action.toggleImageVisibility";

    /**
     * Delete given image
     */
    const IMAGE_DELETE = "action.deleteImage";

    /**
     * Sent on document processing
     */
    const DOCUMENT_PROCESS = "action.processDocument";

    /**
     * Sent on image cache clear request
     */
    const DOCUMENT_CLEAR_CACHE = "action.clearDocumentCache";

    /**
     * Save given documents
     */
    const DOCUMENT_SAVE = "action.saveDocument";

    /**
     * Save given documents
     */
    const DOCUMENT_UPDATE = "action.updateDocument";
    const DOCUMENT_UPDATE_POSITION = "action.updateDocumentPosition";
    const DOCUMENT_TOGGLE_VISIBILITY = "action.toggleDocumentVisibility";

    /**
     * Delete given document
     */
    const DOCUMENT_DELETE = "action.deleteDocument";

    /**
     * Sent when creating a Coupon
     */
    const COUPON_CREATE = "action.create_coupon";

    /**
     * Sent when editing a Coupon
     */
    const COUPON_UPDATE = "action.update_coupon";

    /**
     * Sent when deleting a Coupon
     */
    const COUPON_DELETE = "action.delete_coupon";

    /**
     * Sent when attempting to use a Coupon
     */
    const COUPON_CONSUME    = "action.consume_coupon";

    /**
     * Sent when all coupons in the current session should be cleared
     */
    const COUPON_CLEAR_ALL    = "action.clear_all_coupon";

    /**
     * Sent when attempting to update Coupon Condition
     */
    const COUPON_CONDITION_UPDATE    = "action.update_coupon_condition";

    // -- Loop ---------------------------------------------

    const LOOP_EXTENDS_ARG_DEFINITIONS = "loop.extends.arg_definitions";
    const LOOP_EXTENDS_INITIALIZE_ARGS = "loop.extends.initialize_args";
    const LOOP_EXTENDS_BUILD_MODEL_CRITERIA = "loop.extends.build_model_criteria";
    const LOOP_EXTENDS_BUILD_ARRAY = "loop.extends.build_array";
    const LOOP_EXTENDS_PARSE_RESULTS = "loop.extends.parse_results";

    /**
     * Generate the event name for a specific loop
     *
     * @param string $eventName the event name
     * @param string|null $loopName the loop name
     *
     * @return string the event name for the loop
     */
    public static function getLoopExtendsEvent(string $eventName, string $loopName = null): string
    {
        return sprintf("%s.%s", $eventName, $loopName);
    }

    // -- Configuration management ---------------------------------------------

    const CONFIG_CREATE   = "action.createConfig";
    const CONFIG_SETVALUE = "action.setConfigValue";
    const CONFIG_UPDATE   = "action.updateConfig";
    const CONFIG_DELETE   = "action.deleteConfig";

    // -- Messages management ---------------------------------------------

    const MESSAGE_CREATE   = "action.createMessage";
    const MESSAGE_UPDATE   = "action.updateMessage";
    const MESSAGE_DELETE   = "action.deleteMessage";

    // -- Currencies management ---------------------------------------------

    const CURRENCY_CREATE          = "action.createCurrency";
    const CURRENCY_UPDATE          = "action.updateCurrency";
    const CURRENCY_DELETE          = "action.deleteCurrency";
    const CURRENCY_SET_DEFAULT     = "action.setDefaultCurrency";
    const CURRENCY_SET_VISIBLE     = "action.setVisibleCurrency";
    const CURRENCY_UPDATE_RATES    = "action.updateCurrencyRates";
    const CURRENCY_UPDATE_POSITION = "action.updateCurrencyPosition";

    const CHANGE_DEFAULT_CURRENCY = 'action.changeDefaultCurrency';

    // -- Tax management ---------------------------------------------

    const TAX_CREATE          = "action.createTax";
    const TAX_UPDATE          = "action.updateTax";
    const TAX_DELETE          = "action.deleteTax";

    // -- Profile management ---------------------------------------------

    const PROFILE_CREATE                    = "action.createProfile";
    const PROFILE_UPDATE                    = "action.updateProfile";
    const PROFILE_DELETE                    = "action.deleteProfile";
    const PROFILE_RESOURCE_ACCESS_UPDATE    = "action.updateProfileResourceAccess";
    const PROFILE_MODULE_ACCESS_UPDATE      = "action.updateProfileModuleAccess";

    // -- Administrator management ---------------------------------------------

    const ADMINISTRATOR_CREATE                    = "action.createAdministrator";
    const ADMINISTRATOR_UPDATE                    = "action.updateAdministrator";
    const ADMINISTRATOR_DELETE                    = "action.deleteAdministrator";
    const ADMINISTRATOR_UPDATEPASSWORD            = 'action.generatePassword';
    const ADMINISTRATOR_CREATEPASSWORD            = 'action.createPassword';

    // -- Mailing System management ---------------------------------------------

    const MAILING_SYSTEM_UPDATE                    = "action.updateMailingSystem";

    // -- Tax Rules management ---------------------------------------------

    const TAX_RULE_CREATE          = "action.createTaxRule";
    const TAX_RULE_UPDATE          = "action.updateTaxRule";
    const TAX_RULE_DELETE          = "action.deleteTaxRule";
    const TAX_RULE_SET_DEFAULT     = "action.setDefaultTaxRule";
    const TAX_RULE_TAXES_UPDATE     = "action.updateTaxesTaxRule";

    // -- Product templates management -----------------------------------------

    const TEMPLATE_CREATE          = "action.createTemplate";
    const TEMPLATE_UPDATE          = "action.updateTemplate";
    const TEMPLATE_DELETE          = "action.deleteTemplate";
    const TEMPLATE_DUPLICATE       = "action.duplicateTemplate";

    const TEMPLATE_ADD_ATTRIBUTE    = "action.templateAddAttribute";
    const TEMPLATE_DELETE_ATTRIBUTE = "action.templateDeleteAttribute";

    const TEMPLATE_ADD_FEATURE    = "action.templateAddFeature";
    const TEMPLATE_DELETE_FEATURE = "action.templateDeleteFeature";

    const TEMPLATE_CHANGE_FEATURE_POSITION   = "action.templateChangeAttributePosition";
    const TEMPLATE_CHANGE_ATTRIBUTE_POSITION = "action.templateChangeFeaturePosition";

    // -- Attributes management ---------------------------------------------

    const ATTRIBUTE_CREATE          = "action.createAttribute";
    const ATTRIBUTE_UPDATE          = "action.updateAttribute";
    const ATTRIBUTE_DELETE          = "action.deleteAttribute";
    const ATTRIBUTE_UPDATE_POSITION = "action.updateAttributePosition";

    const ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES = "action.addAttributeToAllTemplate";
    const ATTRIBUTE_ADD_TO_ALL_TEMPLATES      = "action.removeAttributeFromAllTemplate";

    // -- Features management ---------------------------------------------

    const FEATURE_CREATE          = "action.createFeature";
    const FEATURE_UPDATE          = "action.updateFeature";
    const FEATURE_DELETE          = "action.deleteFeature";
    const FEATURE_UPDATE_POSITION = "action.updateFeaturePosition";

    const FEATURE_REMOVE_FROM_ALL_TEMPLATES = "action.addFeatureToAllTemplate";
    const FEATURE_ADD_TO_ALL_TEMPLATES      = "action.removeFeatureFromAllTemplate";

    // -- Attributes values management ----------------------------------------

    const ATTRIBUTE_AV_CREATE          = "action.createAttributeAv";
    const ATTRIBUTE_AV_UPDATE          = "action.updateAttributeAv";
    const ATTRIBUTE_AV_DELETE          = "action.deleteAttributeAv";
    const ATTRIBUTE_AV_UPDATE_POSITION = "action.updateAttributeAvPosition";

    // -- Features values management ----------------------------------------

    const FEATURE_AV_CREATE          = "action.createFeatureAv";
    const FEATURE_AV_UPDATE          = "action.updateFeatureAv";
    const FEATURE_AV_DELETE          = "action.deleteFeatureAv";
    const FEATURE_AV_UPDATE_POSITION = "action.updateFeatureAvPosition";

    /**
     * sent when system find a mailer transporter.
     */
    const MAILTRANSPORTER_CONFIG = 'action.mailertransporter.config';

    /**
     * sent when Thelia try to generate a rewritten url
     */
    const GENERATE_REWRITTENURL = 'action.generate_rewritenurl';

    const GENERATE_PDF = 'thelia.generatePdf';

    /**
     * sent when a module is activated or deactivated
     */
    const MODULE_TOGGLE_ACTIVATION = 'thelia.module.toggleActivation';

    /**
     * sent when module position is changed
     */
    const MODULE_UPDATE_POSITION = 'thelia.module.action.updatePosition';

    /**
     * module
     */
    const MODULE_CREATE  = 'thelia.module.create';
    const MODULE_UPDATE  = 'thelia.module.update';
    const MODULE_DELETE  = 'thelia.module.delete';
    const MODULE_INSTALL = 'thelia.module.install';

    /**
     * Generate the event name for a specific module
     *
     * @param string $eventName the event name
     * @param string $moduleCode the module code
     *
     * @return string the event name for the module
     */
    public static function getModuleEvent(string $eventName, string $moduleCode): string
    {
        return sprintf("%s.%s", $eventName, strtolower($moduleCode));
    }

    /* Payment module */
    const MODULE_PAY = 'thelia.module.pay';
    const MODULE_PAYMENT_IS_VALID = 'thelia.module.payment.is_valid';
    const MODULE_PAYMENT_MANAGE_STOCK = 'thelia.module.payment.manage_stock';

    /* Delivery module */
    const MODULE_DELIVERY_GET_POSTAGE = 'thelia.module.delivery.postage';

    /**
     * Hook
     */
    const HOOK_CREATE                = 'thelia.hook.action.create';
    const HOOK_UPDATE                = 'thelia.hook.action.update';
    const HOOK_DELETE                = 'thelia.hook.action.delete';
    const HOOK_TOGGLE_NATIVE         = 'thelia.hook.action.toggleNative';
    const HOOK_TOGGLE_ACTIVATION     = 'thelia.hook.action.toggleActivation';
    const HOOK_CREATE_ALL            = 'thelia.hook.action.createAll';
    const HOOK_DEACTIVATION          = 'thelia.hook.action.deactivation';

    const MODULE_HOOK_CREATE            = 'thelia.moduleHook.action.create';
    const MODULE_HOOK_UPDATE            = 'thelia.moduleHook.action.update';
    const MODULE_HOOK_DELETE            = 'thelia.moduleHook.action.delete';
    const MODULE_HOOK_UPDATE_POSITION   = 'thelia.moduleHook.action.updatePosition';
    const MODULE_HOOK_TOGGLE_ACTIVATION = 'thelia.moduleHook.action.toggleActivation';

    /**
     * sent for clearing cache
     */
    const CACHE_CLEAR = 'thelia.cache.clear';

    /**
     * sent for subscribing to the newsletter
     */
    const NEWSLETTER_SUBSCRIBE = 'thelia.newsletter.subscribe';
    const NEWSLETTER_UPDATE = 'thelia.newsletter.update';
    const NEWSLETTER_UNSUBSCRIBE = 'thelia.newsletter.unsubscribe';
    const NEWSLETTER_CONFIRM_SUBSCRIPTION = 'thelia.newsletter.confirmSubscription';

    /**
     * sent for submit contact form
     * @since 2.4
     */
    const CONTACT_SUBMIT = 'thelia.contact.submit';

    /************ LANG MANAGEMENT ****************************/

    const LANG_UPDATE                           = 'action.lang.update';
    const LANG_CREATE                           = 'action.lang.create';
    const LANG_DELETE                           = 'action.lang.delete';

    const LANG_DEFAULTBEHAVIOR                  = 'action.lang.defaultBehavior';
    const LANG_URL                              = 'action.lang.url';

    const LANG_TOGGLEDEFAULT                    = 'action.lang.toggleDefault';
    const LANG_TOGGLEACTIVE                    = 'action.lang.toggleActive';
    const LANG_TOGGLEVISIBLE                    = 'action.lang.toggleVisible';

    // -- Brands management -----------------------------------------------

    const BRAND_CREATE = "action.createBrand";
    const BRAND_UPDATE = "action.updateBrand";
    const BRAND_DELETE = "action.deleteBrand";

    const BRAND_UPDATE_POSITION   = "action.updateBrandPosition";
    const BRAND_TOGGLE_VISIBILITY = "action.toggleBrandVisibility";

    const BRAND_UPDATE_SEO = "action.updateBrandSeo";

    const VIEW_BRAND_ID_NOT_VISIBLE = "action.viewBrandIdNotVisible";

    // -- Import ----------------------------------------------

    const IMPORT_CHANGE_POSITION = 'import.change.position';
    const IMPORT_CATEGORY_CHANGE_POSITION = 'import.category.change.position';

    const IMPORT_BEGIN = 'import.begin';
    const IMPORT_FINISHED = 'import.finished';
    const IMPORT_SUCCESS = 'import.success';

    // -- Export ----------------------------------------------

    const EXPORT_CHANGE_POSITION = 'export.change.position';
    const EXPORT_CATEGORY_CHANGE_POSITION = 'export.category.change.position';

    const EXPORT_BEGIN = 'export.begin';
    const EXPORT_FINISHED = 'export.finished';
    const EXPORT_SUCCESS = 'export.success';

    // -- Sales management -----------------------------------------------

    const SALE_CREATE = "action.createSale";
    const SALE_UPDATE = "action.updateSale";
    const SALE_DELETE = "action.deleteSale";

    const SALE_TOGGLE_ACTIVITY = "action.toggleSaleActivity";

    const SALE_CLEAR_SALE_STATUS = "action.clearSaleStatus";

    const UPDATE_PRODUCT_SALE_STATUS = "action.updateProductSaleStatus";

    const CHECK_SALE_ACTIVATION_EVENT = "action.checkSaleActivationEvent";

    // -- Meta Data ---------------------------------------------

    const META_DATA_CREATE = "thelia.metadata.create";
    const META_DATA_UPDATE = "thelia.metadata.update";
    const META_DATA_DELETE = "thelia.metadata.delete";

    // -- Form events -------------------------------------------

    const FORM_BEFORE_BUILD = "thelia.form.before_build";
    const FORM_AFTER_BUILD = "thelia.form.after_build";

    // -- Customer Title ----------------------------------------

    const CUSTOMER_TITLE_CREATE = "action.title.create";
    const CUSTOMER_TITLE_UPDATE = "action.title.update";

    const CUSTOMER_TITLE_DELETE = "action.title.delete";

    // -- Translation -------------------------------------------

    const TRANSLATION_GET_STRINGS = 'action.translation.get_strings';
    const TRANSLATION_WRITE_FILE = 'action.translation.write_file';

    // -- ORDER STATUS EVENTS -----------------------------------------------

    const ORDER_STATUS_CREATE           = "action.createOrderStatus";
    const ORDER_STATUS_UPDATE           = "action.updateOrderStatus";
    const ORDER_STATUS_DELETE           = "action.deleteOrderStatus";

    const ORDER_STATUS_UPDATE_POSITION  = "action.updateOrderStatusPosition";
    // -- END ORDER STATUS EVENTS -----------------------------------------------
}
