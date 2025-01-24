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
 * This class contains all Thelia events identifiers used by Thelia Core.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
final class TheliaEvents
{
    // -- CORE EVENTS ---------------------------------------------------------
    /**
     * sent at the beginning.
     */
    public const BOOT = 'thelia.boot';
    /**
     * Kernel View Check Handle.
     */
    public const VIEW_CHECK = 'thelia.view_check';
    // -- END CORE EVENTS ---------------------------------------------------------
    // -- ADDRESS EVENTS ---------------------------------------------------------

    /**
     * sent for address creation.
     */
    public const ADDRESS_CREATE = 'action.createAddress';
    public const ADDRESS_UPDATE = 'action.updateAddress';
    public const ADDRESS_DELETE = 'action.deleteAddress';

    /**
     * sent when an address is tag as default.
     */
    public const ADDRESS_DEFAULT = 'action.defaultAddress';

    // -- END ADDRESS EVENTS ---------------------------------------------------------

    // -- ADMIN EVENTS ---------------------------------------------------------
    /**
     * Sent before the logout of the administrator.
     */
    public const ADMIN_LOGOUT = 'action.admin_logout';
    /**
     * Sent once the administrator is successfully logged in.
     */
    public const ADMIN_LOGIN = 'action.admin_login';

    // -- END ADMIN EVENTS --------------------------------------------------------

    // -- AREA EVENTS ---------------------------------------------------------

    public const AREA_CREATE = 'action.createArea';
    public const AREA_UPDATE = 'action.updateArea';
    public const AREA_DELETE = 'action.deleteArea';

    public const AREA_REMOVE_COUNTRY = 'action.area.removeCountry';
    public const AREA_POSTAGE_UPDATE = 'action.area.postageUpdate';
    public const AREA_ADD_COUNTRY = 'action.area.addCountry';

    // -- END AREA EVENTS ---------------------------------------------------------

    // -- CATEGORIES EVENTS -----------------------------------------------

    public const CATEGORY_CREATE = 'action.createCategory';
    public const CATEGORY_UPDATE = 'action.updateCategory';
    public const CATEGORY_DELETE = 'action.deleteCategory';

    public const CATEGORY_TOGGLE_VISIBILITY = 'action.toggleCategoryVisibility';
    public const CATEGORY_UPDATE_POSITION = 'action.updateCategoryPosition';

    public const CATEGORY_ADD_CONTENT = 'action.categoryAddContent';
    public const CATEGORY_REMOVE_CONTENT = 'action.categoryRemoveContent';

    public const CATEGORY_UPDATE_SEO = 'action.updateCategorySeo';

    public const VIEW_CATEGORY_ID_NOT_VISIBLE = 'action.viewCategoryIdNotVisible';
    // -- END CATEGORIES EVENTS -----------------------------------------------

    // -- CONTENT EVENTS -----------------------------------------------

    public const CONTENT_CREATE = 'action.createContent';
    public const CONTENT_UPDATE = 'action.updateContent';
    public const CONTENT_DELETE = 'action.deleteContent';

    public const CONTENT_TOGGLE_VISIBILITY = 'action.toggleContentVisibility';
    public const CONTENT_UPDATE_POSITION = 'action.updateContentPosition';
    public const CONTENT_UPDATE_SEO = 'action.updateContentSeo';

    public const CONTENT_ADD_FOLDER = 'action.contentAddFolder';
    public const CONTENT_REMOVE_FOLDER = 'action.contentRemoveFolder';

    public const VIEW_CONTENT_ID_NOT_VISIBLE = 'action.viewContentIdNotVisible';
    // -- END CONTENT EVENTS ---------------------------------------------------------

    // -- COUNTRY EVENTS -----------------------------------------------

    public const COUNTRY_CREATE = 'action.state.create';
    public const COUNTRY_UPDATE = 'action.state.update';
    public const COUNTRY_DELETE = 'action.state.delete';

    public const COUNTRY_TOGGLE_DEFAULT = 'action.toggleCountryDefault';
    public const COUNTRY_TOGGLE_VISIBILITY = 'action.state.toggleVisibility';
    // -- END COUNTRY EVENTS ---------------------------------------------------------

    // -- STATE EVENTS -----------------------------------------------

    public const STATE_CREATE = 'action.createState';
    public const STATE_UPDATE = 'action.updateState';
    public const STATE_DELETE = 'action.deleteState';

