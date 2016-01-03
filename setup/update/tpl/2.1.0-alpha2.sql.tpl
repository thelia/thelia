SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

UPDATE `config` SET `name`='form_firewall_active' WHERE `name`='from_firewall_active';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'cart.use_persistent_cookie', '1', 0, 0, NOW(), NOW()),
(@max_id + 2, 'cart.cookie_name', 'thelia_cart', 0, 0, NOW(), NOW()),
(@max_id + 3, 'cart.cookie_lifetime', '31536060', 0, 0, NOW(), NOW()),
(@max_id + 4, 'allow_slash_ended_uri', 1, 0, 0, NOW(), NOW())
;


INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Use a persistent cookie to keep track of customer cart' locale=$locale}, NULL, NULL, NULL),
(@max_id + 2, '{$locale}', {intl l='Name the cart cookie' locale=$locale}, NULL, NULL, NULL),
(@max_id + 3, '{$locale}', {intl l='Life time of the cart cookie in the customer browser, in seconds' locale=$locale}, NULL, NULL, NULL),
(@max_id + 4, '{$locale}', {intl l='Allow slash ended uri' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

DELETE FROM `config` WHERE `name`='currency_rate_update_url';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  (@max_id+1, 'order-edit.cart-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+2, 'order-edit.cart-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+3, 'order-edit.bill-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+4, 'order-edit.bill-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+5, 'order-edit.before-order-product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+6, 'order-edit.before-order-product-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+7, 'order-edit.after-order-product-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+8, 'order-edit.after-order-product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+9, 'sales.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+10, 'sales.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+11, 'sales.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+12, 'sales.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+13, 'sale.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+14, 'sale.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+15, 'sales.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+16, 'product.combinations-row', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (@max_id+17, 'main.before-content', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+18, 'main.after-content', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
  (@max_id+1, '{$locale}', {intl l='Order - cart top' locale=$locale}, '', ''),
  (@max_id+2, '{$locale}', {intl l='Order - cart bottom' locale=$locale}, '', ''),
  (@max_id+3, '{$locale}', {intl l='Order - bill top' locale=$locale}, '', ''),
  (@max_id+4, '{$locale}', {intl l='Order - bill bottom' locale=$locale}, '', ''),
  (@max_id+5, '{$locale}', {intl l='Order - Before product list' locale=$locale}, '', ''),
  (@max_id+6, '{$locale}', {intl l='Order - Before starting product row' locale=$locale}, '', ''),
  (@max_id+7, '{$locale}', {intl l='Order - After closing product row' locale=$locale}, '', ''),
  (@max_id+8, '{$locale}', {intl l='Order - After product list' locale=$locale}, '', ''),
  (@max_id+9, '{$locale}', {intl l='Sales - at the top' locale=$locale}, '', ''),
  (@max_id+10, '{$locale}', {intl l='Sales - table header' locale=$locale}, '', ''),
  (@max_id+11, '{$locale}', {intl l='Sales - table row' locale=$locale}, '', ''),
  (@max_id+12, '{$locale}', {intl l='Sales - at the bottom' locale=$locale}, '', ''),
  (@max_id+13, '{$locale}', {intl l='Sale - create form' locale=$locale}, '', ''),
  (@max_id+14, '{$locale}', {intl l='Sale - delete form' locale=$locale}, '', ''),
  (@max_id+15, '{$locale}', {intl l='Sales - JavaScript' locale=$locale}, '', ''),
  (@max_id+16, '{$locale}', {intl l='Product - at the bottom of a product combination' locale=$locale}, '', ''),
  (@max_id+17, '{$locale}', {intl l='Layout - Before the main content' locale=$locale}, '', ''),
  (@max_id+18, '{$locale}', {intl l='Admin layout - After the main content' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

# ======================================================================================================================
# Module version, min & max Thelia version supported
# ======================================================================================================================

ALTER TABLE `module`
  ADD COLUMN `category` VARCHAR(50) DEFAULT 'classic' NOT NULL
  AFTER `type`
;

UPDATE `module` SET `category` = 'classic' WHERE `type` = 1;
UPDATE `module` SET `category` = 'delivery' WHERE `type` = 2;
UPDATE `module` SET `category` = 'payment' WHERE `type` = 3;

ALTER TABLE `module`
  ADD COLUMN `version` VARCHAR(10) DEFAULT '' NOT NULL
  AFTER `code`
;

UPDATE `country` SET `isoalpha2` = 'BH' WHERE `isoalpha3` = 'BHR';
UPDATE `country` SET `isoalpha2` = 'MG' WHERE `isoalpha3` = 'MDG';


SELECT @max_id := IFNULL(MAX(`id`),0) FROM `module`;
SELECT @max_classic_position := IFNULL(MAX(`position`),0) FROM `module` WHERE `type`=1;


INSERT INTO `module` (`id`, `code`, `type`, `activate`, `position`, `full_namespace`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'TheliaSmarty', 1, 1, @max_classic_position+1, 'TheliaSmarty\\TheliaSmarty', NOW(), NOW()),
(@max_id+2, 'VirtualProductControl', 1, 1, @max_classic_position+2, 'VirtualProductControl\\VirtualProductControl', NOW(), NOW())
;

INSERT INTO  `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
{foreach $locales as $locale}
(@max_id+1, '{$locale}', {intl l='Smarty template engine integration' locale=$locale}, NULL,  NULL,  NULL),
(@max_id+2, '{$locale}', {intl l='Virtual Product Controller' locale=$locale}, {intl l='Check if a virtual product delivery module is enabled if at least one product is virtual' locale=$locale},  NULL,  NULL){if ! $locale@last},{/if}

{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;
