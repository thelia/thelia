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

namespace Thelia\Core\Event;

/**
 *
 * This class contains all Thelia events identifiers used by Thelia Core
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

final class TheliaEvents
{

    /**
     * sent at the beginning
     */
    const BOOT = "thelia.boot";

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
     * sent on customer address removal
     */
    const CUSTOMER_ADDRESS_DELETE = "action.customer.deleteAddress";

    /**
     * sent when a customer need a new password
     */
    const LOST_PASSWORD = "action.lostPassword";
    /**
     * Sent before the logout of the administrator.
     */
    const ADMIN_LOGOUT = "action.admin_logout";
    /**
     * Sent once the administrator is successfully logged in.
     */
    const ADMIN_LOGIN  = "action.admin_login";

    /**
     * Sent once the customer creation form has been successfully validated, and before customer insertion in the database.
     */
    const BEFORE_CREATECUSTOMER = "action.before_createcustomer";

    /**
     * Sent just after a successful insert of a new customer in the database.
     */
    const AFTER_CREATECUSTOMER 	= "action.after_createcustomer";

    /**
     * Sent once the customer change form has been successfully validated, and before customer update in the database.
     */
    const BEFORE_UPDATECUSTOMER = "action.before_updateCustomer";
    /**
     * Sent just after a successful update of a customer in the database.
     */
    const AFTER_UPDATECUSTOMER 	= "action.after_updateCustomer";

    /**
     * sent just before customer removal
     */
    const BEFORE_DELETECUSTOMER = "action.before_deleteCustomer";

    /**
     * sent just after customer removal
     */
    const AFTER_DELETECUSTOMER = "action.after_deleteCustomer";

    // -- ADDRESS MANAGEMENT ---------------------------------------------------------
    /**
     * sent for address creation
     */
    const ADDRESS_CREATE = "action.createAddress";

    /**
     * sent for address modification
     */
    const ADDRESS_UPDATE = "action.updateAddress";

    /**
     * sent on address removal
     */
    const ADDRESS_DELETE = "action.deleteAddress";

    /**
     * sent when an address is tag as default
     */
    const ADDRESS_DEFAULT = "action.defaultAddress";

    /**
     * sent once the address creation form has been successfully validated, and before address insertion in the database.
     */
    const BEFORE_CREATEADDRESS = "action.before_createAddress";

    /**
     * Sent just after a successful insert of a new address in the database.
     */
    const AFTER_CREATEADDRESS  = "action.after_createAddress";

    const BEFORE_UPDATEADDRESS = "action.before_updateAddress";
    const AFTER_UPDATEADDRESS = "action.after_updateAddress";

    const BEFORE_DELETEADDRESS = "action.before_deleteAddress";
    const AFTER_DELETEADDRESS = "action.after_deleteAddress";

    // -- END ADDRESS MANAGEMENT ---------------------------------------------------------

    // -- Categories management -----------------------------------------------

    const CATEGORY_CREATE            = "action.createCategory";
    const CATEGORY_UPDATE            = "action.updateCategory";
    const CATEGORY_DELETE            = "action.deleteCategory";
    const CATEGORY_TOGGLE_VISIBILITY = "action.toggleCategoryVisibility";
    const CATEGORY_UPDATE_POSITION   = "action.updateCategoryPosition";
    const CATEGORY_UPDATE_SEO        = "action.updateCategorySeo";

    const CATEGORY_ADD_CONTENT      = "action.categoryAddContent";
    const CATEGORY_REMOVE_CONTENT   = "action.categoryRemoveContent";

    const BEFORE_CREATECATEGORY = "action.before_createcategory";
    const AFTER_CREATECATEGORY 	= "action.after_createcategory";

    const BEFORE_DELETECATEGORY = "action.before_deletecategory";
    const AFTER_DELETECATEGORY 	= "action.after_deletecategory";

    const BEFORE_UPDATECATEGORY = "action.before_updateCategory";
    const AFTER_UPDATECATEGORY 	= "action.after_updateCategory";

    // -- folder management -----------------------------------------------

    const FOLDER_CREATE            = "action.createFolder";
    const FOLDER_UPDATE            = "action.updateFolder";
    const FOLDER_DELETE            = "action.deleteFolder";
    const FOLDER_TOGGLE_VISIBILITY = "action.toggleFolderVisibility";
    const FOLDER_UPDATE_POSITION   = "action.updateFolderPosition";
    const FOLDER_UPDATE_SEO        = "action.updateFolderSeo";

    const BEFORE_CREATEFOLDER = "action.before_createFolder";
    const AFTER_CREATEFOLDER 	= "action.after_createFolder";

    const BEFORE_DELETEFOLDER = "action.before_deleteFolder";
    const AFTER_DELETEFOLDER 	= "action.after_deleteFolder";

    const BEFORE_UPDATEFOLDER = "action.before_updateFolder";
    const AFTER_UPDATEFOLDER 	= "action.after_updateFolder";

    // -- content management -----------------------------------------------

    const CONTENT_CREATE            = "action.createContent";
    const CONTENT_UPDATE            = "action.updateContent";
    const CONTENT_DELETE            = "action.deleteContent";
    const CONTENT_TOGGLE_VISIBILITY = "action.toggleContentVisibility";
    const CONTENT_UPDATE_POSITION   = "action.updateContentPosition";
    const CONTENT_UPDATE_SEO        = "action.updateContentSeo";

    const CONTENT_ADD_FOLDER      = "action.contentAddFolder";
    const CONTENT_REMOVE_FOLDER   = "action.contentRemoveFolder";

    const BEFORE_CREATECONTENT = "action.before_createContent";
    const AFTER_CREATECONTENT	= "action.after_createContent";

    const BEFORE_DELETECONTENT = "action.before_deleteContent";
    const AFTER_DELETECONTENT 	= "action.after_deleteContent";

    const BEFORE_UPDATECONTENT = "action.before_updateContent";
    const AFTER_UPDATECONTENT 	= "action.after_updateContent";

    // -- country management -----------------------------------------------

    const COUNTRY_CREATE            = "action.createCountry";
    const COUNTRY_UPDATE            = "action.updateCountry";
    const COUNTRY_DELETE            = "action.deleteCountry";
    const COUNTRY_TOGGLE_DEFAULT = "action.toggleCountryDefault";

    const BEFORE_CREATECOUNTRY = "action.before_createCountry";
    const AFTER_CREATECOUNTRY 	= "action.after_createCountry";

    const BEFORE_DELETECOUNTRY = "action.before_deleteCountry";
    const AFTER_DELETECOUNTRY 	= "action.after_deleteCountry";

    const BEFORE_UPDATECOUNTRY = "action.before_updateCountry";
    const AFTER_UPDATECOUNTRY 	= "action.after_updateCountry";

    // -- AREA CONFIGURATION MANAGEMENT

    const AREA_CREATE = 'action.createArea';
    const AREA_UPDATE = 'action.updateArea';
    const AREA_DELETE = 'action.deleteArea';

    const AREA_ADD_COUNTRY = 'action.area.addCountry';
    const AREA_REMOVE_COUNTRY = 'action.area.removeCountry';
    const AREA_POSTAGE_UPDATE = 'action.area.postageUpdate';

    const BEFORE_CREATEAREA = 'action.before_createArea';
    const AFTER_CREATEAREA = 'action.after_createArea';

    const BEFORE_UPDATEAREA = 'action.before_updateArea';
    const AFTER_UPDATEAREA = 'action.after_updateArea';

    const BEFORE_DELETEAREA = 'action.before_deleteArea';
    const AFTER_DELETEAREA = 'action.after_deleteArea';

    // -- SHIPPING ZONE MANAGEMENT

    const SHIPPING_ZONE_ADD_AREA = 'action.shippingZone.addArea';
    const SHIPPING_ZONE_REMOVE_AREA = 'action.shippingZone.removeArea';

    // -- Categories Associated Content ----------------------------------------

    const BEFORE_CREATECATEGORY_ASSOCIATED_CONTENT = "action.before_createCategoryAssociatedContent";
    const AFTER_CREATECATEGORY_ASSOCIATED_CONTENT  = "action.after_createCategoryAssociatedContent";

    const BEFORE_DELETECATEGORY_ASSOCIATED_CONTENT = "action.before_deleteCategoryAssociatedContent";
    const AFTER_DELETECATEGORY_ASSOCIATED_CONTENT  = "action.after_deleteCategoryAssociatedContent";

    const BEFORE_UPDATECATEGORY_ASSOCIATED_CONTENT = "action.before_updateCategoryAssociatedContent";
    const AFTER_UPDATECATEGORY_ASSOCIATED_CONTENT  = "action.after_updateCategoryAssociatedContent";

    // -- Product management -----------------------------------------------

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

    const PRODUCT_COMBINATION_GENERATION = "action.productCombineationGeneration";

    const PRODUCT_SET_TEMPLATE = "action.productSetTemplate";

    const PRODUCT_ADD_ACCESSORY             = "action.productAddProductAccessory";
    const PRODUCT_REMOVE_ACCESSORY          = "action.productRemoveProductAccessory";
    const PRODUCT_UPDATE_ACCESSORY_POSITION = "action.updateProductAccessoryPosition";

    const PRODUCT_FEATURE_UPDATE_VALUE = "action.updateProductFeatureValue";
    const PRODUCT_FEATURE_DELETE_VALUE = "action.deleteProductFeatureValue";

    const PRODUCT_ADD_CATEGORY    = "action.addProductCategory";
    const PRODUCT_REMOVE_CATEGORY = "action.deleteProductCategory";

    const BEFORE_CREATEPRODUCT = "action.before_createproduct";
    const AFTER_CREATEPRODUCT  = "action.after_createproduct";

    const BEFORE_DELETEPRODUCT = "action.before_deleteproduct";
    const AFTER_DELETEPRODUCT  = "action.after_deleteproduct";

    const BEFORE_UPDATEPRODUCT = "action.before_updateProduct";
    const AFTER_UPDATEPRODUCT  = "action.after_updateProduct";

    // -- Product Accessories --------------------------------------------------

    const BEFORE_CREATEACCESSORY = "action.before_createAccessory";
    const AFTER_CREATEACCESSORY  = "action.after_createAccessory";

    const BEFORE_DELETEACCESSORY = "action.before_deleteAccessory";
    const AFTER_DELETEACCESSORY  = "action.after_deleteAccessory";

    const BEFORE_UPDATEACCESSORY = "action.before_updateAccessory";
    const AFTER_UPDATEACCESSORY  = "action.after_updateAccessory";

    // -- Product Associated Content -------------------------------------------

    const BEFORE_CREATEPRODUCT_ASSOCIATED_CONTENT   = "action.before_createProductAssociatedContent";
    const AFTER_CREATEPRODUCT_ASSOCIATED_CONTENT 	= "action.after_createProductAssociatedContent";

    const BEFORE_DELETEPRODUCT_ASSOCIATED_CONTENT   = "action.before_deleteProductAssociatedContent";
    const AFTER_DELETEPRODUCT_ASSOCIATED_CONTENT 	= "action.after_deleteProductAssociatedContent";

    const BEFORE_UPDATEPRODUCT_ASSOCIATED_CONTENT   = "action.before_updateProductAssociatedContent";
    const AFTER_UPDATEPRODUCT_ASSOCIATED_CONTENT 	= "action.after_updateProductAssociatedContent";

    // -- Feature product ------------------------------------------------------

    const BEFORE_CREATEFEATURE_PRODUCT = "action.before_createFeatureProduct";
    const AFTER_CREATEFEATURE_PRODUCT  = "action.after_createFeatureProduct";

    const BEFORE_DELETEFEATURE_PRODUCT = "action.before_deleteFeatureProduct";
    const AFTER_DELETEFEATURE_PRODUCT  = "action.after_deleteFeatureProduct";

    const BEFORE_UPDATEFEATURE_PRODUCT = "action.before_updateFeatureProduct";
    const AFTER_UPDATEFEATURE_PRODUCT  = "action.after_updateFeatureProduct";

    /**
     * sent when a new existing cat id duplicated. This append when current customer is different from current cart
     */
    const CART_DUPLICATE = "cart.duplicate";

    const CART_ITEM_DUPLICATE = "cart.item.duplicate";

    /**
     * sent when a new item is added to current cart
     */
    const AFTER_CARTADDITEM = "cart.after.addItem";

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

    /**
     * Order linked event
     */
    const ORDER_SET_DELIVERY_ADDRESS = "action.order.setDeliveryAddress";
    const ORDER_SET_DELIVERY_MODULE = "action.order.setDeliveryModule";
    const ORDER_SET_POSTAGE = "action.order.setPostage";
    const ORDER_SET_INVOICE_ADDRESS = "action.order.setInvoiceAddress";
    const ORDER_SET_PAYMENT_MODULE = "action.order.setPaymentModule";
    const ORDER_PAY = "action.order.pay";
    const ORDER_BEFORE_CREATE = "action.order.beforeCreate";
    const ORDER_AFTER_CREATE = "action.order.afterCreate";
    const ORDER_BEFORE_PAYMENT = "action.order.beforePayment";
    const ORDER_CART_CLEAR = "action.order.cartClear";

    const ORDER_CREATE_MANUAL = "action.order.createManual";

    const ORDER_UPDATE_STATUS = "action.order.updateStatus";

    const ORDER_SEND_CONFIRMATION_EMAIL = "action.order.sendOrderConfirmationEmail";

    const ORDER_SEND_NOTIFICATION_EMAIL = "action.order.sendOrderNotificationEmail";

    const ORDER_UPDATE_DELIVERY_REF = "action.order.updateDeliveryRef";
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
     * Sent when creating a Coupon
     */
    const COUPON_CREATE = "action.create_coupon";

    /**
     * Sent just before a successful insert of a new Coupon in the database.
     */
    const BEFORE_CREATE_COUPON 	= "action.before_create_coupon";

    /**
     * Sent just after a successful insert of a new Coupon in the database.
     */
    const AFTER_CREATE_COUPON 	= "action.after_create_coupon";

    /**
     * Sent when editing a Coupon
     */
    const COUPON_UPDATE = "action.update_coupon";

    /**
     * Sent just before a successful update of a new Coupon in the database.
     */
    const BEFORE_UPDATE_COUPON 	= "action.before_update_coupon";

    /**
     * Sent just after a successful update of a new Coupon in the database.
     */
    const AFTER_UPDATE_COUPON 	= "action.after_update_coupon";

    /**
     * Sent when attempting to use a Coupon
     */
    const COUPON_CONSUME 	= "action.consume_coupon";

    /**
     * Sent when all coupons in the current session should be cleared
     */
    const COUPON_CLEAR_ALL 	= "action.clear_all_coupon";

    /**
     * Sent just before an attempt to use a Coupon
     */
    const BEFORE_CONSUME_COUPON 	= "action.before_consume_coupon";

    /**
     * Sent just after an attempt to use a Coupon
     */
    const AFTER_CONSUME_COUPON 	= "action.after_consume_coupon";

    /**
     * Sent when attempting to update Coupon Condition
     */
    const COUPON_CONDITION_UPDATE 	= "action.update_coupon_condition";

    /**
     * Sent just before an attempt to update a Coupon Condition
     */
    const BEFORE_COUPON_CONDITION_UPDATE 	= "action.before_update_coupon_condition";

    /**
     * Sent just after an attempt to update a Coupon Condition
     */
    const AFTER_COUPON_CONDITION_UPDATE 	= "action.after_update_coupon_condition";

    // -- Configuration management ---------------------------------------------

    const CONFIG_CREATE   = "action.createConfig";
    const CONFIG_SETVALUE = "action.setConfigValue";
    const CONFIG_UPDATE   = "action.updateConfig";
    const CONFIG_DELETE   = "action.deleteConfig";

    const BEFORE_CREATECONFIG = "action.before_createConfig";
    const AFTER_CREATECONFIG  = "action.after_createConfig";

    const BEFORE_UPDATECONFIG = "action.before_updateConfig";
    const AFTER_UPDATECONFIG  = "action.after_updateConfig";

    const BEFORE_DELETECONFIG = "action.before_deleteConfig";
    const AFTER_DELETECONFIG  = "action.after_deleteConfig";

    // -- Messages management ---------------------------------------------

    const MESSAGE_CREATE   = "action.createMessage";
    const MESSAGE_UPDATE   = "action.updateMessage";
    const MESSAGE_DELETE   = "action.deleteMessage";

    const BEFORE_CREATEMESSAGE = "action.before_createMessage";
    const AFTER_CREATEMESSAGE  = "action.after_createMessage";

    const BEFORE_UPDATEMESSAGE = "action.before_updateMessage";
    const AFTER_UPDATEMESSAGE  = "action.after_updateMessage";

    const BEFORE_DELETEMESSAGE = "action.before_deleteMessage";
    const AFTER_DELETEMESSAGE  = "action.after_deleteMessage";

    // -- Currencies management ---------------------------------------------

    const CURRENCY_CREATE          = "action.createCurrency";
    const CURRENCY_UPDATE          = "action.updateCurrency";
    const CURRENCY_DELETE          = "action.deleteCurrency";
    const CURRENCY_SET_DEFAULT     = "action.setDefaultCurrency";
    const CURRENCY_UPDATE_RATES    = "action.updateCurrencyRates";
    const CURRENCY_UPDATE_POSITION = "action.updateCurrencyPosition";

    const BEFORE_CREATECURRENCY = "action.before_createCurrency";
    const AFTER_CREATECURRENCY  = "action.after_createCurrency";

    const BEFORE_UPDATECURRENCY = "action.before_updateCurrency";
    const AFTER_UPDATECURRENCY  = "action.after_updateCurrency";

    const BEFORE_DELETECURRENCY = "action.before_deleteCurrency";
    const AFTER_DELETECURRENCY  = "action.after_deleteCurrency";

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
    const ADMINISTRATOR_UPDATEPASSWORD          = 'action.generatePassword';

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

    const TEMPLATE_ADD_ATTRIBUTE    = "action.templateAddAttribute";
    const TEMPLATE_DELETE_ATTRIBUTE = "action.templateDeleteAttribute";

    const TEMPLATE_ADD_FEATURE    = "action.templateAddFeature";
    const TEMPLATE_DELETE_FEATURE = "action.templateDeleteFeature";

    const TEMPLATE_CHANGE_FEATURE_POSITION   = "action.templateChangeAttributePosition";
    const TEMPLATE_CHANGE_ATTRIBUTE_POSITION = "action.templateChangeFeaturePosition";

    const BEFORE_CREATETEMPLATE = "action.before_createTemplate";
    const AFTER_CREATETEMPLATE  = "action.after_createTemplate";

    const BEFORE_UPDATETEMPLATE = "action.before_updateTemplate";
    const AFTER_UPDATETEMPLATE  = "action.after_updateTemplate";

    const BEFORE_DELETETEMPLATE = "action.before_deleteTemplate";
    const AFTER_DELETETEMPLATE  = "action.after_deleteTemplate";

    // -- Attributes management ---------------------------------------------

    const ATTRIBUTE_CREATE          = "action.createAttribute";
    const ATTRIBUTE_UPDATE          = "action.updateAttribute";
    const ATTRIBUTE_DELETE          = "action.deleteAttribute";
    const ATTRIBUTE_UPDATE_POSITION = "action.updateAttributePosition";

    const ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES = "action.addAttributeToAllTemplate";
    const ATTRIBUTE_ADD_TO_ALL_TEMPLATES      = "action.removeAttributeFromAllTemplate";

    const BEFORE_CREATEATTRIBUTE = "action.before_createAttribute";
    const AFTER_CREATEATTRIBUTE  = "action.after_createAttribute";

    const BEFORE_UPDATEATTRIBUTE = "action.before_updateAttribute";
    const AFTER_UPDATEATTRIBUTE  = "action.after_updateAttribute";

    const BEFORE_DELETEATTRIBUTE = "action.before_deleteAttribute";
    const AFTER_DELETEATTRIBUTE  = "action.after_deleteAttribute";

    // -- Features management ---------------------------------------------

    const FEATURE_CREATE          = "action.createFeature";
    const FEATURE_UPDATE          = "action.updateFeature";
    const FEATURE_DELETE          = "action.deleteFeature";
    const FEATURE_UPDATE_POSITION = "action.updateFeaturePosition";

    const FEATURE_REMOVE_FROM_ALL_TEMPLATES = "action.addFeatureToAllTemplate";
    const FEATURE_ADD_TO_ALL_TEMPLATES      = "action.removeFeatureFromAllTemplate";

    const BEFORE_CREATEFEATURE = "action.before_createFeature";
    const AFTER_CREATEFEATURE  = "action.after_createFeature";

    const BEFORE_UPDATEFEATURE = "action.before_updateFeature";
    const AFTER_UPDATEFEATURE  = "action.after_updateFeature";

    const BEFORE_DELETEFEATURE = "action.before_deleteFeature";
    const AFTER_DELETEFEATURE  = "action.after_deleteFeature";

    // -- Attributes values management ----------------------------------------

    const ATTRIBUTE_AV_CREATE          = "action.createAttributeAv";
    const ATTRIBUTE_AV_UPDATE          = "action.updateAttributeAv";
    const ATTRIBUTE_AV_DELETE          = "action.deleteAttributeAv";
    const ATTRIBUTE_AV_UPDATE_POSITION = "action.updateAttributeAvPosition";

    const BEFORE_CREATEATTRIBUTE_AV = "action.before_createAttributeAv";
    const AFTER_CREATEATTRIBUTE_AV  = "action.after_createAttributeAv";

    const BEFORE_UPDATEATTRIBUTE_AV = "action.before_updateAttributeAv";
    const AFTER_UPDATEATTRIBUTE_AV  = "action.after_updateAttributeAv";

    const BEFORE_DELETEATTRIBUTE_AV = "action.before_deleteAttributeAv";
    const AFTER_DELETEATTRIBUTE_AV  = "action.after_deleteAttributeAv";

    // -- Features values management ----------------------------------------

    const FEATURE_AV_CREATE          = "action.createFeatureAv";
    const FEATURE_AV_UPDATE          = "action.updateFeatureAv";
    const FEATURE_AV_DELETE          = "action.deleteFeatureAv";
    const FEATURE_AV_UPDATE_POSITION = "action.updateFeatureAvPosition";

    const BEFORE_CREATEFEATURE_AV = "action.before_createFeatureAv";
    const AFTER_CREATEFEATURE_AV  = "action.after_createFeatureAv";

    const BEFORE_UPDATEFEATURE_AV = "action.before_updateFeatureAv";
    const AFTER_UPDATEFEATURE_AV  = "action.after_updateFeatureAv";

    const BEFORE_DELETEFEATURE_AV = "action.before_deleteFeatureAv";
    const AFTER_DELETEFEATURE_AV  = "action.after_deleteFeatureAv";

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
    const MODULE_CREATE = 'thelia.module.create';
    const MODULE_UPDATE = 'thelia.module.update';
    const MODULE_DELETE = 'thelia.module.delete';

    /* Invoke payment module */

    const MODULE_PAY = 'thelia.module.pay';

    /**
     * Hook
     */
    const BEFORE_HOOK_RENDER    = 'thelia.hook.beforeRender';
    const HOOK_PROCESS_RENDER   = 'thelia.hook.processRender';
    const AFTER_HOOK_RENDER     = 'thelia.hook.afterRender';

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

    /************ LANG MANAGEMENT ****************************/

    const LANG_UPDATE                           = 'action.lang.update';
    const LANG_CREATE                           = 'action.lang.create';
    const LANG_DELETE                           = 'action.lang.delete';

    const LANG_DEFAULTBEHAVIOR                  = 'action.lang.defaultBehavior';
    const LANG_URL                              = 'action.lang.url';

    const LANG_TOGGLEDEFAULT                    = 'action.lang.toggleDefault';

    const BEFORE_UPDATELANG                     = 'action.lang.beforeUpdate';
    const AFTER_UPDATELANG                      = 'action.lang.afterUpdate';

    const BEFORE_CREATELANG                     = 'action.lang.beforeCreate';
    const AFTER_CREATELANG                      = 'action.lang.afterCreate';

    const BEFORE_DELETELANG                     = 'action.lang.beforeDelete';
    const AFTER_DELETELANG                      = 'action.lang.afterDelete';

    // -- Brands management -----------------------------------------------

    const BRAND_CREATE = "action.createBrand";
    const BRAND_UPDATE = "action.updateBrand";
    const BRAND_DELETE = "action.deleteBrand";

    const BRAND_UPDATE_POSITION   = "action.updateBrandPosition";
    const BRAND_TOGGLE_VISIBILITY = "action.toggleBrandVisibility";

    const BRAND_UPDATE_SEO = "action.updateBrandSeo";

    const BEFORE_CREATEBRAND = "action.before_createBrand";
    const AFTER_CREATEBRAND	 = "action.after_createBrand";

    const BEFORE_DELETEBRAND = "action.before_deleteBrand";
    const AFTER_DELETEBRAND  = "action.after_deleteBrand";

    const BEFORE_UPDATEBRAND = "action.before_updateBrand";
    const AFTER_UPDATEBRAND  = "action.after_updateBrand";

    // -- Export ----------------------------------------------

    const EXPORT_BEFORE_ENCODE = "Thelia.export.encode.before";
    const EXPORT_AFTER_ENCODE = "Thelia.export.encode.after";

    const EXPORT_CATEGORY_CHANGE_POSITION = "Thelia.export.change_category_position";
    const EXPORT_CHANGE_POSITION = "Thelia.export.change_position";

    const IMPORT_BEFORE_DECODE = "Thelia.import.decode.before";
    const IMPORT_AFTER_DECODE = "Thelia.import.decode.after";

    const IMPORT_CATEGORY_CHANGE_POSITION = "Thelia.import.change_category_position";
    const IMPORT_CHANGE_POSITION = "Thelia.import.change_position";

    // -- Sales management -----------------------------------------------

    const SALE_CREATE = "action.createSale";
    const SALE_UPDATE = "action.updateSale";
    const SALE_DELETE = "action.deleteSale";

    const SALE_TOGGLE_ACTIVITY = "action.toggleSaleActivity";

    const SALE_CLEAR_SALE_STATUS = "action.clearSaleStatus";

    const UPDATE_PRODUCT_SALE_STATUS = "action.updateProductSaleStatus";

    const CHECK_SALE_ACTIVATION_EVENT = "action.checkSaleActivationEvent";

    const BEFORE_CREATESALE = "action.before_createSale";
    const AFTER_CREATESALE	= "action.after_createSale";

    const BEFORE_DELETESALE = "action.before_deleteSale";
    const AFTER_DELETESALE  = "action.after_deleteSale";

    const BEFORE_UPDATESALE = "action.before_updateSale";
    const AFTER_UPDATESALE  = "action.after_updateSale";

    // -- Meta Data ---------------------------------------------

    const META_DATA_CREATE = "thelia.metadata.create";
    const META_DATA_UPDATE = "thelia.metadata.update";
    const META_DATA_DELETE = "thelia.metadata.delete";

}