    public const STATE_TOGGLE_VISIBILITY = 'action.toggleCountryVisibility';
    // -- END STATE EVENTS ---------------------------------------------------------

    // -- CUSTOMER EVENTS ---------------------------------------------------------
    /**
     * Sent before the logout of the customer.
     */
    public const CUSTOMER_LOGOUT = 'action.customer_logout';
    /**
     * Sent once the customer is successfully logged in.
     */
    public const CUSTOMER_LOGIN = 'action.customer_login';
    /**
     * sent on customer account creation.
     */
    public const CUSTOMER_CREATEACCOUNT = 'action.createCustomer';
    /**
     * sent on customer account update.
     */
    public const CUSTOMER_UPDATEACCOUNT = 'action.updateCustomer';
    /**
     * sent on customer account update profile.
     */
    public const CUSTOMER_UPDATEPROFILE = 'action.updateProfileCustomer';
    /**
     * sent on customer removal.
     */
    public const CUSTOMER_DELETEACCOUNT = 'action.deleteCustomer';

    /**
     * sent when a customer need a new password.
     */
    public const LOST_PASSWORD = 'action.lostPassword';

    /**
     * Send the account ccreation confirmation email.
     */
    public const SEND_ACCOUNT_CONFIRMATION_EMAIL = 'action.customer.sendAccountConfirmationEmail';

    // -- END CUSTOMER EVENTS ---------------------------------------------------------

    // -- FOLDER EVENTS -----------------------------------------------

    public const FOLDER_CREATE = 'action.createFolder';
    public const FOLDER_UPDATE = 'action.updateFolder';
    public const FOLDER_DELETE = 'action.deleteFolder';

    public const FOLDER_TOGGLE_VISIBILITY = 'action.toggleFolderVisibility';
    public const FOLDER_UPDATE_POSITION = 'action.updateFolderPosition';
    public const FOLDER_UPDATE_SEO = 'action.updateFolderSeo';

    public const VIEW_FOLDER_ID_NOT_VISIBLE = 'action.viewFolderIdNotVisible';
    // -- END FOLDER EVENTS ---------------------------------------------------------

    // -- PRODUCT EVENTS -----------------------------------------------

    public const PRODUCT_CREATE = 'action.createProduct';
    public const PRODUCT_UPDATE = 'action.updateProduct';
    public const PRODUCT_DELETE = 'action.deleteProduct';

    public const PRODUCT_TOGGLE_VISIBILITY = 'action.toggleProductVisibility';
    public const PRODUCT_UPDATE_POSITION = 'action.updateProductPosition';
    public const PRODUCT_UPDATE_SEO = 'action.updateProductSeo';

    public const PRODUCT_ADD_CONTENT = 'action.productAddContent';
    public const PRODUCT_REMOVE_CONTENT = 'action.productRemoveContent';
    public const PRODUCT_UPDATE_CONTENT_POSITION = 'action.updateProductContentPosition';

    public const PRODUCT_ADD_PRODUCT_SALE_ELEMENT = 'action.addProductSaleElement';
    public const PRODUCT_DELETE_PRODUCT_SALE_ELEMENT = 'action.deleteProductSaleElement';
    public const PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT = 'action.updateProductSaleElement';

    public const PRODUCT_COMBINATION_GENERATION = 'action.productCombinationGeneration';

    public const PRODUCT_SET_TEMPLATE = 'action.productSetTemplate';

    public const PRODUCT_ADD_ACCESSORY = 'action.productAddProductAccessory';
    public const PRODUCT_REMOVE_ACCESSORY = 'action.productRemoveProductAccessory';
    public const PRODUCT_UPDATE_ACCESSORY_POSITION = 'action.updateProductAccessoryPosition';

    public const PRODUCT_FEATURE_UPDATE_VALUE = 'action.updateProductFeatureValue';
    public const PRODUCT_FEATURE_DELETE_VALUE = 'action.deleteProductFeatureValue';

    public const PRODUCT_ADD_CATEGORY = 'action.addProductCategory';
    public const PRODUCT_REMOVE_CATEGORY = 'action.deleteProductCategory';

    public const VIRTUAL_PRODUCT_ORDER_HANDLE = 'action.virtualProduct.handleOrder';
    public const VIRTUAL_PRODUCT_ORDER_DOWNLOAD_RESPONSE = 'action.virtualProduct.downloadResponse';

