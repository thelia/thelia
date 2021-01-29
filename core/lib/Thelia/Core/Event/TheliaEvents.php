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

    /** @deprecated since 2.4, \Thelia\Model\Event\AddressEvent::PRE_INSERT */
    const BEFORE_CREATEADDRESS = "action.before_createAddress";
    /** @deprecated since 2.4, \Thelia\Model\Event\AddressEvent::POST_INSERT */
    const AFTER_CREATEADDRESS  = "action.after_createAddress";

    /** @deprecated since 2.4, \Thelia\Model\Event\AddressEvent::PRE_DELETE */
    const BEFORE_DELETEADDRESS = "action.before_deleteAddress";
    /** @deprecated since 2.4, \Thelia\Model\Event\AddressEvent::POST_DELETE */
    const AFTER_DELETEADDRESS  = "action.after_deleteAddress";

    /** @deprecated since 2.4, \Thelia\Model\Event\AddressEvent::PRE_UPDATE */
    const BEFORE_UPDATEADDRESS = "action.before_updateAddress";
    /** @deprecated since 2.4, \Thelia\Model\Event\AddressEvent::POST_UPDATE */
    const AFTER_UPDATEADDRESS  = "action.after_updateAddress";
    
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

    const ADMIN_PASSWORD_RENEW  = "action.admin_renew_password";

    // -- END ADMIN EVENTS --------------------------------------------------------


    // -- AREA EVENTS ---------------------------------------------------------

    const AREA_CREATE = 'action.createArea';
    const AREA_UPDATE = 'action.updateArea';
    const AREA_DELETE = 'action.deleteArea';

    const AREA_REMOVE_COUNTRY = 'action.area.removeCountry';
    const AREA_POSTAGE_UPDATE = 'action.area.postageUpdate';
    const AREA_ADD_COUNTRY = 'action.area.addCountry';

    /** @deprecated since 2.4, \Thelia\Model\Event\AreaEvent::PRE_INSERT */
    const BEFORE_CREATEAREA = "action.before_createArea";
    /** @deprecated since 2.4, \Thelia\Model\Event\AreaEvent::POST_INSERT */
    const AFTER_CREATEAREA  = "action.after_createArea";

    /** @deprecated since 2.4, \Thelia\Model\Event\AreaEvent::PRE_DELETE */
    const BEFORE_DELETEAREA = "action.before_deleteArea";
    /** @deprecated since 2.4, \Thelia\Model\Event\AreaEvent::POST_DELETE */
    const AFTER_DELETEAREA  = "action.after_deleteArea";

    /** @deprecated since 2.4, \Thelia\Model\Event\AreaEvent::PRE_UPDATE */
    const BEFORE_UPDATEAREA = "action.before_updateArea";
    /** @deprecated since 2.4, \Thelia\Model\Event\AreaEvent::POST_UPDATE */
    const AFTER_UPDATEAREA  = "action.after_updateArea";
    
    // -- END AREA EVENTS ---------------------------------------------------------


    // -- CATEGORIES EVENTS -----------------------------------------------

    const CATEGORY_CREATE            = "action.createCategory";
    const CATEGORY_UPDATE            = "action.updateCategory";
    const CATEGORY_DELETE            = "action.deleteCategory";

    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryEvent::PRE_INSERT */
    const BEFORE_CREATECATEGORY = "action.before_createCategory";
    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryEvent::POST_INSERT */
    const AFTER_CREATECATEGORY  = "action.after_createCategory";

    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryEvent::PRE_DELETE */
    const BEFORE_DELETECATEGORY = "action.before_deleteCategory";
    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryEvent::POST_DELETE */
    const AFTER_DELETECATEGORY  = "action.after_deleteCategory";

    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryEvent::PRE_UPDATE */
    const BEFORE_UPDATECATEGORY = "action.before_updateCategory";
    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryEvent::POST_UPDATE */
    const AFTER_UPDATECATEGORY  = "action.after_updateCategory";

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

    /** @deprecated since 2.4, \Thelia\Model\Event\ContentEvent::PRE_INSERT */
    const BEFORE_CREATECONTENT = "action.before_createContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\ContentEvent::POST_INSERT */
    const AFTER_CREATECONTENT  = "action.after_createContent";

    /** @deprecated since 2.4, \Thelia\Model\Event\ContentEvent::PRE_DELETE */
    const BEFORE_DELETECONTENT = "action.before_deleteContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\ContentEvent::POST_DELETE */
    const AFTER_DELETECONTENT  = "action.after_deleteContent";

    /** @deprecated since 2.4, \Thelia\Model\Event\ContentEvent::PRE_UPDATE */
    const BEFORE_UPDATECONTENT = "action.before_updateContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\ContentEvent::POST_UPDATE */
    const AFTER_UPDATECONTENT  = "action.after_updateContent";
    
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

    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::PRE_INSERT */
    const BEFORE_CREATECOUNTRY = "action.before_createCountry";
    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::POST_INSERT */
    const AFTER_CREATECOUNTRY  = "action.after_createCountry";

    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::PRE_DELETE */
    const BEFORE_DELETECOUNTRY = "action.before_deleteCountry";
    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::POST_DELETE */
    const AFTER_DELETECOUNTRY  = "action.after_deleteCountry";

    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::PRE_UPDATE */
    const BEFORE_UPDATECOUNTRY = "action.before_updateCountry";
    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::POST_UPDATE */
    const AFTER_UPDATECOUNTRY  = "action.after_updateCountry";
    
    const COUNTRY_TOGGLE_DEFAULT = "action.toggleCountryDefault";
    const COUNTRY_TOGGLE_VISIBILITY = "action.state.toggleVisibility";
    // -- END COUNTRY EVENTS ---------------------------------------------------------


    // -- STATE EVENTS -----------------------------------------------

    const STATE_CREATE            = "action.createState";
    const STATE_UPDATE            = "action.updateState";
    const STATE_DELETE            = "action.deleteState";

    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::PRE_INSERT */
    const BEFORE_CREATESTATE = "action.before_createCountry";
    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::POST_INSERT */
    const AFTER_CREATESTATE  = "action.after_createCountry";

    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::PRE_DELETE */
    const BEFORE_DELETESTATE = "action.before_deleteCountry";
    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::POST_DELETE */
    const AFTER_DELETESTATE  = "action.after_deleteCountry";

    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::PRE_UPDATE */
    const BEFORE_UPDATESTATE = "action.before_updateCountry";
    /** @deprecated since 2.4, \Thelia\Model\Event\CountryEvent::POST_UPDATE */
    const AFTER_UPDATESTATE  = "action.after_updateCountry";
    
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
     * sent on customer address removal
     */
    const CUSTOMER_ADDRESS_DELETE = "action.customer.deleteAddress";
    /**
     * sent when a customer need a new password
     */
    const LOST_PASSWORD = "action.lostPassword";

    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerEvent::PRE_INSERT */
    const BEFORE_CREATECUSTOMER = "action.before_createCustomer";
    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerEvent::POST_INSERT */
    const AFTER_CREATECUSTOMER  = "action.after_createCustomer";

    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerEvent::PRE_DELETE */
    const BEFORE_DELETECUSTOMER = "action.before_deleteCustomer";
    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerEvent::POST_DELETE */
    const AFTER_DELETECUSTOMER  = "action.after_deleteCustomer";

    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerEvent::PRE_UPDATE */
    const BEFORE_UPDATECUSTOMER = "action.before_updateCustomer";
    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerEvent::POST_UPDATE */
    const AFTER_UPDATECUSTOMER  = "action.after_updateCustomer";
    
    /**
     * Send the account ccreation confirmation email
     */
    const SEND_ACCOUNT_CONFIRMATION_EMAIL = "action.customer.sendAccountConfirmationEmail";

    // -- END CUSTOMER EVENTS ---------------------------------------------------------


    // -- FOLDER EVENTS -----------------------------------------------


    const FOLDER_CREATE            = "action.createFolder";
    const FOLDER_UPDATE            = "action.updateFolder";
    const FOLDER_DELETE            = "action.deleteFolder";

    /** @deprecated since 2.4, \Thelia\Model\Event\FolderEvent::PRE_INSERT */
    const BEFORE_CREATEFOLDER = "action.before_createFolder";
    /** @deprecated since 2.4, \Thelia\Model\Event\FolderEvent::POST_INSERT */
    const AFTER_CREATEFOLDER  = "action.after_createFolder";

    /** @deprecated since 2.4, \Thelia\Model\Event\FolderEvent::PRE_DELETE */
    const BEFORE_DELETEFOLDER = "action.before_deleteFolder";
    /** @deprecated since 2.4, \Thelia\Model\Event\FolderEvent::POST_DELETE */
    const AFTER_DELETEFOLDER  = "action.after_deleteFolder";

    /** @deprecated since 2.4, \Thelia\Model\Event\FolderEvent::PRE_UPDATE */
    const BEFORE_UPDATEFOLDER = "action.before_updateFolder";
    /** @deprecated since 2.4, \Thelia\Model\Event\FolderEvent::POST_UPDATE */
    const AFTER_UPDATEFOLDER  = "action.after_updateFolder";
    
    const FOLDER_TOGGLE_VISIBILITY = "action.toggleFolderVisibility";
    const FOLDER_UPDATE_POSITION   = "action.updateFolderPosition";
    const FOLDER_UPDATE_SEO        = "action.updateFolderSeo";

    const VIEW_FOLDER_ID_NOT_VISIBLE = "action.viewFolderIdNotVisible";
    // -- END FOLDER EVENTS ---------------------------------------------------------


    // -- PRODUCT EVENTS -----------------------------------------------

    const PRODUCT_CREATE            = "action.createProduct";
    const PRODUCT_UPDATE            = "action.updateProduct";
    const PRODUCT_DELETE            = "action.deleteProduct";

    /** @deprecated since 2.4, \Thelia\Model\Event\ProductEvent::PRE_INSERT */
    const BEFORE_CREATEPRODUCT = "action.before_createproduct";
    /** @deprecated since 2.4, \Thelia\Model\Event\ProductEvent::POST_INSERT */
    const AFTER_CREATEPRODUCT  = "action.after_createproduct";

    /** @deprecated since 2.4, \Thelia\Model\Event\ProductEvent::PRE_DELETE */
    const BEFORE_DELETEPRODUCT = "action.before_deleteProduct";
    /** @deprecated since 2.4, \Thelia\Model\Event\ProductEvent::POST_DELETE */
    const AFTER_DELETEPRODUCT  = "action.after_deleteProduct";

    /** @deprecated since 2.4, \Thelia\Model\Event\ProductEvent::PRE_UPDATE */
    const BEFORE_UPDATEPRODUCT = "action.before_deleteproduct";
    /** @deprecated since 2.4, \Thelia\Model\Event\ProductEvent::POST_UPDATE */
    const AFTER_UPDATEPRODUCT  = "action.after_deleteproduct";
    
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



    // -- Categories Associated Content ----------------------------------------

    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryAssociatedContentEvent::PRE_INSERT */
    const BEFORE_CREATECATEGORY_ASSOCIATED_CONTENT = "action.before_createCategoryAssociatedContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryAssociatedContentEvent::POST_INSERT */
    const AFTER_CREATECATEGORY_ASSOCIATED_CONTENT  = "action.after_createCategoryAssociatedContent";

    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryAssociatedContentEvent::PRE_DELETE */
    const BEFORE_DELETECATEGORY_ASSOCIATED_CONTENT = "action.before_deleteCategoryAssociatedContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryAssociatedContentEvent::POST_DELETE */
    const AFTER_DELETECATEGORY_ASSOCIATED_CONTENT  = "action.after_deleteCategoryAssociatedContent";

    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryAssociatedContentEvent::PRE_UPDATE */
    const BEFORE_UPDATECATEGORY_ASSOCIATED_CONTENT = "action.before_updateCategoryAssociatedContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\CategoryAssociatedContentEvent::POST_UPDATE */
    const AFTER_UPDATECATEGORY_ASSOCIATED_CONTENT  = "action.after_updateCategoryAssociatedContent";
    
    // -- Product Accessories --------------------------------------------------

    /** @deprecated since 2.4, \Thelia\Model\Event\AccessoryEvent::PRE_INSERT */
    const BEFORE_CREATEACCESSORY = "action.before_createAccessory";
    /** @deprecated since 2.4, \Thelia\Model\Event\AccessoryEvent::POST_INSERT */
    const AFTER_CREATEACCESSORY  = "action.after_createAccessory";

    /** @deprecated since 2.4, \Thelia\Model\Event\AccessoryEvent::PRE_DELETE */
    const BEFORE_DELETEACCESSORY = "action.before_deleteAccessory";
    /** @deprecated since 2.4, \Thelia\Model\Event\AccessoryEvent::POST_DELETE */
    const AFTER_DELETEACCESSORY  = "action.after_deleteAccessory";

    /** @deprecated since 2.4, \Thelia\Model\Event\AccessoryEvent::PRE_UPDATE */
    const BEFORE_UPDATEACCESSORY = "action.before_updateAccessory";
    /** @deprecated since 2.4, \Thelia\Model\Event\AccessoryEvent::POST_UPDATE */
    const AFTER_UPDATEACCESSORY  = "action.after_updateAccessory";

    // -- Product Associated Content -------------------------------------------

    /** @deprecated since 2.4, \Thelia\Model\Event\ProductAssociatedContentEvent::PRE_INSERT */
    const BEFORE_CREATEPRODUCT_ASSOCIATED_CONTENT = "action.before_createProductAssociatedContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\ProductAssociatedContentEvent::POST_INSERT */
    const AFTER_CREATEPRODUCT_ASSOCIATED_CONTENT  = "action.after_createProductAssociatedContent";

    /** @deprecated since 2.4, \Thelia\Model\Event\ProductAssociatedContentEvent::PRE_DELETE */
    const BEFORE_DELETEPRODUCT_ASSOCIATED_CONTENT = "action.before_deleteProductAssociatedContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\ProductAssociatedContentEvent::POST_DELETE */
    const AFTER_DELETEPRODUCT_ASSOCIATED_CONTENT  = "action.after_deleteProductAssociatedContent";

    /** @deprecated since 2.4, \Thelia\Model\Event\ProductAssociatedContentEvent::PRE_UPDATE */
    const BEFORE_UPDATEPRODUCT_ASSOCIATED_CONTENT = "action.before_updateProductAssociatedContent";
    /** @deprecated since 2.4, \Thelia\Model\Event\ProductAssociatedContentEvent::POST_UPDATE */
    const AFTER_UPDATEPRODUCT_ASSOCIATED_CONTENT  = "action.after_updateProductAssociatedContent";
    
    // -- Feature product ------------------------------------------------------
    
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureProductEvent::PRE_INSERT */
    const BEFORE_CREATEFEATURE_PRODUCT = "action.before_createFeatureProduct";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureProductEvent::POST_INSERT */
    const AFTER_CREATEFEATURE_PRODUCT  = "action.after_createFeatureProduct";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureProductEvent::PRE_DELETE */
    const BEFORE_DELETEFEATURE_PRODUCT = "action.before_deleteFeatureProduct";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureProductEvent::POST_DELETE */
    const AFTER_DELETEFEATURE_PRODUCT  = "action.after_deleteFeatureProduct";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureProductEvent::PRE_UPDATE */
    const BEFORE_UPDATEFEATURE_PRODUCT = "action.before_updateFeatureProduct";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureProductEvent::POST_UPDATE */
    const AFTER_UPDATEFEATURE_PRODUCT  = "action.after_updateFeatureProduct";

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
    /** @deprecated since 2.4, \Thelia\Model\Event\OrderEvent::PRE_INSERT */
    const ORDER_BEFORE_CREATE = "action.order.beforeCreate";
    /** @deprecated since 2.4, \Thelia\Model\Event\OrderEvent::POST_INSERT */
    const ORDER_AFTER_CREATE = "action.order.afterCreate";
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
     * Sent just before a successful insert of a new Coupon in the database.
     */
    const BEFORE_CREATE_COUPON    = "action.before_create_coupon";

    /**
     * Sent just after a successful insert of a new Coupon in the database.
     */
    const AFTER_CREATE_COUPON    = "action.after_create_coupon";

    /**
     * Sent when editing a Coupon
     */
    const COUPON_UPDATE = "action.update_coupon";

    /**
     * Sent just before a successful update of a new Coupon in the database.
     */
    const BEFORE_UPDATE_COUPON    = "action.before_update_coupon";

    /**
     * Sent just after a successful update of a new Coupon in the database.
     */
    const AFTER_UPDATE_COUPON    = "action.after_update_coupon";

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
     * Sent just before an attempt to use a Coupon
     */
    const BEFORE_CONSUME_COUPON    = "action.before_consume_coupon";

    /**
     * Sent just after an attempt to use a Coupon
     */
    const AFTER_CONSUME_COUPON    = "action.after_consume_coupon";

    /**
     * Sent when attempting to update Coupon Condition
     */
    const COUPON_CONDITION_UPDATE    = "action.update_coupon_condition";

    /**
     * Sent just before an attempt to update a Coupon Condition
     */
    const BEFORE_COUPON_CONDITION_UPDATE    = "action.before_update_coupon_condition";

    /**
     * Sent just after an attempt to update a Coupon Condition
     */
    const AFTER_COUPON_CONDITION_UPDATE    = "action.after_update_coupon_condition";

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
     * @param string $loopName the loop name
     *
     * @return string the event name for the loop
     */
    public static function getLoopExtendsEvent($eventName, $loopName)
    {
        return sprintf("%s.%s", $eventName, $loopName);
    }

    // -- Configuration management ---------------------------------------------

    const CONFIG_CREATE   = "action.createConfig";
    const CONFIG_SETVALUE = "action.setConfigValue";
    const CONFIG_UPDATE   = "action.updateConfig";
    const CONFIG_DELETE   = "action.deleteConfig";

    /** @deprecated since 2.4, \Thelia\Model\Event\ConfigEvent::PRE_INSERT */
    const BEFORE_CREATECONFIG = "action.before_createConfig";
    /** @deprecated since 2.4, \Thelia\Model\Event\ConfigEvent::POST_INSERT */
    const AFTER_CREATECONFIG  = "action.after_createConfig";

    /** @deprecated since 2.4, \Thelia\Model\Event\ConfigEvent::PRE_DELETE */
    const BEFORE_DELETECONFIG = "action.before_deleteConfig";
    /** @deprecated since 2.4, \Thelia\Model\Event\ConfigEvent::POST_DELETE */
    const AFTER_DELETECONFIG  = "action.after_deleteConfig";

    /** @deprecated since 2.4, \Thelia\Model\Event\ConfigEvent::PRE_UPDATE */
    const BEFORE_UPDATECONFIG = "action.before_updateConfig";
    /** @deprecated since 2.4, \Thelia\Model\Event\ConfigEvent::POST_UPDATE */
    const AFTER_UPDATECONFIG  = "action.after_updateConfig";
    
    // -- Messages management ---------------------------------------------

    const MESSAGE_CREATE   = "action.createMessage";
    const MESSAGE_UPDATE   = "action.updateMessage";
    const MESSAGE_DELETE   = "action.deleteMessage";

    /** @deprecated since 2.4, \Thelia\Model\Event\MessageEvent::PRE_INSERT */
    const BEFORE_CREATEMESSAGE = "action.before_createMessage";
    /** @deprecated since 2.4, \Thelia\Model\Event\MessageEvent::POST_INSERT */
    const AFTER_CREATEMESSAGE  = "action.after_createMessage";

    /** @deprecated since 2.4, \Thelia\Model\Event\MessageEvent::PRE_DELETE */
    const BEFORE_DELETEMESSAGE = "action.before_deleteMessage";
    /** @deprecated since 2.4, \Thelia\Model\Event\MessageEvent::POST_DELETE */
    const AFTER_DELETEMESSAGE  = "action.after_deleteMessage";

    /** @deprecated since 2.4, \Thelia\Model\Event\MessageEvent::PRE_UPDATE */
    const BEFORE_UPDATEMESSAGE = "action.before_updateMessage";
    /** @deprecated since 2.4, \Thelia\Model\Event\MessageEvent::POST_UPDATE */
    const AFTER_UPDATEMESSAGE  = "action.after_updateMessage";
    
    // -- Currencies management ---------------------------------------------

    const CURRENCY_CREATE          = "action.createCurrency";
    const CURRENCY_UPDATE          = "action.updateCurrency";
    const CURRENCY_DELETE          = "action.deleteCurrency";
    const CURRENCY_SET_DEFAULT     = "action.setDefaultCurrency";
    const CURRENCY_SET_VISIBLE     = "action.setVisibleCurrency";
    const CURRENCY_UPDATE_RATES    = "action.updateCurrencyRates";
    const CURRENCY_UPDATE_POSITION = "action.updateCurrencyPosition";

    /** @deprecated since 2.4, \Thelia\Model\Event\CurrencyEvent::PRE_INSERT */
    const BEFORE_CREATECURRENCY = "action.before_createCurrency";
    /** @deprecated since 2.4, \Thelia\Model\Event\CurrencyEvent::POST_INSERT */
    const AFTER_CREATECURRENCY  = "action.after_createCurrency";

    /** @deprecated since 2.4, \Thelia\Model\Event\CurrencyEvent::PRE_DELETE */
    const BEFORE_DELETECURRENCY = "action.before_deleteCurrency";
    /** @deprecated since 2.4, \Thelia\Model\Event\CurrencyEvent::POST_DELETE */
    const AFTER_DELETECURRENCY  = "action.after_deleteCurrency";

    /** @deprecated since 2.4, \Thelia\Model\Event\CurrencyEvent::PRE_UPDATE */
    const BEFORE_UPDATECURRENCY = "action.before_updateCurrency";
    /** @deprecated since 2.4, \Thelia\Model\Event\CurrencyEvent::POST_UPDATE */
    const AFTER_UPDATECURRENCY  = "action.after_updateCurrency";
    
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

    /** @deprecated since 2.4, \Thelia\Model\Event\TemplateEvent::PRE_INSERT */
    const BEFORE_CREATETEMPLATE = "action.before_createTemplate";
    /** @deprecated since 2.4, \Thelia\Model\Event\TemplateEvent::POST_INSERT */
    const AFTER_CREATETEMPLATE  = "action.after_createTemplate";

    /** @deprecated since 2.4, \Thelia\Model\Event\TemplateEvent::PRE_DELETE */
    const BEFORE_DELETETEMPLATE = "action.before_deleteTemplate";
    /** @deprecated since 2.4, \Thelia\Model\Event\TemplateEvent::POST_DELETE */
    const AFTER_DELETETEMPLATE  = "action.after_deleteTemplate";

    /** @deprecated since 2.4, \Thelia\Model\Event\TemplateEvent::PRE_UPDATE */
    const BEFORE_UPDATETEMPLATE = "action.before_updateTemplate";
    /** @deprecated since 2.4, \Thelia\Model\Event\TemplateEvent::POST_UPDATE */
    const AFTER_UPDATETEMPLATE  = "action.after_updateTemplate";

    // -- Attributes management ---------------------------------------------

    const ATTRIBUTE_CREATE          = "action.createAttribute";
    const ATTRIBUTE_UPDATE          = "action.updateAttribute";
    const ATTRIBUTE_DELETE          = "action.deleteAttribute";
    const ATTRIBUTE_UPDATE_POSITION = "action.updateAttributePosition";

    const ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES = "action.addAttributeToAllTemplate";
    const ATTRIBUTE_ADD_TO_ALL_TEMPLATES      = "action.removeAttributeFromAllTemplate";

    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeEvent::PRE_INSERT */
    const BEFORE_CREATEATTRIBUTE = "action.before_createAttribute";
    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeEvent::POST_INSERT */
    const AFTER_CREATEATTRIBUTE  = "action.after_createAttribute";

    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeEvent::PRE_DELETE */
    const BEFORE_DELETEATTRIBUTE = "action.before_deleteAttribute";
    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeEvent::POST_DELETE */
    const AFTER_DELETEATTRIBUTE  = "action.after_deleteAttribute";

    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeEvent::PRE_UPDATE */
    const BEFORE_UPDATEATTRIBUTE = "action.before_updateAttribute";
    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeEvent::POST_UPDATE */
    const AFTER_UPDATEATTRIBUTE  = "action.after_updateAttribute";

    // -- Features management ---------------------------------------------

    const FEATURE_CREATE          = "action.createFeature";
    const FEATURE_UPDATE          = "action.updateFeature";
    const FEATURE_DELETE          = "action.deleteFeature";
    const FEATURE_UPDATE_POSITION = "action.updateFeaturePosition";

    const FEATURE_REMOVE_FROM_ALL_TEMPLATES = "action.addFeatureToAllTemplate";
    const FEATURE_ADD_TO_ALL_TEMPLATES      = "action.removeFeatureFromAllTemplate";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureEvent::PRE_INSERT */
    const BEFORE_CREATEFEATURE = "action.before_createFeature";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureEvent::POST_INSERT */
    const AFTER_CREATEFEATURE  = "action.after_createFeature";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureEvent::PRE_DELETE */
    const BEFORE_DELETEFEATURE = "action.before_deleteFeature";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureEvent::POST_DELETE */
    const AFTER_DELETEFEATURE  = "action.after_deleteFeature";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureEvent::PRE_UPDATE */
    const BEFORE_UPDATEFEATURE = "action.before_updateFeature";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureEvent::POST_UPDATE */
    const AFTER_UPDATEFEATURE  = "action.after_updateFeature";

    // -- Attributes values management ----------------------------------------

    const ATTRIBUTE_AV_CREATE          = "action.createAttributeAv";
    const ATTRIBUTE_AV_UPDATE          = "action.updateAttributeAv";
    const ATTRIBUTE_AV_DELETE          = "action.deleteAttributeAv";
    const ATTRIBUTE_AV_UPDATE_POSITION = "action.updateAttributeAvPosition";

    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeAvEvent::PRE_INSERT */
    const BEFORE_CREATEATTRIBUTE_AV = "action.before_createAttributeAv";
    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeAvEvent::POST_INSERT */
    const AFTER_CREATEATTRIBUTE_AV  = "action.after_createAttributeAv";

    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeAvEvent::PRE_DELETE */
    const BEFORE_UPDATEATTRIBUTE_AV = "action.before_updateAttributeAv";
    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeAvEvent::POST_DELETE */
    const AFTER_UPDATEATTRIBUTE_AV  = "action.after_updateAttributeAv";

    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeAvEvent::PRE_UPDATE */
    const BEFORE_DELETEATTRIBUTE_AV = "action.before_deleteAttributeAv";
    /** @deprecated since 2.4, \Thelia\Model\Event\AttributeAvEvent::POST_UPDATE */
    const AFTER_DELETEATTRIBUTE_AV  = "action.after_deleteAttributeAv";
    
    // -- Features values management ----------------------------------------

    const FEATURE_AV_CREATE          = "action.createFeatureAv";
    const FEATURE_AV_UPDATE          = "action.updateFeatureAv";
    const FEATURE_AV_DELETE          = "action.deleteFeatureAv";
    const FEATURE_AV_UPDATE_POSITION = "action.updateFeatureAvPosition";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureAvEvent::PRE_INSERT */
    const BEFORE_CREATEFEATURE_AV = "action.before_createFeatureAv";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureAvEvent::POST_INSERT */
    const AFTER_CREATEFEATURE_AV  = "action.after_createFeatureAv";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureAvEvent::PRE_DELETE */
    const BEFORE_UPDATEFEATURE_AV = "action.before_updateFeatureAv";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureAvEvent::POST_DELETE */
    const AFTER_UPDATEFEATURE_AV  = "action.after_updateFeatureAv";

    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureAvEvent::PRE_UPDATE */
    const BEFORE_DELETEFEATURE_AV = "action.before_deleteFeatureAv";
    /** @deprecated since 2.4, \Thelia\Model\Event\FeatureAvEvent::POST_UPDATE */
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
    public static function getModuleEvent($eventName, $moduleCode)
    {
        return sprintf("%s.%s", $eventName, strtolower($moduleCode));
    }

    /* Payment module */
    const MODULE_PAY = 'thelia.module.pay';
    const MODULE_PAYMENT_IS_VALID = 'thelia.module.payment.is_valid';
    const MODULE_PAYMENT_MANAGE_STOCK = 'thelia.module.payment.manage_stock';

    /* Delivery module */
    const MODULE_DELIVERY_GET_POSTAGE = 'thelia.module.delivery.postage';
    const MODULE_DELIVERY_GET_PICKUP_LOCATIONS = 'thelia.module.delivery.pickupLocations';

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

    const LANG_FIX_MISSING_FLAG                 = 'action.lang.fix_missing_flag';

    const LANG_TOGGLEDEFAULT                    = 'action.lang.toggleDefault';
    const LANG_TOGGLEACTIVE                    = 'action.lang.toggleActive';
    const LANG_TOGGLEVISIBLE                    = 'action.lang.toggleVisible';

    /** @deprecated since 2.4, \Thelia\Model\Event\LangEvent::PRE_INSERT */
    const BEFORE_CREATELANG = "action.before_createLang";
    /** @deprecated since 2.4, \Thelia\Model\Event\LangEvent::POST_INSERT */
    const AFTER_CREATELANG  = "action.after_createLang";

    /** @deprecated since 2.4, \Thelia\Model\Event\LangEvent::PRE_DELETE */
    const BEFORE_DELETELANG = "action.before_deleteLang";
    /** @deprecated since 2.4, \Thelia\Model\Event\LangEvent::POST_DELETE */
    const AFTER_DELETELANG  = "action.after_deleteLang";

    /** @deprecated since 2.4, \Thelia\Model\Event\LangEvent::PRE_UPDATE */
    const BEFORE_UPDATELANG = "action.before_updateLang";
    /** @deprecated since 2.4, \Thelia\Model\Event\LangEvent::POST_UPDATE */
    const AFTER_UPDATELANG  = "action.after_updateLang";

    // -- Brands management -----------------------------------------------

    const BRAND_CREATE = "action.createBrand";
    const BRAND_UPDATE = "action.updateBrand";
    const BRAND_DELETE = "action.deleteBrand";

    const BRAND_UPDATE_POSITION   = "action.updateBrandPosition";
    const BRAND_TOGGLE_VISIBILITY = "action.toggleBrandVisibility";

    const BRAND_UPDATE_SEO = "action.updateBrandSeo";

    /** @deprecated since 2.4, \Thelia\Model\Event\BrandEvent::PRE_INSERT */
    const BEFORE_CREATEBRAND = "action.before_createBrand";
    /** @deprecated since 2.4, \Thelia\Model\Event\BrandEvent::POST_INSERT */
    const AFTER_CREATEBRAND  = "action.after_createBrand";

    /** @deprecated since 2.4, \Thelia\Model\Event\BrandEvent::PRE_DELETE */
    const BEFORE_DELETEBRAND = "action.before_deleteBrand";
    /** @deprecated since 2.4, \Thelia\Model\Event\BrandEvent::POST_DELETE */
    const AFTER_DELETEBRAND  = "action.after_deleteBrand";

    /** @deprecated since 2.4, \Thelia\Model\Event\BrandEvent::PRE_UPDATE */
    const BEFORE_UPDATEBRAND = "action.before_updateBrand";
    /** @deprecated since 2.4, \Thelia\Model\Event\BrandEvent::POST_UPDATE */
    const AFTER_UPDATEBRAND  = "action.after_updateBrand";

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

    /** @deprecated since 2.4, \Thelia\Model\Event\SaleEvent::PRE_INSERT */
    const BEFORE_CREATESALE = "action.before_createSale";
    /** @deprecated since 2.4, \Thelia\Model\Event\SaleEvent::POST_INSERT */
    const AFTER_CREATESALE  = "action.after_createSale";

    /** @deprecated since 2.4, \Thelia\Model\Event\SaleEvent::PRE_DELETE */
    const BEFORE_DELETESALE = "action.before_deleteSale";
    /** @deprecated since 2.4, \Thelia\Model\Event\SaleEvent::POST_DELETE */
    const AFTER_DELETESALE  = "action.after_deleteSale";

    /** @deprecated since 2.4, \Thelia\Model\Event\SaleEvent::PRE_UPDATE */
    const BEFORE_UPDATESALE = "action.before_updateSale";
    /** @deprecated since 2.4, \Thelia\Model\Event\SaleEvent::POST_UPDATE */
    const AFTER_UPDATESALE  = "action.after_updateSale";

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

    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerTitleEvent::PRE_INSERT */
    const CUSTOMER_TITLE_BEFORE_CREATE = "action.title.before_create";
    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerTitleEvent::PRE_INSERT */
    const CUSTOMER_TITLE_AFTER_CREATE = "action.title.after_create";
    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerTitleEvent::PRE_INSERT */
    const CUSTOMER_TITLE_BEFORE_UPDATE = "action.title.before_update";
    /** @deprecated since 2.4, \Thelia\Model\Event\CustomerTitleEvent::PRE_INSERT */
    const CUSTOMER_TITLE_AFTER_UPDATE = "action.title.after_update";

    const CUSTOMER_TITLE_DELETE = "action.title.delete";

    // -- Translation -------------------------------------------

    const TRANSLATION_GET_STRINGS = 'action.translation.get_strings';
    const TRANSLATION_WRITE_FILE = 'action.translation.write_file';

    // -- ORDER STATUS EVENTS -----------------------------------------------

    const ORDER_STATUS_CREATE           = "action.createOrderStatus";
    const ORDER_STATUS_UPDATE           = "action.updateOrderStatus";
    const ORDER_STATUS_DELETE           = "action.deleteOrderStatus";

    /** @deprecated since 2.4, \Thelia\Model\Event\OrderStatusEvent::PRE_INSERT */
    const BEFORE_CREATE_ORDER_STATUS = "action.before_createOrderStatus";
    /** @deprecated since 2.4, \Thelia\Model\Event\OrderStatusEvent::POST_INSERT */
    const AFTER_CREATE_ORDER_STATUS  = "action.after_createOrderStatus";

    /** @deprecated since 2.4, \Thelia\Model\Event\OrderStatusEvent::PRE_DELETE */
    const BEFORE_DELETE_ORDER_STATUS = "action.before_deleteOrderStatus";
    /** @deprecated since 2.4, \Thelia\Model\Event\OrderStatusEvent::POST_DELETE */
    const AFTER_DELETE_ORDER_STATUS  = "action.after_deleteOrderStatus";

    /** @deprecated since 2.4, \Thelia\Model\Event\OrderStatusEvent::PRE_UPDATE */
    const BEFORE_UPDATE_ORDER_STATUS = "action.before_updateOrderStatus";
    /** @deprecated since 2.4, \Thelia\Model\Event\OrderStatusEvent::POST_UPDATE */
    const AFTER_UPDATE_ORDER_STATUS  = "action.after_updateOrderStatus";

    const ORDER_STATUS_UPDATE_POSITION  = "action.updateOrderStatusPosition";
    // -- END ORDER STATUS EVENTS -----------------------------------------------
}
