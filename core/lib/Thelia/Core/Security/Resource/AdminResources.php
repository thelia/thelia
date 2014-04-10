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

namespace Thelia\Core\Security\Resource;

use Thelia\Core\Security\Exception\ResourceException;

/**
 *
 * This class contains all Thelia admin resources
 *
 * @author Etienne roudeix <eroudeix@openstudio.fr>
 */
final class AdminResources
{
    private static $selfReflection = null;

    public static function retrieve($name)
    {
        $constantName = strtoupper($name);

        if (null === self::$selfReflection) {
            self::$selfReflection = new \ReflectionClass(__CLASS__);
        }

        if (self::$selfReflection->hasConstant($constantName)) {
            return self::$selfReflection->getConstant($constantName);
        } else {
            throw new ResourceException(sprintf('Resource `%s` not found', $constantName), ResourceException::RESOURCE_NOT_FOUND);
        }
    }

    const SUPERADMINISTRATOR = "SUPERADMINISTRATOR";

    const ADDRESS = "admin.address";

    const ADMINISTRATOR = "admin.configuration.administrator";

    const AREA = "admin.configuration.area";

    const ATTRIBUTE = "admin.configuration.attribute";

    const CACHE = "admin.cache";

    const CATEGORY = "admin.category";

    const CONFIG = "admin.configuration";

    const CONTENT = "admin.content";

    const COUNTRY = "admin.configuration.country";

    const COUPON = "admin.coupon";

    const CURRENCY = "admin.configuration.currency";

    const CUSTOMER = "admin.customer";

    const FEATURE = "admin.configuration.feature";

    const FOLDER = "admin.folder";

    const LANGUAGE = "admin.configuration.language";

    const MAILING_SYSTEM = "admin.configuration.mailing-system";

    const MESSAGE = "admin.configuration.message";

    const MODULE = "admin.module";

    const ORDER = "admin.order";

    const PRODUCT = "admin.product";

    const PROFILE = "admin.configuration.profile";

    const SHIPPING_ZONE = "admin.configuration.shipping-zone";

    const TAX = "admin.configuration.tax";

    const TEMPLATE = "admin.configuration.template";

    const SYSTEM_LOG = "admin.configuration.system-log";

    const STORE = "admin.configuration.store";

    const TRANSLATIONS = "admin.configuration.translations";

    const UPDATE = "admin.configuration.update";
}
