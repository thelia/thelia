<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
     * ACTION event
     *
     * Sent if no action are already present in Thelia action process ( see Thelia\Routing\Matcher\ActionMatcher)
     */
    const ACTION = "thelia.action";

    /**
     * INCLUDE event
     *
     * Sent before starting thelia inclusion
     */
    const INCLUSION = "thelia.include";

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
     * sent on customer removal
     */
    const CUSTOMER_DELETEACCOUNT = "action.deleteCustomer";

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
    const BEFORE_DELETECUSTOMER = "action.before_updateCustomer";

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
     * sent for address creation
     */
    const ADDRESS_UPDATE = "action.updateAddress";

    /**
     * sent on address removal
     */
    const ADDRESS_DELETE = "action.deleteAddress";

    const BEFORE_CREATEADDRESS = "action.before_createAddress";
    const AFTER_CREATEADDRESS  = "action.after_createAddress";

    const BEFORE_UPDATEADDRESS = "action.before_updateAddress";
    const AFTER_UPDATEADDRESS = "action.after_updateAddress";

    const BEFORE_DELETEADDRESS = "action.before_deleteAddress";
    const AFTER_DELETEADDRESS = "action.after_deleteAddress";

    // -- END ADDRESS MANAGEMENT ---------------------------------------------------------

    /**
     * Sent once the category creation form has been successfully validated, and before category insertion in the database.
     */
    const BEFORE_CREATECATEGORY = "action.before_createcategory";

    /**
     * Create, change or delete a category
     */
    const CATEGORY_CREATE = "action.createCategory";
    const CATEGORY_UPDATE = "action.updateCategory";
    const CATEGORY_DELETE = "action.deleteCategory";

    /**
     * Toggle category visibility
     */
    const CATEGORY_TOGGLE_VISIBILITY = "action.toggleCategoryVisibility";

    /**
     * Change category position
     */
    const CATEGORY_CHANGE_POSITION = "action.updateCategoryPosition";

    /**
     * Sent just after a successful insert of a new category in the database.
     */
    const AFTER_CREATECATEGORY 	= "action.after_createcategory";
    /**
     * Sent befonre deleting a category
     */
    const BEFORE_DELETECATEGORY = "action.before_deletecategory";

    /**
     * Sent just after a successful delete of a category from the database.
     */
    const AFTER_DELETECATEGORY 	= "action.after_deletecategory";

    /**
     * Sent just before a successful change of a category in the database.
     */
    const BEFORE_UPDATECATEGORY = "action.before_updateCategory";

    /**
     * Sent just after a successful change of a category in the database.
     */
    const AFTER_UPDATECATEGORY 	= "action.after_updateCategory";

    /**
     * sent when a new existing cat id duplicated. This append when current customer is different from current cart
     */
    const CART_DUPLICATE = "cart.duplicate";

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

    /**
     * Sent on image processing
     */
    const IMAGE_PROCESS = "action.processImage";

    /**
     * Sent on cimage cache clear request
     */
    const IMAGE_CLEAR_CACHE = "action.clearImageCache";

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
     * Sent when disabling a Coupon
     */
    const COUPON_DISABLE = "action.disable_coupon";

    /**
     * Sent just before a successful disable of a new Coupon in the database.
     */
    const BEFORE_DISABLE_COUPON 	= "action.before_disable_coupon";

    /**
     * Sent just after a successful disable of a new Coupon in the database.
     */
    const AFTER_DISABLE_COUPON 	= "action.after_disable_coupon";

    /**
     * Sent when enabling a Coupon
     */
    const COUPON_ENABLE = "action.enable_coupon";

    /**
     * Sent just before a successful enable of a new Coupon in the database.
     */
    const BEFORE_ENABLE_COUPON 	= "action.before_enable_coupon";

    /**
     * Sent just after a successful enable of a new Coupon in the database.
     */
    const AFTER_ENABLE_COUPON 	= "action.after_enable_coupon";

    /**
     * Sent when attempting to use a Coupon
     */
    const COUPON_CONSUME 	= "action.consume_coupon";

    /**
     * Sent just before an attempt to use a Coupon
     */
    const BEFORE_CONSUME_COUPON 	= "action.before_consume_coupon";

    /**
     * Sent just after an attempt to use a Coupon
     */
    const AFTER_CONSUME_COUPON 	= "action.after_consume_coupon";

    /**
     * Sent when attempting to update Coupon Rule
     */
    const COUPON_RULE_UPDATE 	= "action.update_coupon_rule";

    /**
     * Sent just before an attempt to update a Coupon Rule
     */
    const BEFORE_COUPON_RULE_UPDATE 	= "action.before_update_coupon_rule";

    /**
     * Sent just after an attempt to update a Coupon Rule
     */
    const AFTER_COUPON_RULE_UPDATE 	= "action.after_update_coupon_rule";

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

    // -- Product templates management -----------------------------------------

    const TEMPLATE_CREATE          = "action.createTemplate";
    const TEMPLATE_UPDATE          = "action.updateTemplate";
    const TEMPLATE_DELETE          = "action.deleteTemplate";

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
}
