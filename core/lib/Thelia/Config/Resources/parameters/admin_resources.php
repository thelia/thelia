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

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('admin.resources', [
            'thelia' => [
                'SUPERADMINISTRATOR' => 'SUPERADMINISTRATOR',
                'ADDRESS' => 'admin.address',
                'ADMINISTRATOR' => 'admin.configuration.administrator',
                'ADVANCED_CONFIGURATION' => 'admin.configuration.advanced',
                'AREA' => 'admin.configuration.area',
                'ATTRIBUTE' => 'admin.configuration.attribute',
                'BRAND' => 'admin.brand',
                'CATEGORY' => 'admin.category',
                'CONFIG' => 'admin.configuration',
                'CONTENT' => 'admin.content',
                'COUNTRY' => 'admin.configuration.country',
                'STATE' => 'admin.configuration.state',
                'COUPON' => 'admin.coupon',
                'CURRENCY' => 'admin.configuration.currency',
                'CUSTOMER' => 'admin.customer',
                'FEATURE' => 'admin.configuration.feature',
                'FOLDER' => 'admin.folder',
                'HOME' => 'admin.home',
                'LANGUAGE' => 'admin.configuration.language',
                'MAILING_SYSTEM' => 'admin.configuration.mailing-system',
                'MESSAGE' => 'admin.configuration.message',
                'MODULE' => 'admin.module',
                'HOOK' => 'admin.hook',
                'MODULE_HOOK' => 'admin.module-hook',
                'ORDER' => 'admin.order',
                'ORDER_STATUS' => 'admin.configuration.order-status',
                'PRODUCT' => 'admin.product',
                'PROFILE' => 'admin.configuration.profile',
                'SHIPPING_ZONE' => 'admin.configuration.shipping-zone',
                'TAX' => 'admin.configuration.tax',
                'TEMPLATE' => 'admin.configuration.template',
                'SYSTEM_LOG' => 'admin.configuration.system-logs',
                'ADMIN_LOG' => 'admin.configuration.admin-logs',
                'STORE' => 'admin.configuration.store',
                'TRANSLATIONS' => 'admin.configuration.translations',
                'UPDATE' => 'admin.configuration.update',
                'EXPORT' => 'admin.export',
                'IMPORT' => 'admin.import',
                'TOOLS' => 'admin.tools',
                'SALES' => 'admin.sales',
                'TITLE' => 'admin.customer.title',
            ],
        ]);
};
