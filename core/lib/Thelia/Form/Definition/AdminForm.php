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

namespace Thelia\Form\Definition;

/**
 * Class AdminForm
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @package Thelia\Form\Definition
 */
final class AdminForm
{
    const ADMIN_LOGIN = 'thelia.admin.login';
    const SEO = 'thelia.admin.seo';

    const CUSTOMER_CREATE = 'thelia.admin.customer.create';
    const CUSTOMER_UPDATE = 'thelia.admin.customer.update';

    const ADDRESS_CREATE = 'thelia.admin.address.create';
    const ADDRESS_UPDATE = 'thelia.admin.address.update';

    const CATEGORY_CREATION = 'thelia.admin.category.creation';
    const CATEGORY_MODIFICATION = 'thelia.admin.category.modification';
    const CATEGORY_IMAGE_MODIFICATION = 'thelia.admin.category.image.modification';
    const CATEGORY_DOCUMENT_MODIFICATION = 'thelia.admin.category.document.modification';

    const PRODUCT_CREATION = 'thelia.admin.product.creation';
    const PRODUCT_CLONE = 'thelia.admin.product.clone';
    const PRODUCT_MODIFICATION = 'thelia.admin.product.modification';
    const PRODUCT_DETAILS_MODIFICATION = 'thelia.admin.product.details.modification';
    const PRODUCT_IMAGE_MODIFICATION = 'thelia.admin.product.image.modification';
    const PRODUCT_DOCUMENT_MODIFICATION = 'thelia.admin.product.document.modification';

    const PRODUCT_SALE_ELEMENT_UPDATE = 'thelia.admin.product_sale_element.update';
    const PRODUCT_DEFAULT_SALE_ELEMENT_UPDATE = 'thelia.admin.product_default_sale_element.update';
    const PRODUCT_COMBINATION_GENERATION = 'thelia.admin.product_combination.build';

    const PRODUCT_DELETE = 'thelia.admin.product.deletion';

    const FOLDER_CREATION = 'thelia.admin.folder.creation';
    const FOLDER_MODIFICATION = 'thelia.admin.folder.modification';
    const FOLDER_IMAGE_MODIFICATION = 'thelia.admin.folder.image.modification';
    const FOLDER_DOCUMENT_MODIFICATION = 'thelia.admin.folder.document.modification';

    const CONTENT_CREATION = 'thelia.admin.content.creation';
    const CONTENT_MODIFICATION = 'thelia.admin.content.modification';
    const CONTENT_IMAGE_MODIFICATION = 'thelia.admin.content.image.modification';
    const CONTENT_DOCUMENT_MODIFICATION = 'thelia.admin.content.document.modification';

    const BRAND_CREATION = 'thelia.admin.brand.creation';
    const BRAND_MODIFICATION = 'thelia.admin.brand.modification';
    const BRAND_IMAGE_MODIFICATION = 'thelia.admin.brand.image.modification';
    const BRAND_DOCUMENT_MODIFICATION = 'thelia.admin.brand.document.modification';

    const CART_ADD = 'thelia.cart.add';

    const ORDER_DELIVERY = 'thelia.order.delivery';
    const ORDER_PAYMENT = 'thelia.order.payment';
    const ORDER_UPDATE_ADDRESS = 'thelia.order.update.address';

    const COUPON_CODE = 'thelia.order.coupon';

    const CONFIG_CREATION = 'thelia.admin.config.creation';
    const CONFIG_MODIFICATION = 'thelia.admin.config.modification';

    const MESSAGE_CREATION = 'thelia.admin.message.creation';
    const MESSAGE_MODIFICATION = 'thelia.admin.message.modification';

    const CURRENCY_CREATION = 'thelia.admin.currency.creation';
    const CURRENCY_MODIFICATION = 'thelia.admin.currency.modification';

    const COUPON_CREATION = 'thelia.admin.coupon.creation';

    const ATTRIBUTE_CREATION = 'thelia.admin.attribute.creation';
    const ATTRIBUTE_MODIFICATION = 'thelia.admin.attribute.modification';

