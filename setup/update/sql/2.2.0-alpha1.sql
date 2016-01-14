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
(@max_id + 1, "en_US", 'Refunded', "", "", ""),
(@max_id + 1, "es_ES", NULL, "", "", "")
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
(@max_id + 1, 'en_US', 'Allow customers to change their email. 1 for yes, 0 for no', NULL, NULL, NULL),
(@max_id + 2, 'en_US', 'Ask the customers to confirm their email, 1 for yes, 0 for no', NULL, NULL, NULL),
(@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
(@max_id + 2, 'es_ES', NULL, NULL, NULL, NULL)
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
(@max_id + 1, 'en_US', 'Profile - table header', '', ''),
(@max_id + 2, 'en_US', 'Profile - table row', '', ''),
(@max_id + 3, 'en_US', 'Import - table header', '', ''),
(@max_id + 4, 'en_US', 'Import - table row', '', ''),
(@max_id + 5, 'en_US', 'Export - table header', '', ''),
(@max_id + 6, 'en_US', 'Export - table row', '', ''),
(@max_id + 7, 'en_US', 'Category edit - top', '', ''),
(@max_id + 8, 'en_US', 'Category edit - bottom', '', ''),
(@max_id + 9, 'en_US', 'Brand edit - top', '', ''),
(@max_id + 10, 'en_US', 'Brand edit - bottom', '', ''),
(@max_id + 11, 'en_US', 'Attribute edit - top', '', ''),
(@max_id + 12, 'en_US', 'Attribute edit - bottom', '', ''),
(@max_id + 13, 'en_US', 'Currency edit - top', '', ''),
(@max_id + 14, 'en_US', 'Currency edit - bottom', '', ''),
(@max_id + 15, 'en_US', 'Country edit - top', '', ''),
(@max_id + 16, 'en_US', 'Country edit - bottom', '', ''),
(@max_id + 17, 'en_US', 'Content edit - top', '', ''),
(@max_id + 18, 'en_US', 'Content edit - bottom', '', ''),
(@max_id + 19, 'en_US', 'Feature edit - top', '', ''),
(@max_id + 20, 'en_US', 'Feature edit - bottom', '', ''),
(@max_id + 21, 'en_US', 'Document edit - top', '', ''),
(@max_id + 22, 'en_US', 'Document edit - bottom', '', ''),
(@max_id + 23, 'en_US', 'Client edit - top', '', ''),
(@max_id + 24, 'en_US', 'Client edit - bottom', '', ''),
(@max_id + 25, 'en_US', 'Image edit - top', '', ''),
(@max_id + 26, 'en_US', 'Image edit - bottom', '', ''),
(@max_id + 27, 'en_US', 'Hook edit - top', '', ''),
(@max_id + 28, 'en_US', 'Hook edit - bottom', '', ''),
(@max_id + 29, 'en_US', 'Folder edit - top', '', ''),
(@max_id + 30, 'en_US', 'Folder edit - bottom', '', ''),
(@max_id + 31, 'en_US', 'Module hook edit - top', '', ''),
(@max_id + 32, 'en_US', 'Module hook edit - bottom', '', ''),
(@max_id + 33, 'en_US', 'Module edit - top', '', ''),
(@max_id + 34, 'en_US', 'Module edit - bottom', '', ''),
(@max_id + 35, 'en_US', 'Message edit - top', '', ''),
(@max_id + 36, 'en_US', 'Message edit - bottom', '', ''),
(@max_id + 37, 'en_US', 'Profile edit - top', '', ''),
(@max_id + 38, 'en_US', 'Profile edit - bottom', '', ''),
(@max_id + 39, 'en_US', 'Product edit - top', '', ''),
(@max_id + 40, 'en_US', 'Product edit - bottom', '', ''),
(@max_id + 41, 'en_US', 'Order edit - top', '', ''),
(@max_id + 42, 'en_US', 'Order edit - bottom', '', ''),
(@max_id + 43, 'en_US', 'Shipping zones edit - top', '', ''),
(@max_id + 44, 'en_US', 'Shipping zones edit - bottom', '', ''),
(@max_id + 45, 'en_US', 'Shipping configuration edit - top', '', ''),
(@max_id + 46, 'en_US', 'Shipping configuration edit - bottom', '', ''),
(@max_id + 47, 'en_US', 'Sale edit - top', '', ''),
(@max_id + 48, 'en_US', 'Sale edit - bottom', '', ''),
(@max_id + 49, 'en_US', 'Variable edit - top', '', ''),
(@max_id + 50, 'en_US', 'Variable edit - bottom', '', ''),
(@max_id + 51, 'en_US', 'Template edit - top', '', ''),
(@max_id + 52, 'en_US', 'Template edit - bottom', '', ''),
(@max_id + 53, 'en_US', 'Tax rule edit - top', '', ''),
(@max_id + 54, 'en_US', 'Tax rule edit - bottom', '', ''),
(@max_id + 55, 'en_US', 'Tax edit - top', '', ''),
(@max_id + 56, 'en_US', 'Tax edit - bottom', '', ''),
(@max_id + 57, 'en_US', 'Order edit - displayed after product information', '', ''),
(@max_id + 58, 'en_US', 'Order - Tab', '', ''),
(@max_id + 59, 'en_US', 'Order details - after product', '', ''),
(@max_id + 60, 'en_US', 'Tab SEO - top', '', ''),
(@max_id + 61, 'en_US', 'Tab SEO - bottom', '', ''),
(@max_id + 62, 'en_US', 'Tab image - top', '', ''),
(@max_id + 63, 'en_US', 'Tab image - bottom', '', ''),
(@max_id + 64, 'en_US', 'Tab document - top', '', ''),
(@max_id + 65, 'en_US', 'Tab document - bottom', '', ''),
(@max_id + 1, 'es_ES', NULL, '', ''),
(@max_id + 2, 'es_ES', NULL, '', ''),
(@max_id + 3, 'es_ES', NULL, '', ''),
(@max_id + 4, 'es_ES', NULL, '', ''),
(@max_id + 5, 'es_ES', NULL, '', ''),
(@max_id + 6, 'es_ES', NULL, '', ''),
(@max_id + 7, 'es_ES', NULL, '', ''),
(@max_id + 8, 'es_ES', NULL, '', ''),
(@max_id + 9, 'es_ES', NULL, '', ''),
(@max_id + 10, 'es_ES', NULL, '', ''),
(@max_id + 11, 'es_ES', NULL, '', ''),
(@max_id + 12, 'es_ES', NULL, '', ''),
(@max_id + 13, 'es_ES', NULL, '', ''),
(@max_id + 14, 'es_ES', NULL, '', ''),
(@max_id + 15, 'es_ES', NULL, '', ''),
(@max_id + 16, 'es_ES', NULL, '', ''),
(@max_id + 17, 'es_ES', NULL, '', ''),
(@max_id + 18, 'es_ES', NULL, '', ''),
(@max_id + 19, 'es_ES', NULL, '', ''),
(@max_id + 20, 'es_ES', NULL, '', ''),
(@max_id + 21, 'es_ES', NULL, '', ''),
(@max_id + 22, 'es_ES', NULL, '', ''),
(@max_id + 23, 'es_ES', NULL, '', ''),
(@max_id + 24, 'es_ES', NULL, '', ''),
(@max_id + 25, 'es_ES', NULL, '', ''),
(@max_id + 26, 'es_ES', NULL, '', ''),
(@max_id + 27, 'es_ES', NULL, '', ''),
(@max_id + 28, 'es_ES', NULL, '', ''),
(@max_id + 29, 'es_ES', NULL, '', ''),
(@max_id + 30, 'es_ES', NULL, '', ''),
(@max_id + 31, 'es_ES', NULL, '', ''),
(@max_id + 32, 'es_ES', NULL, '', ''),
(@max_id + 33, 'es_ES', NULL, '', ''),
(@max_id + 34, 'es_ES', NULL, '', ''),
(@max_id + 35, 'es_ES', NULL, '', ''),
(@max_id + 36, 'es_ES', NULL, '', ''),
(@max_id + 37, 'es_ES', NULL, '', ''),
(@max_id + 38, 'es_ES', NULL, '', ''),
(@max_id + 39, 'es_ES', NULL, '', ''),
(@max_id + 40, 'es_ES', NULL, '', ''),
(@max_id + 41, 'es_ES', NULL, '', ''),
(@max_id + 42, 'es_ES', NULL, '', ''),
(@max_id + 43, 'es_ES', NULL, '', ''),
(@max_id + 44, 'es_ES', NULL, '', ''),
(@max_id + 45, 'es_ES', NULL, '', ''),
(@max_id + 46, 'es_ES', NULL, '', ''),
(@max_id + 47, 'es_ES', NULL, '', ''),
(@max_id + 48, 'es_ES', NULL, '', ''),
(@max_id + 49, 'es_ES', NULL, '', ''),
(@max_id + 50, 'es_ES', NULL, '', ''),
(@max_id + 51, 'es_ES', NULL, '', ''),
(@max_id + 52, 'es_ES', NULL, '', ''),
(@max_id + 53, 'es_ES', NULL, '', ''),
(@max_id + 54, 'es_ES', NULL, '', ''),
(@max_id + 55, 'es_ES', NULL, '', ''),
(@max_id + 56, 'es_ES', NULL, '', ''),
(@max_id + 57, 'es_ES', NULL, '', ''),
(@max_id + 58, 'es_ES', NULL, '', ''),
(@max_id + 59, 'es_ES', NULL, '', ''),
(@max_id + 60, 'es_ES', NULL, '', ''),
(@max_id + 61, 'es_ES', NULL, '', ''),
(@max_id + 62, 'es_ES', NULL, '', ''),
(@max_id + 63, 'es_ES', NULL, '', ''),
(@max_id + 64, 'es_ES', NULL, '', ''),
(@max_id + 65, 'es_ES', NULL, '', '')
;

-- Fix attribute_template and feature_template relations
ALTER TABLE `attribute_template` DROP FOREIGN KEY `fk_attribute_template`; ALTER TABLE `attribute_template` ADD CONSTRAINT `fk_attribute_template` FOREIGN KEY (`template_id`) REFERENCES `template`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `feature_template` DROP FOREIGN KEY `fk_feature_template`; ALTER TABLE `feature_template` ADD CONSTRAINT `fk_feature_template` FOREIGN KEY (`template_id`) REFERENCES `template`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;