    public const VIEW_PRODUCT_ID_NOT_VISIBLE = 'action.viewProductIdNotVisible';
    // -- END PRODUCT EVENTS ---------------------------------------------------------

    // -- CLONE EVENTS ------------------------------------------------------------

    public const PRODUCT_CLONE = 'action.cloneProduct';
    public const FILE_CLONE = 'action.cloneFile';
    public const PSE_CLONE = 'action.clonePSE';

    // -- END CLONE EVENTS ------------------------------------------------------------

    // -- SHIPPING ZONE MANAGEMENT

    public const SHIPPING_ZONE_ADD_AREA = 'action.shippingZone.addArea';
    public const SHIPPING_ZONE_REMOVE_AREA = 'action.shippingZone.removeArea';

    // -- END SHIPPING ZONE MANAGEMENT

    /** Persist a cart */
    public const CART_PERSIST = 'cart.persist';

    /** Restore a current cart in the session, either by reloading it from the database, or creating a new one */
    public const CART_RESTORE_CURRENT = 'cart.restore.current';

    /** Create a new, empty cart in the session, and attach it to the current customer, if any. */
    public const CART_CREATE_NEW = 'cart.create.new';

    /**
     * sent when a new existing cat id duplicated. This append when current customer is different from current cart
     * The old cart is already deleted from the database when this event is dispatched.
     */
    public const CART_DUPLICATE = 'cart.duplicate';

    /**
     * Sent when the cart is duplicated, but not yet deleted from the database.
     */
    public const CART_DUPLICATED = 'cart.duplicated';

    /**
     * Sent when a cart item is duplicated.
     */
    public const CART_ITEM_DUPLICATE = 'cart.item.duplicate';

    /**
     * sent when a new item is added to current cart.
     */
    public const AFTER_CARTADDITEM = 'cart.after.addItem';

    /**
     * sent for searching an item in the cart.
     */
    public const CART_FINDITEM = 'cart.findItem';

    /**
     * sent when a cart item is modify.
     */
    public const AFTER_CARTUPDATEITEM = 'cart.updateItem';

    /**
     * sent for addArticle action.
     */
    public const CART_ADDITEM = 'action.addArticle';

    /**
     * sent on modify article action.
     */
    public const CART_UPDATEITEM = 'action.updateArticle';
    public const CART_DELETEITEM = 'action.deleteArticle';
    public const CART_CLEAR = 'action.clear';

    /** before inserting a cart item in database */
    public const CART_ITEM_CREATE_BEFORE = 'action.cart.item.create.before';

    /** before updating a cart item in database */
    public const CART_ITEM_UPDATE_BEFORE = 'action.cart.item.update.before';

    /**
     * Order linked event.
     */
    public const ORDER_SET_DELIVERY_ADDRESS = 'action.order.setDeliveryAddress';
    public const ORDER_SET_DELIVERY_MODULE = 'action.order.setDeliveryModule';
    public const ORDER_SET_POSTAGE = 'action.order.setPostage';
    public const ORDER_SET_INVOICE_ADDRESS = 'action.order.setInvoiceAddress';
    public const ORDER_SET_PAYMENT_MODULE = 'action.order.setPaymentModule';
    public const ORDER_PAY = 'action.order.pay';
    public const ORDER_PAY_GET_TOTAL = 'action.order.pay.getTotal';
    public const ORDER_BEFORE_PAYMENT = 'action.order.beforePayment';
    public const ORDER_CART_CLEAR = 'action.order.cartClear';

    public const ORDER_CREATE_MANUAL = 'action.order.createManual';

    public const ORDER_UPDATE_STATUS = 'action.order.updateStatus';

    public const ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE = 'action.order.getStockUpdateOperationOnOrderStatusChange';

    public const ORDER_SEND_CONFIRMATION_EMAIL = 'action.order.sendOrderConfirmationEmail';

    public const ORDER_SEND_NOTIFICATION_EMAIL = 'action.order.sendOrderNotificationEmail';

    public const ORDER_UPDATE_DELIVERY_REF = 'action.order.updateDeliveryRef';
    public const ORDER_UPDATE_TRANSACTION_REF = 'action.order.updateTransactionRef';
    public const ORDER_UPDATE_ADDRESS = 'action.order.updateAddress';

