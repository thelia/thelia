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
    const BEFORE_CHANGECUSTOMER = "action.before_updateCustomer";
    /**
     * Sent just after a successful update of a customer in the database.
     */
    const AFTER_CHANGECUSTOMER 	= "action.after_updateCustomer";

    /**
     * sent for address creation
     */
    const ADDRESS_CREATE = "action.createAddress";

    /**
     * sent for address creation
     */
    const ADDRESS_UPDATE = "action.updateAddress";

    /**
     * Sent once the category creation form has been successfully validated, and before category insertion in the database.
     */
    const BEFORE_CREATECATEGORY = "action.before_createcategory";

    /**
     * Create, change or delete a category
     */
    const CATEGORY_CREATE = "action.createCategory";
    const CATEGORY_MODIFY = "action.modifyCategory";
    const CATEGORY_DELETE = "action.deleteCategory";

    /**
     * Toggle category visibility
     */
    const CATEGORY_TOGGLE_VISIBILITY = "action.toggleCategoryVisibility";

    /**
     * Change category position
     */
    const CATEGORY_CHANGE_POSITION = "action.changeCategoryPosition";

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
    const BEFORE_CHANGECATEGORY = "action.before_changecategory";

    /**
     * Sent just after a successful change of a category in the database.
     */
    const AFTER_CHANGECATEGORY 	= "action.after_changecategory";

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
    const AFTER_CARTCHANGEITEM = "cart.modifyItem";

    /**
     * sent for addArticle action
     */
    const CART_ADDITEM = "action.addArticle";

    /**
     * sent on modify article action
     */
    const CART_CHANGEITEM = "action.changeArticle";

    const CART_DELETEITEM = "action.deleteArticle";

    /**
     * Sent on image processing
     */
    const IMAGE_PROCESS = "action.processImage";

    /**
     * Sent on cimage cache clear request
     */
    const IMAGE_CLEAR_CACHE = "action.clearImageCache";

    // -- Configuration management ---------------------------------------------

    const CONFIG_CREATE   = "action.createConfig";
    const CONFIG_SETVALUE = "action.setConfigValue";
    const CONFIG_MODIFY   = "action.changeConfig";
    const CONFIG_DELETE   = "action.deleteConfig";

    const BEFORE_CREATECONFIG = "action.before_createConfig";
    const AFTER_CREATECONFIG  = "action.after_createConfig";

    const BEFORE_CHANGECONFIG = "action.before_changeConfig";
    const AFTER_CHANGECONFIG  = "action.after_changeConfig";

    const BEFORE_DELETECONFIG = "action.before_deleteConfig";
    const AFTER_DELETECONFIG  = "action.after_deleteConfig";

    // -- Messages management ---------------------------------------------

    const MESSAGE_CREATE   = "action.createMessage";
    const MESSAGE_MODIFY   = "action.changeMessage";
    const MESSAGE_DELETE   = "action.deleteMessage";

    const BEFORE_CREATEMESSAGE = "action.before_createMessage";
    const AFTER_CREATEMESSAGE  = "action.after_createMessage";

    const BEFORE_CHANGEMESSAGE = "action.before_changeMessage";
    const AFTER_CHANGEMESSAGE  = "action.after_changeMessage";

    const BEFORE_DELETEMESSAGE = "action.before_deleteMessage";
    const AFTER_DELETEMESSAGE  = "action.after_deleteMessage";

    // -- Currencies management ---------------------------------------------

    const CURRENCY_CREATE   = "action.createCurrency";
    const CURRENCY_MODIFY   = "action.changeCurrency";
    const CURRENCY_DELETE   = "action.deleteCurrency";

    const BEFORE_CREATECURRENCY = "action.before_createCurrency";
    const AFTER_CREATECURRENCY  = "action.after_createCurrency";

    const BEFORE_CHANGECURRENCY = "action.before_changeCurrency";
    const AFTER_CHANGECURRENCY  = "action.after_changeCurrency";

    const BEFORE_DELETECURRENCY = "action.before_deleteCurrency";
    const AFTER_DELETECURRENCY  = "action.after_deleteCurrency";


}