    const FEATURE_CREATION = 'thelia.admin.feature.creation';
    const FEATURE_MODIFICATION = 'thelia.admin.feature.modification';

    const ATTRIBUTE_AV_CREATION = 'thelia.admin.attributeav.creation';

    const FEATURE_AV_CREATION = 'thelia.admin.featureav.creation';

    const TAX_RULE_MODIFICATION = 'thelia.admin.taxrule.modification';
    const TAX_RULE_TAX_LIST_UPDATE = 'thelia.admin.taxrule.taxlistupdate';
    const TAX_RULE_CREATION = 'thelia.admin.taxrule.add';

    const TAX_MODIFICATION = 'thelia.admin.tax.modification';
    const TAX_TAX_LIST_UPDATE = 'thelia.admin.tax.taxlistupdate';
    const TAX_CREATION = 'thelia.admin.tax.add';

    const PROFILE_CREATION = 'thelia.admin.profile.add';
    const PROFILE_MODIFICATION = 'thelia.admin.profile.modification';
    const PROFILE_UPDATE_RESOURCE_ACCESS = 'thelia.admin.profile.resource-access.modification';
    const PROFILE_UPDATE_MODULE_ACCESS = 'thelia.admin.profile.module-access.modification';

    const ADMINISTRATOR_CREATION = 'thelia.admin.administrator.add';
    const ADMINISTRATOR_MODIFICATION = 'thelia.admin.administrator.update';

    const MAILING_SYSTEM_MODIFICATION = 'thelia.admin.mailing-system.update';

    const TEMPLATE_CREATION = 'thelia.admin.template.creation';
    const TEMPLATE_MODIFICATION = 'thelia.admin.template.modification';

    const COUNTRY_CREATION = 'thelia.admin.country.creation';
    const COUNTRY_MODIFICATION = 'thelia.admin.country.modification';

    const STATE_CREATION = 'thelia.admin.state.creation';
    const STATE_MODIFICATION = 'thelia.admin.state.modification';

    const AREA_CREATE = 'thelia.admin.area.create';
    const AREA_MODIFICATION = 'thelia.admin.area.modification';
    const AREA_COUNTRY = 'thelia.admin.area.country';
    const AREA_DELETE_COUNTRY = 'thelia.admin.area.delete.country';
    const AREA_POSTAGE = 'thelia.admin.area.postage';

    const SHIPPING_ZONE_ADD_AREA = 'thelia.shopping_zone_area';
    const SHIPPING_ZONE_REMOVE_AREA = 'thelia.shopping_zone_remove_area';

    const LANG_UPDATE = 'thelia.lang.update';
    const LANG_CREATE = 'thelia.lang.create';
    const LANG_DEFAULT_BEHAVIOR = 'thelia.lang.defaultBehavior';
    const LANG_URL = 'thelia.lang.url';

    const CONFIG_STORE = 'thelia.configuration.store';
    const SYSTEM_LOG_CONFIGURATION = 'thelia.system-logs.configuration';

    const MODULE_MODIFICATION = 'thelia.admin.module.modification';
    const MODULE_INSTALL = 'thelia.admin.module.install';

    const HOOK_CREATION = 'thelia.admin.hook.creation';
    const HOOK_MODIFICATION = 'thelia.admin.hook.modification';

    const MODULE_HOOK_CREATION = 'thelia.admin.module-hook.creation';
    const MODULE_HOOK_MODIFICATION = 'thelia.admin.module-hook.modification';

    const CACHE_FLUSH = 'thelia.cache.flush';
    const ASSETS_FLUSH = 'thelia.assets.flush';
    const IMAGES_AND_DOCUMENTS_CACHE_FLUSH = 'thelia.images-and-documents-cache.flush';

    const EXPORT = 'thelia.export';
    const IMPORT = 'thelia.import';

    const SALE_CREATION = 'thelia.admin.sale.creation';
    const SALE_MODIFICATION = 'thelia.admin.sale.modification';

    const EMPTY_FORM = 'thelia.empty';

    const API_CREATE = 'thelia_api_create';
    const API_UPDATE = 'thelia_api_update';
}