    public const ORDER_PRODUCT_BEFORE_CREATE = 'action.orderProduct.beforeCreate';
    public const ORDER_PRODUCT_AFTER_CREATE = 'action.orderProduct.afterCreate';

    /**
     * Sent on image processing.
     */
    public const IMAGE_PROCESS = 'action.processImage';

    /**
     * Sent just after creating the image object from the image file.
     */
    public const IMAGE_PREPROCESSING = 'action.preProcessImage';

    /**
     * Sent just before saving the processed image object on disk.
     */
    public const IMAGE_POSTPROCESSING = 'action.postProcessImage';

    /**
     * Sent on image cache clear request.
     */
    public const IMAGE_CLEAR_CACHE = 'action.clearImageCache';

    /**
     * Save given images.
     */
    public const IMAGE_SAVE = 'action.saveImages';

    /**
     * Save given images.
     */
    public const IMAGE_UPDATE = 'action.updateImages';
    public const IMAGE_UPDATE_POSITION = 'action.updateImagePosition';
    public const IMAGE_TOGGLE_VISIBILITY = 'action.toggleImageVisibility';

    /**
     * Delete given image.
     */
    public const IMAGE_DELETE = 'action.deleteImage';

    /**
     * Sent on document processing.
     */
    public const DOCUMENT_PROCESS = 'action.processDocument';

    /**
     * Sent on image cache clear request.
     */
    public const DOCUMENT_CLEAR_CACHE = 'action.clearDocumentCache';

    /**
     * Save given documents.
     */
    public const DOCUMENT_SAVE = 'action.saveDocument';

    /**
     * Save given documents.
     */
    public const DOCUMENT_UPDATE = 'action.updateDocument';
    public const DOCUMENT_UPDATE_POSITION = 'action.updateDocumentPosition';
    public const DOCUMENT_TOGGLE_VISIBILITY = 'action.toggleDocumentVisibility';

    /**
     * Delete given document.
     */
    public const DOCUMENT_DELETE = 'action.deleteDocument';

    /**
     * Sent when creating a Coupon.
     */
    public const COUPON_CREATE = 'action.create_coupon';

    /**
     * Sent when editing a Coupon.
     */
    public const COUPON_UPDATE = 'action.update_coupon';

    /**
     * Sent when deleting a Coupon.
     */
    public const COUPON_DELETE = 'action.delete_coupon';

    /**
     * Sent when attempting to use a Coupon.
     */
    public const COUPON_CONSUME = 'action.consume_coupon';

    /**
     * Sent when all coupons in the current session should be cleared.
     */
    public const COUPON_CLEAR_ALL = 'action.clear_all_coupon';

    /**
     * Sent when attempting to update Coupon Condition.
     */
    public const COUPON_CONDITION_UPDATE = 'action.update_coupon_condition';

    // -- Loop ---------------------------------------------

    public const LOOP_EXTENDS_ARG_DEFINITIONS = 'loop.extends.arg_definitions';
    public const LOOP_EXTENDS_INITIALIZE_ARGS = 'loop.extends.initialize_args';
    public const LOOP_EXTENDS_BUILD_MODEL_CRITERIA = 'loop.extends.build_model_criteria';
    public const LOOP_EXTENDS_BUILD_ARRAY = 'loop.extends.build_array';
    public const LOOP_EXTENDS_PARSE_RESULTS = 'loop.extends.parse_results';

    /**
     * Generate the event name for a specific loop.
     *
     * @param string      $eventName the event name
     * @param string|null $loopName  the loop name
     *
     * @return string the event name for the loop
     */
    public static function getLoopExtendsEvent(string $eventName, string $loopName = null): string
    {
        return sprintf('%s.%s', $eventName, $loopName);
    }

    // -- Configuration management ---------------------------------------------

    public const CONFIG_CREATE = 'action.createConfig';
    public const CONFIG_SETVALUE = 'action.setConfigValue';
    public const CONFIG_UPDATE = 'action.updateConfig';
    public const CONFIG_DELETE = 'action.deleteConfig';

    // -- Messages management ---------------------------------------------

    public const MESSAGE_CREATE = 'action.createMessage';
    public const MESSAGE_UPDATE = 'action.updateMessage';
    public const MESSAGE_DELETE = 'action.deleteMessage';

    // -- Currencies management ---------------------------------------------

