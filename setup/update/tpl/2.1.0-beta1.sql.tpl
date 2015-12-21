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
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Life time of the session cookie in the customer browser, in seconds' locale=$locale}, NULL, NULL, NULL),
(@max_id + 2, '{$locale}', {intl l='Show error message instead of a white page on a server error' locale=$locale}, NULL, NULL, NULL),
(@max_id + 3, '{$locale}', {intl l='Filename of the error page' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
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
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Category page - before the main content area' locale=$locale}, '', ''),
(@max_id + 2, '{$locale}', {intl l='Category page - after the main content area' locale=$locale}, '', ''),
(@max_id + 3, '{$locale}', {intl l='Content page - before the main content area' locale=$locale}, '', ''),
(@max_id + 4, '{$locale}', {intl l='Content page - after the main content area' locale=$locale}, '', ''),
(@max_id + 5, '{$locale}', {intl l='Folder page - before the main content area' locale=$locale}, '', ''),
(@max_id + 6, '{$locale}', {intl l='Folder page - after the main content area' locale=$locale}, '', ''),
(@max_id + 7, '{$locale}', {intl l='Brands page - at the top' locale=$locale}, '', ''),
(@max_id + 8, '{$locale}', {intl l='Brands page - at the bottom' locale=$locale}, '', ''),
(@max_id + 9, '{$locale}', {intl l='Brands page - at the top of the main area' locale=$locale}, '', ''),
(@max_id + 10, '{$locale}', {intl l='Brands page - at the bottom of the main area' locale=$locale}, '', ''),
(@max_id + 11, '{$locale}', {intl l='Brands page - before the main content area' locale=$locale}, '', ''),
(@max_id + 12, '{$locale}', {intl l='Brands page - after the main content area' locale=$locale}, '', ''),
(@max_id + 13, '{$locale}', {intl l='Brands page - CSS stylesheet' locale=$locale}, '', ''),
(@max_id + 14, '{$locale}', {intl l='Brands page - after javascript include' locale=$locale}, '', ''),
(@max_id + 15, '{$locale}', {intl l='Brands page - javascript initialization' locale=$locale}, '', ''),
(@max_id + 16, '{$locale}', {intl l='Brands page - at the top of the sidebar' locale=$locale}, '', ''),
(@max_id + 17, '{$locale}', {intl l='Brands page - the body of the sidebar' locale=$locale}, '', ''),
(@max_id + 18, '{$locale}', {intl l='Brands page - at the bottom of the sidebar' locale=$locale}, '', ''),
(@max_id + 19, '{$locale}', {intl l='Order details - at the top' locale=$locale}, '', ''),
(@max_id + 20, '{$locale}', {intl l='Order details - additional information' locale=$locale}, '', ''),
(@max_id + 21, '{$locale}', {intl l='Order details - after global information' locale=$locale}, '', ''),
(@max_id + 22, '{$locale}', {intl l='Order details - additional delivery information' locale=$locale}, '', ''),
(@max_id + 23, '{$locale}', {intl l='Order details - delivery address' locale=$locale}, '', ''),
(@max_id + 24, '{$locale}', {intl l='Order details - additional invoice information' locale=$locale}, '', ''),
(@max_id + 25, '{$locale}', {intl l='Order details - invoice address' locale=$locale}, '', ''),
(@max_id + 26, '{$locale}', {intl l='Order details - after addresses' locale=$locale}, '', ''),
(@max_id + 27, '{$locale}', {intl l='Order details - before products list' locale=$locale}, '', ''),
(@max_id + 28, '{$locale}', {intl l='Order details - additional product information' locale=$locale}, '', ''),
(@max_id + 29, '{$locale}', {intl l='Order details - after products list' locale=$locale}, '', ''),
(@max_id + 30, '{$locale}', {intl l='Order details - after products' locale=$locale}, '', ''),
(@max_id + 31, '{$locale}', {intl l='Order details - at the bottom' locale=$locale}, '', ''),
(@max_id + 32, '{$locale}', {intl l='Order details - CSS stylesheet' locale=$locale}, '', ''),
(@max_id + 33, '{$locale}', {intl l='Order details - after javascript include' locale=$locale}, '', ''),
(@max_id + 34, '{$locale}', {intl l='Order details - javascript initialization' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
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
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Category - Tab' locale=$locale}, '', ''),
(@max_id + 2, '{$locale}', {intl l='Product - Tab' locale=$locale}, '', ''),
(@max_id + 3, '{$locale}', {intl l='Folder - Tab' locale=$locale}, '', ''),
(@max_id + 4, '{$locale}', {intl l='Content - Tab' locale=$locale}, '', ''),
(@max_id + 5, '{$locale}', {intl l='Brand - Tab' locale=$locale}, '', ''),
(@max_id + 6, '{$locale}', {intl l='Order edit - delivery address' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;


SET FOREIGN_KEY_CHECKS = 1;

