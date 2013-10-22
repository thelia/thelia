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

use Thelia\Core\Security\Exception\ResourceException;

/**
 *
 * This class contains all Thelia admin resources
 *
 * @author Etienne roudeix <eroudeix@openstudio.fr>
 */
final class AdminResources
{
    static private $selfReflection = null;

    static public function retrieve($name, $action)
    {
        $contantName = strtoupper($name . '_' . $action);

        if(null === self::$selfReflection) {
            self::$selfReflection = new \ReflectionClass(__CLASS__);
        }

        if(self::$selfReflection->hasConstant($contantName)) {
            return self::$selfReflection->getConstant($contantName);
        } else {
            throw new ResourceException(sprintf('Resource `%s` not found', $contantName), ResourceException::RESOURCE_NOT_FOUND);
        }
    }

    const SUPERADMINISTRATOR = "SUPERADMINISTRATOR";

    const ADDRESS_VIEW = "admin.address.view";
    const ADDRESS_CREATE = "admin.address.create";
    const ADDRESS_UPDATE = "admin.address.update";
    const ADDRESS_DELETE = "admin.address.delete";

    const ADMIN_VIEW = "admin.configuration.admin.view";
    const ADMIN_CREATE = "admin.configuration.admin.create";
    const ADMIN_UPDATE = "admin.configuration.admin.update";
    const ADMIN_DELETE = "admin.configuration.admin.delete";

    const AREA_VIEW = "admin.configuration.area.view";
    const AREA_CREATE = "admin.configuration.area.create";
    const AREA_UPDATE = "admin.configuration.area.update";
    const AREA_DELETE = "admin.configuration.area.delete";

    const ATTRIBUTE_VIEW = "admin.configuration.attribute.view";
    const ATTRIBUTE_CREATE = "admin.configuration.attribute.create";
    const ATTRIBUTE_UPDATE = "admin.configuration.attribute.update";
    const ATTRIBUTE_DELETE = "admin.configuration.attribute.delete";

    const CATEGORY_VIEW = "admin.category.view";
    const CATEGORY_CREATE = "admin.category.create";
    const CATEGORY_UPDATE = "admin.category.update";
    const CATEGORY_DELETE = "admin.category.delete";

    const CONFIG_VIEW = "admin.configuration.view";
    const CONFIG_CREATE = "admin.configuration.create";
    const CONFIG_UPDATE = "admin.configuration.update";
    const CONFIG_DELETE = "admin.configuration.delete";

    const CONTENT_VIEW = "admin.content.view";
    const CONTENT_CREATE = "admin.content.create";
    const CONTENT_UPDATE = "admin.content.update";
    const CONTENT_DELETE = "admin.content.delete";

    const COUNTRY_VIEW = "admin.configuration.country.view";
    const COUNTRY_CREATE = "admin.configuration.country.create";
    const COUNTRY_UPDATE = "admin.configuration.country.update";
    const COUNTRY_DELETE = "admin.configuration.country.delete";

    const COUPON_VIEW = "admin.coupon.view";
    const COUPON_CREATE = "admin.coupon.create";
    const COUPON_UPDATE = "admin.coupon.update";
    const COUPON_DELETE = "admin.coupon.delete";

    const CURRENCY_VIEW = "admin.configuration.currency.view";
    const CURRENCY_CREATE = "admin.configuration.currency.create";
    const CURRENCY_UPDATE = "admin.configuration.currency.update";
    const CURRENCY_DELETE = "admin.configuration.currency.delete";

    const CUSTOMER_VIEW = "admin.customer.view";
    const CUSTOMER_CREATE = "admin.customer.create";
    const CUSTOMER_UPDATE = "admin.customer.update";
    const CUSTOMER_DELETE = "admin.customer.delete";

    const FEATURE_VIEW = "admin.configuration.feature.view";
    const FEATURE_CREATE = "admin.configuration.feature.create";
    const FEATURE_UPDATE = "admin.configuration.feature.update";
    const FEATURE_DELETE = "admin.configuration.feature.delete";

    const FOLDER_VIEW = "admin.folder.view";
    const FOLDER_CREATE = "admin.folder.create";
    const FOLDER_UPDATE = "admin.folder.update";
    const FOLDER_DELETE = "admin.folder.delete";

    const LANGUAGE_VIEW = "admin.configuration.language.view";
    const LANGUAGE_CREATE = "admin.configuration.language.create";
    const LANGUAGE_UPDATE = "admin.configuration.language.update";
    const LANGUAGE_DELETE = "admin.configuration.language.delete";

    const MAILING_SYSTEM_VIEW = "admin.configuration.mailing-system.view";
    const MAILING_SYSTEM_CREATE = "admin.configuration.mailing-system.create";
    const MAILING_SYSTEM_UPDATE = "admin.configuration.mailing-system.update";
    const MAILING_SYSTEM_DELETE = "admin.configuration.mailing-system.delete";

    const MESSAGE_VIEW = "admin.configuration.message.view";
    const MESSAGE_CREATE = "admin.configuration.message.create";
    const MESSAGE_UPDATE = "admin.configuration.message.update";
    const MESSAGE_DELETE = "admin.configuration.message.delete";

    const MODULE_VIEW = "admin.configuration.module.view";
    const MODULE_CREATE = "admin.configuration.module.create";
    const MODULE_UPDATE = "admin.configuration.module.update";
    const MODULE_DELETE = "admin.configuration.module.delete";

    const ORDER_VIEW = "admin.order.view";
    const ORDER_CREATE = "admin.order.create";
    const ORDER_UPDATE = "admin.order.update";
    const ORDER_DELETE = "admin.order.delete";

    const PRODUCT_VIEW = "admin.product.view";
    const PRODUCT_CREATE = "admin.product.create";
    const PRODUCT_UPDATE = "admin.product.update";
    const PRODUCT_DELETE = "admin.product.delete";

    const PROFILE_VIEW = "admin.configuration.profile.view";
    const PROFILE_CREATE = "admin.configuration.profile.create";
    const PROFILE_UPDATE = "admin.configuration.profile.update";
    const PROFILE_DELETE = "admin.configuration.profile.delete";

    const SHIPPING_ZONE_VIEW = "admin.configuration.shipping-zone.view";
    const SHIPPING_ZONE_CREATE = "admin.configuration.shipping-zone.create";
    const SHIPPING_ZONE_UPDATE = "admin.configuration.shipping-zone.update";
    const SHIPPING_ZONE_DELETE = "admin.configuration.shipping-zone.delete";

    const TAX_VIEW = "admin.configuration.tax.view";
    const TAX_CREATE = "admin.configuration.tax.create";
    const TAX_UPDATE = "admin.configuration.tax.update";
    const TAX_DELETE = "admin.configuration.tax.delete";

    const TEMPLATE_VIEW = "admin.configuration.template.view";
    const TEMPLATE_CREATE = "admin.configuration.template.create";
    const TEMPLATE_UPDATE = "admin.configuration.template.update";
    const TEMPLATE_DELETE = "admin.configuration.template.delete";
}