    public const CURRENCY_CREATE = 'action.createCurrency';
    public const CURRENCY_UPDATE = 'action.updateCurrency';
    public const CURRENCY_DELETE = 'action.deleteCurrency';
    public const CURRENCY_SET_DEFAULT = 'action.setDefaultCurrency';
    public const CURRENCY_SET_VISIBLE = 'action.setVisibleCurrency';
    public const CURRENCY_UPDATE_RATES = 'action.updateCurrencyRates';
    public const CURRENCY_UPDATE_POSITION = 'action.updateCurrencyPosition';

    public const CHANGE_DEFAULT_CURRENCY = 'action.changeDefaultCurrency';

    // -- Tax management ---------------------------------------------

    public const TAX_CREATE = 'action.createTax';
    public const TAX_UPDATE = 'action.updateTax';
    public const TAX_DELETE = 'action.deleteTax';
    public const TAX_GET_TYPE_SERVICE = 'action.getTaxService';

    // -- Profile management ---------------------------------------------

    public const PROFILE_CREATE = 'action.createProfile';
    public const PROFILE_UPDATE = 'action.updateProfile';
    public const PROFILE_DELETE = 'action.deleteProfile';
    public const PROFILE_RESOURCE_ACCESS_UPDATE = 'action.updateProfileResourceAccess';
    public const PROFILE_MODULE_ACCESS_UPDATE = 'action.updateProfileModuleAccess';

    // -- Administrator management ---------------------------------------------

    public const ADMINISTRATOR_CREATE = 'action.createAdministrator';
    public const ADMINISTRATOR_UPDATE = 'action.updateAdministrator';
    public const ADMINISTRATOR_DELETE = 'action.deleteAdministrator';
    public const ADMINISTRATOR_UPDATEPASSWORD = 'action.generatePassword';
    public const ADMINISTRATOR_CREATEPASSWORD = 'action.createPassword';

    // -- Mailing System management ---------------------------------------------

    public const MAILING_SYSTEM_UPDATE = 'action.updateMailingSystem';

    // -- Tax Rules management ---------------------------------------------

    public const TAX_RULE_CREATE = 'action.createTaxRule';
    public const TAX_RULE_UPDATE = 'action.updateTaxRule';
    public const TAX_RULE_DELETE = 'action.deleteTaxRule';
    public const TAX_RULE_SET_DEFAULT = 'action.setDefaultTaxRule';
    public const TAX_RULE_TAXES_UPDATE = 'action.updateTaxesTaxRule';

    // -- Product templates management -----------------------------------------

    public const TEMPLATE_CREATE = 'action.createTemplate';
    public const TEMPLATE_UPDATE = 'action.updateTemplate';
    public const TEMPLATE_DELETE = 'action.deleteTemplate';
    public const TEMPLATE_DUPLICATE = 'action.duplicateTemplate';

    public const TEMPLATE_ADD_ATTRIBUTE = 'action.templateAddAttribute';
    public const TEMPLATE_DELETE_ATTRIBUTE = 'action.templateDeleteAttribute';

    public const TEMPLATE_ADD_FEATURE = 'action.templateAddFeature';
    public const TEMPLATE_DELETE_FEATURE = 'action.templateDeleteFeature';

    public const TEMPLATE_CHANGE_FEATURE_POSITION = 'action.templateChangeAttributePosition';
    public const TEMPLATE_CHANGE_ATTRIBUTE_POSITION = 'action.templateChangeFeaturePosition';

    // -- Attributes management ---------------------------------------------

    public const ATTRIBUTE_CREATE = 'action.createAttribute';
    public const ATTRIBUTE_UPDATE = 'action.updateAttribute';
    public const ATTRIBUTE_DELETE = 'action.deleteAttribute';
    public const ATTRIBUTE_UPDATE_POSITION = 'action.updateAttributePosition';

    public const ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES = 'action.addAttributeToAllTemplate';
    public const ATTRIBUTE_ADD_TO_ALL_TEMPLATES = 'action.removeAttributeFromAllTemplate';

    // -- Features management ---------------------------------------------

    public const FEATURE_CREATE = 'action.createFeature';
    public const FEATURE_UPDATE = 'action.updateFeature';
    public const FEATURE_DELETE = 'action.deleteFeature';
    public const FEATURE_UPDATE_POSITION = 'action.updateFeaturePosition';

