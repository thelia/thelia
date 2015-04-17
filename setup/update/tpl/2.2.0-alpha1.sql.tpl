SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- order status

SELECT @max_id := MAX(`id`) FROM `order_status`;

INSERT INTO `order_status` VALUES
  (@max_id + 1, "refunded", NOW(), NOW())
;

INSERT INTO `order_status_i18n` VALUES
{foreach $locales as $locale}
(@max_id + 1, "{$locale}", {intl l="Refunded" locale=$locale}, "", "", ""){if ! $locale@last},{/if}

{/foreach}
;

-- new column in admin_log

ALTER TABLE `admin_log` ADD `resource_id` INTEGER AFTER `resource` ;

-- new config

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'customer_change_email', '0', 0, 0, NOW(), NOW()),
(@max_id + 2, 'customer_confirm_email', '0', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Allow customers to change their email. 1 for yes, 0 for no' locale=$locale}, NULL, NULL, NULL),
(@max_id + 2, '{$locale}', {intl l='Ask the customers to confirm their email, 1 for yes, 0 for no' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- country area table

create table IF NOT EXISTS `country_area`
(
    `country_id` INTEGER NOT NULL,
    `area_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    INDEX `country_area_area_id_idx` (`area_id`),
    INDEX `fk_country_area_country_id_idx` (`country_id`),
    CONSTRAINT `fk_country_area_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_country_area_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- Initialize the table with existing data
INSERT INTO `country_area` (`country_id`, `area_id`, `created_at`, `updated_at`) select `id`, `area_id`, NOW(), NOW() FROM `country` WHERE `area_id` IS NOT NULL;

-- Remove area_id column from country table
ALTER TABLE `country` DROP FOREIGN KEY `fk_country_area_id`;
ALTER TABLE `country` DROP KEY `idx_country_area_id`;
ALTER TABLE `country` DROP COLUMN `area_id`;
ALTER TABLE `category` ADD COLUMN `default_template_id` INTEGER AFTER  `position`;

-- new hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'profile.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'profile.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'import.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'import.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'export.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'export.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 7, 'category-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 8, 'category-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 9, 'brand-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 10, 'brand-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 11, 'attribute-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 12, 'attribute-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 13, 'currency-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 14, 'currency-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 15, 'country-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 16, 'country-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 17, 'content-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 18, 'content-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 19, 'feature-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 20, 'feature-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 21, 'document-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 22, 'document-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 23, 'customer-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 24, 'customer-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 25, 'image-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 26, 'image-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 27, 'hook-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 28, 'hook-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 29, 'folder-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 30, 'folder-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 31, 'module-hook-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 32, 'module-hook-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 33, 'module-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 34, 'module-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 35, 'message-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 36, 'message-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 37, 'profile-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 38, 'profile-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 39, 'product-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 40, 'product-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 41, 'order-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 42, 'order-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 43, 'shipping-zones-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 44, 'shipping-zones-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 45, 'shipping-configuration-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 46, 'shipping-configuration-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 47, 'sale-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 48, 'sale-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 49, 'variables-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 50, 'variables-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 51, 'template-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 52, 'template-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 53, 'tax-rule-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 54, 'tax-rule-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 55, 'tax-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 56, 'tax-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 57, 'order-edit.product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 58, 'order.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 59, 'account-order.product', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 60, 'tab-seo.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 61, 'tab-seo.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 62, 'tab-image.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 63, 'tab-image.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 64, 'tab-document.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 65, 'tab-document.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Profile - table header' locale=$locale}, '', ''),
(@max_id + 2, '{$locale}', {intl l='Profile - table row' locale=$locale}, '', ''),
(@max_id + 3, '{$locale}', {intl l='Import - table header' locale=$locale}, '', ''),
(@max_id + 4, '{$locale}', {intl l='Import - table row' locale=$locale}, '', ''),
(@max_id + 5, '{$locale}', {intl l='Export - table header' locale=$locale}, '', ''),
(@max_id + 6, '{$locale}', {intl l='Export - table row' locale=$locale}, '', ''),
(@max_id + 7, '{$locale}', {intl l='Category edit - top' locale=$locale}, '', ''),
(@max_id + 8, '{$locale}', {intl l='Category edit - bottom' locale=$locale}, '', ''),
(@max_id + 9, '{$locale}', {intl l='Brand edit - top' locale=$locale}, '', ''),
(@max_id + 10, '{$locale}', {intl l='Brand edit - bottom' locale=$locale}, '', ''),
(@max_id + 11, '{$locale}', {intl l='Attribute edit - top' locale=$locale}, '', ''),
(@max_id + 12, '{$locale}', {intl l='Attribute edit - bottom' locale=$locale}, '', ''),
(@max_id + 13, '{$locale}', {intl l='Currency edit - top' locale=$locale}, '', ''),
(@max_id + 14, '{$locale}', {intl l='Currency edit - bottom' locale=$locale}, '', ''),
(@max_id + 15, '{$locale}', {intl l='Country edit - top' locale=$locale}, '', ''),
(@max_id + 16, '{$locale}', {intl l='Country edit - bottom' locale=$locale}, '', ''),
(@max_id + 17, '{$locale}', {intl l='Content edit - top' locale=$locale}, '', ''),
(@max_id + 18, '{$locale}', {intl l='Content edit - bottom' locale=$locale}, '', ''),
(@max_id + 19, '{$locale}', {intl l='Feature edit - top' locale=$locale}, '', ''),
(@max_id + 20, '{$locale}', {intl l='Feature edit - bottom' locale=$locale}, '', ''),
(@max_id + 21, '{$locale}', {intl l='Document edit - top' locale=$locale}, '', ''),
(@max_id + 22, '{$locale}', {intl l='Document edit - bottom' locale=$locale}, '', ''),
(@max_id + 23, '{$locale}', {intl l='Client edit - top' locale=$locale}, '', ''),
(@max_id + 24, '{$locale}', {intl l='Client edit - bottom' locale=$locale}, '', ''),
(@max_id + 25, '{$locale}', {intl l='Image edit - top' locale=$locale}, '', ''),
(@max_id + 26, '{$locale}', {intl l='Image edit - bottom' locale=$locale}, '', ''),
(@max_id + 27, '{$locale}', {intl l='Hook edit - top' locale=$locale}, '', ''),
(@max_id + 28, '{$locale}', {intl l='Hook edit - bottom' locale=$locale}, '', ''),
(@max_id + 29, '{$locale}', {intl l='Folder edit - top' locale=$locale}, '', ''),
(@max_id + 30, '{$locale}', {intl l='Folder edit - bottom' locale=$locale}, '', ''),
(@max_id + 31, '{$locale}', {intl l='Module hook edit - top' locale=$locale}, '', ''),
(@max_id + 32, '{$locale}', {intl l='Module hook edit - bottom' locale=$locale}, '', ''),
(@max_id + 33, '{$locale}', {intl l='Module edit - top' locale=$locale}, '', ''),
(@max_id + 34, '{$locale}', {intl l='Module edit - bottom' locale=$locale}, '', ''),
(@max_id + 35, '{$locale}', {intl l='Message edit - top' locale=$locale}, '', ''),
(@max_id + 36, '{$locale}', {intl l='Message edit - bottom' locale=$locale}, '', ''),
(@max_id + 37, '{$locale}', {intl l='Profile edit - top' locale=$locale}, '', ''),
(@max_id + 38, '{$locale}', {intl l='Profile edit - bottom' locale=$locale}, '', ''),
(@max_id + 39, '{$locale}', {intl l='Product edit - top' locale=$locale}, '', ''),
(@max_id + 40, '{$locale}', {intl l='Product edit - bottom' locale=$locale}, '', ''),
(@max_id + 41, '{$locale}', {intl l='Order edit - top' locale=$locale}, '', ''),
(@max_id + 42, '{$locale}', {intl l='Order edit - bottom' locale=$locale}, '', ''),
(@max_id + 43, '{$locale}', {intl l='Shipping zones edit - top' locale=$locale}, '', ''),
(@max_id + 44, '{$locale}', {intl l='Shipping zones edit - bottom' locale=$locale}, '', ''),
(@max_id + 45, '{$locale}', {intl l='Shipping configuration edit - top' locale=$locale}, '', ''),
(@max_id + 46, '{$locale}', {intl l='Shipping configuration edit - bottom' locale=$locale}, '', ''),
(@max_id + 47, '{$locale}', {intl l='Sale edit - top' locale=$locale}, '', ''),
(@max_id + 48, '{$locale}', {intl l='Sale edit - bottom' locale=$locale}, '', ''),
(@max_id + 49, '{$locale}', {intl l='Variable edit - top' locale=$locale}, '', ''),
(@max_id + 50, '{$locale}', {intl l='Variable edit - bottom' locale=$locale}, '', ''),
(@max_id + 51, '{$locale}', {intl l='Template edit - top' locale=$locale}, '', ''),
(@max_id + 52, '{$locale}', {intl l='Template edit - bottom' locale=$locale}, '', ''),
(@max_id + 53, '{$locale}', {intl l='Tax rule edit - top' locale=$locale}, '', ''),
(@max_id + 54, '{$locale}', {intl l='Tax rule edit - bottom' locale=$locale}, '', ''),
(@max_id + 55, '{$locale}', {intl l='Tax edit - top' locale=$locale}, '', ''),
(@max_id + 56, '{$locale}', {intl l='Tax edit - bottom' locale=$locale}, '', ''),
(@max_id + 57, '{$locale}', {intl l='Order edit - displayed after product information' locale=$locale}, '', ''),
(@max_id + 58, '{$locale}', {intl l='Order - Tab' locale=$locale}, '', ''),
(@max_id + 59, '{$locale}', {intl l='Order details - after product' locale=$locale}, '', ''),
(@max_id + 60, '{$locale}', {intl l='Tab SEO - top' locale=$locale}, '', ''),
(@max_id + 61, '{$locale}', {intl l='Tab SEO - bottom' locale=$locale}, '', ''),
(@max_id + 62, '{$locale}', {intl l='Tab image - top' locale=$locale}, '', ''),
(@max_id + 63, '{$locale}', {intl l='Tab image - bottom' locale=$locale}, '', ''),
(@max_id + 64, '{$locale}', {intl l='Tab document - top' locale=$locale}, '', ''),
(@max_id + 65, '{$locale}', {intl l='Tab document - bottom' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

-- Fix attribute_template and feature_template relations
ALTER TABLE `attribute_template` DROP FOREIGN KEY `fk_attribute_template`; ALTER TABLE `attribute_template` ADD CONSTRAINT `fk_attribute_template` FOREIGN KEY (`template_id`) REFERENCES `template`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `feature_template` DROP FOREIGN KEY `fk_feature_template`; ALTER TABLE `feature_template` ADD CONSTRAINT `fk_feature_template` FOREIGN KEY (`template_id`) REFERENCES `template`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;