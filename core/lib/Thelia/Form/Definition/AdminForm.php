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

namespace Thelia\Form\Definition;

/**
 * Class AdminForm.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
final class AdminForm
{
    public const ADMIN_LOGIN = 'thelia.admin.login';
    public const ADMIN_LOST_PASSWORD = 'thelia.admin.lostpassword';
    public const ADMIN_CREATE_PASSWORD = 'thelia.admin.createpassword';

    public const SEO = 'thelia.admin.seo';

    public const CUSTOMER_CREATE = 'thelia.admin.customer.create';
    public const CUSTOMER_UPDATE = 'thelia.admin.customer.update';

    public const ADDRESS_CREATE = 'thelia.admin.address.create';
    public const ADDRESS_UPDATE = 'thelia.admin.address.update';

    public const CATEGORY_CREATION = 'thelia.admin.category.creation';
    public const CATEGORY_MODIFICATION = 'thelia.admin.category.modification';
    public const CATEGORY_IMAGE_MODIFICATION = 'thelia.admin.category.image.modification';
    public const CATEGORY_DOCUMENT_MODIFICATION = 'thelia.admin.category.document.modification';

    public const PRODUCT_CREATION = 'thelia.admin.product.creation';
    public const PRODUCT_CLONE = 'thelia.admin.product.clone';
    public const PRODUCT_MODIFICATION = 'thelia.admin.product.modification';
    public const PRODUCT_DETAILS_MODIFICATION = 'thelia.admin.product.details.modification';
    public const PRODUCT_IMAGE_MODIFICATION = 'thelia.admin.product.image.modification';
    public const PRODUCT_DOCUMENT_MODIFICATION = 'thelia.admin.product.document.modification';

    public const PRODUCT_SALE_ELEMENT_UPDATE = 'thelia.admin.product_sale_element.update';
    public const PRODUCT_DEFAULT_SALE_ELEMENT_UPDATE = 'thelia.admin.product_default_sale_element.update';
    public const PRODUCT_COMBINATION_GENERATION = 'thelia.admin.product_combination.build';

    public const PRODUCT_DELETE = 'thelia.admin.product.deletion';

    public const FOLDER_CREATION = 'thelia.admin.folder.creation';
    public const FOLDER_MODIFICATION = 'thelia.admin.folder.modification';
    public const FOLDER_IMAGE_MODIFICATION = 'thelia.admin.folder.image.modification';
    public const FOLDER_DOCUMENT_MODIFICATION = 'thelia.admin.folder.document.modification';

    public const CONTENT_CREATION = 'thelia.admin.content.creation';
    public const CONTENT_MODIFICATION = 'thelia.admin.content.modification';
    public const CONTENT_IMAGE_MODIFICATION = 'thelia.admin.content.image.modification';
    public const CONTENT_DOCUMENT_MODIFICATION = 'thelia.admin.content.document.modification';

    public const BRAND_CREATION = 'thelia.admin.brand.creation';
    public const BRAND_MODIFICATION = 'thelia.admin.brand.modification';
    public const BRAND_IMAGE_MODIFICATION = 'thelia.admin.brand.image.modification';
    public const BRAND_DOCUMENT_MODIFICATION = 'thelia.admin.brand.document.modification';

    public const CART_ADD = 'thelia.cart.add';

    public const ORDER_DELIVERY = 'thelia.order.delivery';
    public const ORDER_PAYMENT = 'thelia.order.payment';
    public const ORDER_UPDATE_ADDRESS = 'thelia.order.update.address';

    public const ORDER_STATUS_CREATION = 'thelia.admin.order-status.creation';
    public const ORDER_STATUS_MODIFICATION = 'thelia.admin.order-status.modification';

    public const COUPON_CODE = 'thelia.order.coupon';

    public const CONFIG_CREATION = 'thelia.admin.config.creation';
    public const CONFIG_MODIFICATION = 'thelia.admin.config.modification';

    public const MESSAGE_CREATION = 'thelia.admin.message.creation';
    public const MESSAGE_MODIFICATION = 'thelia.admin.message.modification';
    public const MESSAGE_SEND_SAMPLE = 'thelia.admin.message.send-sample';

    public const CURRENCY_CREATION = 'thelia.admin.currency.creation';
    public const CURRENCY_MODIFICATION = 'thelia.admin.currency.modification';

    public const COUPON_CREATION = 'thelia.admin.coupon.creation';

    public const ATTRIBUTE_CREATION = 'thelia.admin.attribute.creation';
    public const ATTRIBUTE_MODIFICATION = 'thelia.admin.attribute.modification';

    public const FEATURE_CREATION = 'thelia.admin.feature.creation';
    public const FEATURE_MODIFICATION = 'thelia.admin.feature.modification';

    public const ATTRIBUTE_AV_CREATION = 'thelia.admin.attributeav.creation';

    public const FEATURE_AV_CREATION = 'thelia.admin.featureav.creation';

    public const TAX_RULE_MODIFICATION = 'thelia.admin.taxrule.modification';
    public const TAX_RULE_TAX_LIST_UPDATE = 'thelia.admin.taxrule.taxlistupdate';
    public const TAX_RULE_CREATION = 'thelia.admin.taxrule.add';

    public const TAX_MODIFICATION = 'thelia.admin.tax.modification';
    public const TAX_TAX_LIST_UPDATE = 'thelia.admin.tax.taxlistupdate';
    public const TAX_CREATION = 'thelia.admin.tax.add';

    public const PROFILE_CREATION = 'thelia.admin.profile.add';
    public const PROFILE_MODIFICATION = 'thelia.admin.profile.modification';
    public const PROFILE_UPDATE_RESOURCE_ACCESS = 'thelia.admin.profile.resource-access.modification';
    public const PROFILE_UPDATE_MODULE_ACCESS = 'thelia.admin.profile.module-access.modification';

    public const ADMINISTRATOR_CREATION = 'thelia.admin.administrator.add';
    public const ADMINISTRATOR_MODIFICATION = 'thelia.admin.administrator.update';

    public const MAILING_SYSTEM_MODIFICATION = 'thelia.admin.mailing-system.update';

    public const TEMPLATE_CREATION = 'thelia.admin.template.creation';
    public const TEMPLATE_MODIFICATION = 'thelia.admin.template.modification';

    public const COUNTRY_CREATION = 'thelia.admin.country.creation';
    public const COUNTRY_MODIFICATION = 'thelia.admin.country.modification';

    public const STATE_CREATION = 'thelia.admin.state.creation';
    public const STATE_MODIFICATION = 'thelia.admin.state.modification';

    public const AREA_CREATE = 'thelia.admin.area.create';
    public const AREA_MODIFICATION = 'thelia.admin.area.modification';
    public const AREA_COUNTRY = 'thelia.admin.area.country';
    public const AREA_DELETE_COUNTRY = 'thelia.admin.area.delete.country';
    public const AREA_POSTAGE = 'thelia.admin.area.postage';

    public const SHIPPING_ZONE_ADD_AREA = 'thelia.shopping_zone_area';
    public const SHIPPING_ZONE_REMOVE_AREA = 'thelia.shopping_zone_remove_area';

    public const LANG_UPDATE = 'thelia.lang.update';
    public const LANG_CREATE = 'thelia.lang.create';
    public const LANG_DEFAULT_BEHAVIOR = 'thelia.lang.defaultBehavior';
    public const LANG_URL = 'thelia.lang.url';

    public const CONFIG_STORE = 'thelia.configuration.store';
    public const SYSTEM_LOG_CONFIGURATION = 'thelia.system-logs.configuration';

    public const MODULE_MODIFICATION = 'thelia.admin.module.modification';
    public const MODULE_INSTALL = 'thelia.admin.module.install';

    public const HOOK_CREATION = 'thelia.admin.hook.creation';
    public const HOOK_MODIFICATION = 'thelia.admin.hook.modification';

    public const MODULE_HOOK_CREATION = 'thelia.admin.module-hook.creation';
    public const MODULE_HOOK_MODIFICATION = 'thelia.admin.module-hook.modification';

    public const CACHE_FLUSH = 'thelia.cache.flush';
    public const ASSETS_FLUSH = 'thelia.assets.flush';
    public const IMAGES_AND_DOCUMENTS_CACHE_FLUSH = 'thelia.images-and-documents-cache.flush';

    public const EXPORT = 'thelia.export';
    public const IMPORT = 'thelia.import';

    public const SALE_CREATION = 'thelia.admin.sale.creation';
    public const SALE_MODIFICATION = 'thelia.admin.sale.modification';

    public const EMPTY_FORM = 'thelia.empty';
}