    public const FEATURE_REMOVE_FROM_ALL_TEMPLATES = 'action.addFeatureToAllTemplate';
    public const FEATURE_ADD_TO_ALL_TEMPLATES = 'action.removeFeatureFromAllTemplate';

    // -- Attributes values management ----------------------------------------

    public const ATTRIBUTE_AV_CREATE = 'action.createAttributeAv';
    public const ATTRIBUTE_AV_UPDATE = 'action.updateAttributeAv';
    public const ATTRIBUTE_AV_DELETE = 'action.deleteAttributeAv';
    public const ATTRIBUTE_AV_UPDATE_POSITION = 'action.updateAttributeAvPosition';

    // -- Features values management ----------------------------------------

    public const FEATURE_AV_CREATE = 'action.createFeatureAv';
    public const FEATURE_AV_UPDATE = 'action.updateFeatureAv';
    public const FEATURE_AV_DELETE = 'action.deleteFeatureAv';
    public const FEATURE_AV_UPDATE_POSITION = 'action.updateFeatureAvPosition';

    /**
     * sent when Thelia try to generate a rewritten url.
     */
    public const GENERATE_REWRITTENURL = 'action.generate_rewritenurl';

    public const GENERATE_PDF = 'thelia.generatePdf';

    /**
     * sent when a module is activated or deactivated.
     */
    public const MODULE_TOGGLE_ACTIVATION = 'thelia.module.toggleActivation';

    /**
     * sent when module position is changed.
     */
    public const MODULE_UPDATE_POSITION = 'thelia.module.action.updatePosition';

    /**
     * module.
     */
    public const MODULE_CREATE = 'thelia.module.create';
    public const MODULE_UPDATE = 'thelia.module.update';
    public const MODULE_DELETE = 'thelia.module.delete';
    public const MODULE_INSTALL = 'thelia.module.install';

    /**
     * Generate the event name for a specific module.
     *
     * @param string $eventName  the event name
     * @param string $moduleCode the module code
     *
     * @return string the event name for the module
     */
    public static function getModuleEvent(string $eventName, string $moduleCode): string
    {
        return sprintf('%s.%s', $eventName, strtolower($moduleCode));
    }

    /* Payment module */
    public const MODULE_PAY = 'thelia.module.pay';
    public const MODULE_PAYMENT_IS_VALID = 'thelia.module.payment.is_valid';
    public const MODULE_PAYMENT_MANAGE_STOCK = 'thelia.module.payment.manage_stock';

    /* Delivery module */
    public const MODULE_DELIVERY_GET_POSTAGE = 'thelia.module.delivery.postage';
    public const MODULE_DELIVERY_GET_PICKUP_LOCATIONS = 'thelia.module.delivery.pickupLocations';
    public const MODULE_DELIVERY_GET_OPTIONS = 'thelia.module.delivery.options';
    public const MODULE_PAYMENT_GET_OPTIONS = 'thelia.module.payment.options';

    /**
     * Hook.
     */
    public const HOOK_CREATE = 'thelia.hook.action.create';
    public const HOOK_UPDATE = 'thelia.hook.action.update';
    public const HOOK_DELETE = 'thelia.hook.action.delete';
    public const HOOK_TOGGLE_NATIVE = 'thelia.hook.action.toggleNative';
    public const HOOK_TOGGLE_ACTIVATION = 'thelia.hook.action.toggleActivation';
    public const HOOK_CREATE_ALL = 'thelia.hook.action.createAll';
    public const HOOK_DEACTIVATION = 'thelia.hook.action.deactivation';

    public const MODULE_HOOK_CREATE = 'thelia.moduleHook.action.create';
    public const MODULE_HOOK_UPDATE = 'thelia.moduleHook.action.update';
    public const MODULE_HOOK_DELETE = 'thelia.moduleHook.action.delete';
    public const MODULE_HOOK_UPDATE_POSITION = 'thelia.moduleHook.action.updatePosition';
    public const MODULE_HOOK_TOGGLE_ACTIVATION = 'thelia.moduleHook.action.toggleActivation';

    /**
     * sent for clearing cache.
     */
    public const CACHE_CLEAR = 'thelia.cache.clear';

    /**
     * sent for subscribing to the newsletter.
     */
    public const NEWSLETTER_SUBSCRIBE = 'thelia.newsletter.subscribe';
    public const NEWSLETTER_UPDATE = 'thelia.newsletter.update';
    public const NEWSLETTER_UNSUBSCRIBE = 'thelia.newsletter.unsubscribe';
    public const NEWSLETTER_CONFIRM_SUBSCRIPTION = 'thelia.newsletter.confirmSubscription';

