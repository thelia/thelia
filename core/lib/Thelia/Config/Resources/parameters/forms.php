<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Form\AddressCreateForm;
use Thelia\Form\AddressUpdateForm;
use Thelia\Form\AdministratorCreationForm;
use Thelia\Form\AdministratorModificationForm;
use Thelia\Form\AdminCreatePassword;
use Thelia\Form\AdminLogin;
use Thelia\Form\AdminLostPassword;
use Thelia\Form\Area\AreaCountryForm;
use Thelia\Form\Area\AreaCreateForm;
use Thelia\Form\Area\AreaDeleteCountryForm;
use Thelia\Form\Area\AreaModificationForm;
use Thelia\Form\Area\AreaPostageForm;
use Thelia\Form\AttributeAvCreationForm;
use Thelia\Form\AttributeCreationForm;
use Thelia\Form\AttributeModificationForm;
use Thelia\Form\Brand\BrandCreationForm;
use Thelia\Form\Brand\BrandDocumentModification;
use Thelia\Form\Brand\BrandImageModification;
use Thelia\Form\Brand\BrandModificationForm;
use Thelia\Form\Cache\AssetsFlushForm;
use Thelia\Form\Cache\CacheFlushForm;
use Thelia\Form\Cache\ImagesAndDocumentsCacheFlushForm;
use Thelia\Form\CartAdd;
use Thelia\Form\CategoryCreationForm;
use Thelia\Form\CategoryDocumentModification;
use Thelia\Form\CategoryImageModification;
use Thelia\Form\CategoryModificationForm;
use Thelia\Form\ConfigCreationForm;
use Thelia\Form\ConfigModificationForm;
use Thelia\Form\ConfigStoreForm;
use Thelia\Form\ContactForm;
use Thelia\Form\ContentCreationForm;
use Thelia\Form\ContentDocumentModification;
use Thelia\Form\ContentImageModification;
use Thelia\Form\ContentModificationForm;
use Thelia\Form\CountryCreationForm;
use Thelia\Form\CountryModificationForm;
use Thelia\Form\CouponCode;
use Thelia\Form\CouponCreationForm;
use Thelia\Form\CurrencyCreationForm;
use Thelia\Form\CurrencyModificationForm;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerLogin;
use Thelia\Form\CustomerLostPasswordForm;
use Thelia\Form\CustomerPasswordUpdateForm;
use Thelia\Form\CustomerProfileUpdateForm;
use Thelia\Form\CustomerUpdateForm;
use Thelia\Form\EmptyForm;
use Thelia\Form\ExportForm;
use Thelia\Form\FeatureAvCreationForm;
use Thelia\Form\FeatureCreationForm;
use Thelia\Form\FeatureModificationForm;
use Thelia\Form\FolderCreationForm;
use Thelia\Form\FolderDocumentModification;
use Thelia\Form\FolderImageModification;
use Thelia\Form\FolderModificationForm;
use Thelia\Form\HookCreationForm;
use Thelia\Form\HookModificationForm;
use Thelia\Form\ImportForm;
use Thelia\Form\Lang\LangCreateForm;
use Thelia\Form\Lang\LangDefaultBehaviorForm;
use Thelia\Form\Lang\LangUpdateForm;
use Thelia\Form\Lang\LangUrlForm;
use Thelia\Form\MailingSystemModificationForm;
use Thelia\Form\MessageCreationForm;
use Thelia\Form\MessageModificationForm;
use Thelia\Form\MessageSendSampleForm;
use Thelia\Form\ModuleHookCreationForm;
use Thelia\Form\ModuleHookModificationForm;
use Thelia\Form\ModuleImageModification;
use Thelia\Form\ModuleInstallForm;
use Thelia\Form\ModuleModificationForm;
use Thelia\Form\NewsletterForm;
use Thelia\Form\NewsletterUnsubscribeForm;
use Thelia\Form\OrderDelivery;
use Thelia\Form\OrderPayment;
use Thelia\Form\OrderStatus\OrderStatusCreationForm;
use Thelia\Form\OrderStatus\OrderStatusModificationForm;
use Thelia\Form\OrderUpdateAddress;
use Thelia\Form\ProductCloneForm;
use Thelia\Form\ProductCombinationGenerationForm;
use Thelia\Form\ProductCreationForm;
use Thelia\Form\ProductDefaultSaleElementUpdateForm;
use Thelia\Form\ProductDocumentModification;
use Thelia\Form\ProductImageModification;
use Thelia\Form\ProductModificationForm;
use Thelia\Form\ProductSaleElementUpdateForm;
use Thelia\Form\ProfileCreationForm;
use Thelia\Form\ProfileModificationForm;
use Thelia\Form\ProfileUpdateModuleAccessForm;
use Thelia\Form\ProfileUpdateResourceAccessForm;
use Thelia\Form\Sale\SaleCreationForm;
use Thelia\Form\Sale\SaleModificationForm;
use Thelia\Form\SeoForm;
use Thelia\Form\ShippingZone\ShippingZoneAddArea;
use Thelia\Form\ShippingZone\ShippingZoneRemoveArea;
use Thelia\Form\State\StateCreationForm;
use Thelia\Form\State\StateModificationForm;
use Thelia\Form\SystemLogConfigurationForm;
use Thelia\Form\TaxCreationForm;
use Thelia\Form\TaxModificationForm;
use Thelia\Form\TaxRuleCreationForm;
use Thelia\Form\TaxRuleModificationForm;
use Thelia\Form\TaxRuleTaxListUpdateForm;
use Thelia\Form\TemplateCreationForm;
use Thelia\Form\TemplateModificationForm;
use Thelia\Form\TranslationsCustomerTitleForm;

