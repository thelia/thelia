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
    const BEFORE_CHANGECUSTOMER = "action.before_changecustomer";
    /**
     * Sent just after a successful update of a customer in the database.
     */
    const AFTER_CHANGECUSTOMER 	= "action.after_changecustomer";

    /**
     * Sent once the category creation form has been successfully validated, and before category insertion in the database.
     */
    const BEFORE_CREATECATEGORY = "action.before_createcategory";

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
}
