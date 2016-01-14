SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

DELETE FROM `config` WHERE `name`='session_config.handlers';

CREATE TABLE IF NOT EXISTS `api`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255),
    `api_key` VARCHAR(100),
    `profile_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_api_profile_id` (`profile_id`),
    CONSTRAINT `fk_api_profile_id`
        FOREIGN KEY (`profile_id`)
        REFERENCES `profile` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

-- Add the session_config.lifetime configuration variable
INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'session_config.lifetime', '0', 0, 0, NOW(), NOW()),
(@max_id + 2, 'error_message.show', '1', 0, 0, NOW(), NOW()),
(@max_id + 3, 'error_message.page_name', 'error.html', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max_id + 1, 'en_US', 'Life time of the session cookie in the customer browser, in seconds', NULL, NULL, NULL),
(@max_id + 2, 'en_US', 'Show error message instead of a white page on a server error', NULL, NULL, NULL),
(@max_id + 3, 'en_US', 'Filename of the error page', NULL, NULL, NULL),
(@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
(@max_id + 2, 'es_ES', NULL, NULL, NULL, NULL),
(@max_id + 3, 'es_ES', NULL, NULL, NULL, NULL)
;

-- Hide the session_config.handlers configuration variable
UPDATE `config` SET `secured`=1, `hidden`=1 where `name`='session_config.handlers';

-- Hooks

-- front hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`)
  VALUES
(@max_id + 1, 'category.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'category.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'content.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'content.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'folder.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'folder.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 7, 'brand.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 8, 'brand.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 9, 'brand.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 10, 'brand.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 11, 'brand.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 12, 'brand.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 13, 'brand.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 14, 'brand.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 15, 'brand.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 16, 'brand.sidebar-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 17, 'brand.sidebar-body', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 18, 'brand.sidebar-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 19, 'account-order.top', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 20, 'account-order.information', 1, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 21, 'account-order.after-information', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 22, 'account-order.delivery-information', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 23, 'account-order.delivery-address', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 24, 'account-order.invoice-information', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 25, 'account-order.invoice-address', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 26, 'account-order.after-addresses', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 27, 'account-order.products-top', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 28, 'account-order.product-extra', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 29, 'account-order.products-bottom', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 30, 'account-order.after-products', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 31, 'account-order.bottom', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 32, 'account-order.stylesheet', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 33, 'account-order.after-javascript-include', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 34, 'account-order.javascript-initialization', 1,  0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'en_US', 'Category page - before the main content area', '', ''),
(@max_id + 2, 'en_US', 'Category page - after the main content area', '', ''),
(@max_id + 3, 'en_US', 'Content page - before the main content area', '', ''),
(@max_id + 4, 'en_US', 'Content page - after the main content area', '', ''),
(@max_id + 5, 'en_US', 'Folder page - before the main content area', '', ''),
(@max_id + 6, 'en_US', 'Folder page - after the main content area', '', ''),
(@max_id + 7, 'en_US', 'Brands page - at the top', '', ''),
(@max_id + 8, 'en_US', 'Brands page - at the bottom', '', ''),
(@max_id + 9, 'en_US', 'Brands page - at the top of the main area', '', ''),
(@max_id + 10, 'en_US', 'Brands page - at the bottom of the main area', '', ''),
(@max_id + 11, 'en_US', 'Brands page - before the main content area', '', ''),
(@max_id + 12, 'en_US', 'Brands page - after the main content area', '', ''),
(@max_id + 13, 'en_US', 'Brands page - CSS stylesheet', '', ''),
(@max_id + 14, 'en_US', 'Brands page - after javascript include', '', ''),
(@max_id + 15, 'en_US', 'Brands page - javascript initialization', '', ''),
(@max_id + 16, 'en_US', 'Brands page - at the top of the sidebar', '', ''),
(@max_id + 17, 'en_US', 'Brands page - the body of the sidebar', '', ''),
(@max_id + 18, 'en_US', 'Brands page - at the bottom of the sidebar', '', ''),
(@max_id + 19, 'en_US', 'Order details - at the top', '', ''),
(@max_id + 20, 'en_US', 'Order details - additional information', '', ''),
(@max_id + 21, 'en_US', 'Order details - after global information', '', ''),
(@max_id + 22, 'en_US', 'Order details - additional delivery information', '', ''),
(@max_id + 23, 'en_US', 'Order details - delivery address', '', ''),
(@max_id + 24, 'en_US', 'Order details - additional invoice information', '', ''),
(@max_id + 25, 'en_US', 'Order details - invoice address', '', ''),
(@max_id + 26, 'en_US', 'Order details - after addresses', '', ''),
(@max_id + 27, 'en_US', 'Order details - before products list', '', ''),
(@max_id + 28, 'en_US', 'Order details - additional product information', '', ''),
(@max_id + 29, 'en_US', 'Order details - after products list', '', ''),
(@max_id + 30, 'en_US', 'Order details - after products', '', ''),
(@max_id + 31, 'en_US', 'Order details - at the bottom', '', ''),
(@max_id + 32, 'en_US', 'Order details - CSS stylesheet', '', ''),
(@max_id + 33, 'en_US', 'Order details - after javascript include', '', ''),
(@max_id + 34, 'en_US', 'Order details - javascript initialization', '', ''),
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
(@max_id + 34, 'es_ES', NULL, '', '')
;


-- admin hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'category.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'product.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'folder.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'content.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'brand.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'order-edit.bill-delivery-address', 2, 1, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'en_US', 'Category - Tab', '', ''),
(@max_id + 2, 'en_US', 'Product - Tab', '', ''),
(@max_id + 3, 'en_US', 'Folder - Tab', '', ''),
(@max_id + 4, 'en_US', 'Content - Tab', '', ''),
(@max_id + 5, 'en_US', 'Brand - Tab', '', ''),
(@max_id + 6, 'en_US', 'Order edit - delivery address', '', ''),
(@max_id + 1, 'es_ES', NULL, '', ''),
(@max_id + 2, 'es_ES', NULL, '', ''),
(@max_id + 3, 'es_ES', NULL, '', ''),
(@max_id + 4, 'es_ES', NULL, '', ''),
(@max_id + 5, 'es_ES', NULL, '', ''),
(@max_id + 6, 'es_ES', NULL, '', '')
;


SET FOREIGN_KEY_CHECKS = 1;