return static function (ContainerConfigurator $configurator): void {
    $parameters = $configurator->parameters();

    $parameters->set('Thelia.parser.forms', [
        // Common forms
        'thelia.order.delivery' => OrderDelivery::class,
        'thelia.order.payment' => OrderPayment::class,
        'thelia.order.update.address' => OrderUpdateAddress::class,
        'thelia.cart.add' => CartAdd::class,
        'thelia.order.coupon' => CouponCode::class,
        'thelia.shopping_zone_area' => ShippingZoneAddArea::class,
        'thelia.shipping_zone_area' => ShippingZoneAddArea::class,
        'thelia.shopping_zone_remove_area' => ShippingZoneRemoveArea::class,
        'thelia.lang.update' => LangUpdateForm::class,
        'thelia.lang.create' => LangCreateForm::class,
        'thelia.lang.defaultBehavior' => LangDefaultBehaviorForm::class,
        'thelia.lang.url' => LangUrlForm::class,
        'thelia.configuration.store' => ConfigStoreForm::class,
        'thelia.system-logs.configuration' => SystemLogConfigurationForm::class,
        'thelia.cache.flush' => CacheFlushForm::class,
        'thelia.assets.flush' => AssetsFlushForm::class,
        'thelia.images-and-documents-cache.flush' => ImagesAndDocumentsCacheFlushForm::class,
        'thelia.export' => ExportForm::class,
        'thelia.import' => ImportForm::class,
        'thelia.empty' => EmptyForm::class,

        // Frontend forms
        'thelia.front.customer.login' => CustomerLogin::class,
        'thelia.front.customer.lostpassword' => CustomerLostPasswordForm::class,
        'thelia.front.customer.create' => CustomerCreateForm::class,
        'thelia.front.customer.profile.update' => CustomerProfileUpdateForm::class,
        'thelia.front.customer.password.update' => CustomerPasswordUpdateForm::class,
        'thelia.front.address.create' => AddressCreateForm::class,
        'thelia.front.address.update' => AddressUpdateForm::class,
        'thelia.front.contact' => ContactForm::class,
        'thelia.front.newsletter' => NewsletterForm::class,
        'thelia.front.newsletter.unsubscribe' => NewsletterUnsubscribeForm::class,

        // Backend forms
        'thelia.admin.login' => AdminLogin::class,
        'thelia.admin.lostpassword' => AdminLostPassword::class,
        'thelia.admin.createpassword' => AdminCreatePassword::class,
        'thelia.admin.seo' => SeoForm::class,
        'thelia.admin.product_sale_element.update' => ProductSaleElementUpdateForm::class,
        'thelia.admin.product_default_sale_element.update' => ProductDefaultSaleElementUpdateForm::class,
        'thelia.admin.product_combination.build' => ProductCombinationGenerationForm::class,
        'thelia.admin.product.deletion' => ProductModificationForm::class,
        'thelia.admin.attributeav.creation' => AttributeAvCreationForm::class,
        'thelia.admin.attributeav.modification' => AttributeAvCreationForm::class,
        'thelia.admin.featureav.creation' => FeatureAvCreationForm::class,
        'thelia.admin.taxrule.modification' => TaxRuleModificationForm::class,
        'thelia.admin.taxrule.taxlistupdate' => TaxRuleTaxListUpdateForm::class,
        'thelia.admin.taxrule.add' => TaxRuleCreationForm::class,
        'thelia.admin.tax.add' => TaxCreationForm::class,
        'thelia.admin.profile.add' => ProfileCreationForm::class,
        'thelia.admin.profile.resource-access.modification' => ProfileUpdateResourceAccessForm::class,
        'thelia.admin.profile.module-access.modification' => ProfileUpdateModuleAccessForm::class,
        'thelia.admin.administrator.add' => AdministratorCreationForm::class,
        'thelia.admin.administrator.update' => AdministratorModificationForm::class,
        'thelia.admin.mailing-system.update' => MailingSystemModificationForm::class,
        'thelia.admin.area.delete.country' => AreaDeleteCountryForm::class,
        'thelia.admin.message.send-sample' => MessageSendSampleForm::class,
        'thelia.admin.module-hook.creation' => ModuleHookCreationForm::class,
        'thelia.admin.module-hook.modification' => ModuleHookModificationForm::class,
        'thelia.admin.order-status.creation' => OrderStatusCreationForm::class,
        'thelia.admin.order-status.modification' => OrderStatusModificationForm::class,
        'thelia.admin.customer.create' => CustomerCreateForm::class,
        'thelia.admin.customer.update' => CustomerUpdateForm::class,
        'thelia.admin.address.create' => AddressCreateForm::class,
        'thelia.admin.address.update' => AddressUpdateForm::class,
        'thelia.admin.category.creation' => CategoryCreationForm::class,
        'thelia.admin.category.modification' => CategoryModificationForm::class,
        'thelia.admin.category.image.modification' => CategoryImageModification::class,
        'thelia.admin.category.document.modification' => CategoryDocumentModification::class,
        'thelia.admin.product.creation' => ProductCreationForm::class,
        'thelia.admin.product.modification' => ProductModificationForm::class,
        'thelia.admin.product.clone' => ProductCloneForm::class,
        'thelia.admin.product.combination.generation' => ProductCombinationGenerationForm::class,
        'thelia.admin.product.image.modification' => ProductImageModification::class,
        'thelia.admin.product.document.modification' => ProductDocumentModification::class,
        'thelia.admin.product.sale_elements.update' => ProductSaleElementUpdateForm::class,
        'thelia.admin.product.sale_elements.default_update' => ProductDefaultSaleElementUpdateForm::class,
        'thelia.admin.folder.creation' => FolderCreationForm::class,
        'thelia.admin.folder.modification' => FolderModificationForm::class,
        'thelia.admin.folder.image.modification' => FolderImageModification::class,
        'thelia.admin.folder.document.modification' => FolderDocumentModification::class,
        'thelia.admin.content.creation' => ContentCreationForm::class,
        'thelia.admin.content.modification' => ContentModificationForm::class,
        'thelia.admin.content.image.modification' => ContentImageModification::class,
        'thelia.admin.content.document.modification' => ContentDocumentModification::class,
        'thelia.admin.brand.creation' => BrandCreationForm::class,
        'thelia.admin.brand.modification' => BrandModificationForm::class,
        'thelia.admin.brand.image.modification' => BrandImageModification::class,
        'thelia.admin.brand.document.modification' => BrandDocumentModification::class,
        'thelia.admin.attribute.creation' => AttributeCreationForm::class,
        'thelia.admin.attribute.modification' => AttributeModificationForm::class,
        'thelia.admin.attribute_av.creation' => AttributeAvCreationForm::class,
        'thelia.admin.feature.creation' => FeatureCreationForm::class,
        'thelia.admin.feature.modification' => FeatureModificationForm::class,
        'thelia.admin.feature_av.creation' => FeatureAvCreationForm::class,
        'thelia.admin.template.creation' => TemplateCreationForm::class,
        'thelia.admin.template.modification' => TemplateModificationForm::class,
        'thelia.admin.country.creation' => CountryCreationForm::class,
        'thelia.admin.country.modification' => CountryModificationForm::class,
        'thelia.admin.state.creation' => StateCreationForm::class,
        'thelia.admin.state.modification' => StateModificationForm::class,
        'thelia.admin.area.create' => AreaCreateForm::class,
        'thelia.admin.area.modification' => AreaModificationForm::class,
        'thelia.admin.area.country' => AreaCountryForm::class,
        'thelia.admin.area.postage' => AreaPostageForm::class,
        'thelia.admin.area.delete_country' => AreaDeleteCountryForm::class,
        'thelia.admin.tax.creation' => TaxCreationForm::class,
        'thelia.admin.tax.modification' => TaxModificationForm::class,
        'thelia.admin.tax_rule.creation' => TaxRuleCreationForm::class,
        'thelia.admin.tax_rule.modification' => TaxRuleModificationForm::class,
        'thelia.admin.tax_rule.tax_list_update' => TaxRuleTaxListUpdateForm::class,
        'thelia.admin.profile.creation' => ProfileCreationForm::class,
        'thelia.admin.profile.modification' => ProfileModificationForm::class,
        'thelia.admin.profile.resource.access.modification' => ProfileUpdateResourceAccessForm::class,
        'thelia.admin.profile.module.access.modification' => ProfileUpdateModuleAccessForm::class,
        'thelia.admin.administrator.creation' => AdministratorCreationForm::class,
        'thelia.admin.administrator.modification' => AdministratorModificationForm::class,
        'thelia.admin.currency.creation' => CurrencyCreationForm::class,
        'thelia.admin.currency.modification' => CurrencyModificationForm::class,
        'thelia.admin.coupon.creation' => CouponCreationForm::class,
        'thelia.admin.order_status.creation' => OrderStatusCreationForm::class,
        'thelia.admin.order_status.modification' => OrderStatusModificationForm::class,
        'thelia.admin.sale.creation' => SaleCreationForm::class,
        'thelia.admin.sale.modification' => SaleModificationForm::class,
        'thelia.admin.module.modification' => ModuleModificationForm::class,
        'thelia.admin.module.install' => ModuleInstallForm::class,
        'thelia.admin.module.image.modification' => ModuleImageModification::class,
        'thelia.admin.hook.creation' => HookCreationForm::class,
        'thelia.admin.hook.modification' => HookModificationForm::class,
        'thelia.admin.module_hook.creation' => ModuleHookCreationForm::class,
        'thelia.admin.module_hook.modification' => ModuleHookModificationForm::class,
        'thelia.admin.message.creation' => MessageCreationForm::class,
        'thelia.admin.message.modification' => MessageModificationForm::class,
        'thelia.admin.message.send' => MessageSendSampleForm::class,
        'thelia.admin.mailing.modification' => MailingSystemModificationForm::class,
        'thelia.admin.config.creation' => ConfigCreationForm::class,
        'thelia.admin.config.modification' => ConfigModificationForm::class,
        'thelia.admin.translations.customer_title' => TranslationsCustomerTitleForm::class,
    ]);
};