    /**
     * sent for submit contact form.
     *
     * @since 2.4
     */
    public const CONTACT_SUBMIT = 'thelia.contact.submit';

    /************ LANG MANAGEMENT ****************************/

    public const LANG_UPDATE = 'action.lang.update';
    public const LANG_CREATE = 'action.lang.create';
    public const LANG_DELETE = 'action.lang.delete';

    public const LANG_DEFAULTBEHAVIOR = 'action.lang.defaultBehavior';
    public const LANG_URL = 'action.lang.url';

    public const LANG_TOGGLEDEFAULT = 'action.lang.toggleDefault';
    public const LANG_TOGGLEACTIVE = 'action.lang.toggleActive';
    public const LANG_TOGGLEVISIBLE = 'action.lang.toggleVisible';

    // -- Brands management -----------------------------------------------

    public const BRAND_CREATE = 'action.createBrand';
    public const BRAND_UPDATE = 'action.updateBrand';
    public const BRAND_DELETE = 'action.deleteBrand';

    public const BRAND_UPDATE_POSITION = 'action.updateBrandPosition';
    public const BRAND_TOGGLE_VISIBILITY = 'action.toggleBrandVisibility';

    public const BRAND_UPDATE_SEO = 'action.updateBrandSeo';

    public const VIEW_BRAND_ID_NOT_VISIBLE = 'action.viewBrandIdNotVisible';

    // -- Import ----------------------------------------------

    public const IMPORT_CHANGE_POSITION = 'import.change.position';
    public const IMPORT_CATEGORY_CHANGE_POSITION = 'import.category.change.position';

    public const IMPORT_BEGIN = 'import.begin';
    public const IMPORT_FINISHED = 'import.finished';
    public const IMPORT_SUCCESS = 'import.success';

    // -- Export ----------------------------------------------

    public const EXPORT_CHANGE_POSITION = 'export.change.position';
    public const EXPORT_CATEGORY_CHANGE_POSITION = 'export.category.change.position';

    public const EXPORT_BEGIN = 'export.begin';
    public const EXPORT_FINISHED = 'export.finished';
    public const EXPORT_SUCCESS = 'export.success';

    // -- Sales management -----------------------------------------------

    public const SALE_CREATE = 'action.createSale';
    public const SALE_UPDATE = 'action.updateSale';
    public const SALE_DELETE = 'action.deleteSale';

    public const SALE_TOGGLE_ACTIVITY = 'action.toggleSaleActivity';

    public const SALE_CLEAR_SALE_STATUS = 'action.clearSaleStatus';

    public const UPDATE_PRODUCT_SALE_STATUS = 'action.updateProductSaleStatus';

    public const CHECK_SALE_ACTIVATION_EVENT = 'action.checkSaleActivationEvent';

    // -- Meta Data ---------------------------------------------

    public const META_DATA_CREATE = 'thelia.metadata.create';
    public const META_DATA_UPDATE = 'thelia.metadata.update';
    public const META_DATA_DELETE = 'thelia.metadata.delete';

    // -- Form events -------------------------------------------

    public const FORM_BEFORE_BUILD = 'thelia.form.before_build';
    public const FORM_AFTER_BUILD = 'thelia.form.after_build';

    // -- Customer Title ----------------------------------------

    public const CUSTOMER_TITLE_CREATE = 'action.title.create';
    public const CUSTOMER_TITLE_UPDATE = 'action.title.update';

    public const CUSTOMER_TITLE_DELETE = 'action.title.delete';

    // -- Translation -------------------------------------------

    public const TRANSLATION_GET_STRINGS = 'action.translation.get_strings';
    public const TRANSLATION_WRITE_FILE = 'action.translation.write_file';

    // -- ORDER STATUS EVENTS -----------------------------------------------

    public const ORDER_STATUS_CREATE = 'action.createOrderStatus';
    public const ORDER_STATUS_UPDATE = 'action.updateOrderStatus';
    public const ORDER_STATUS_DELETE = 'action.deleteOrderStatus';

    public const ORDER_STATUS_UPDATE_POSITION = 'action.updateOrderStatusPosition';
    // -- END ORDER STATUS EVENTS -----------------------------------------------
}
