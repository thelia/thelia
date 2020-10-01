SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.4' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

-- Additional hooks on order-invoice page

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'order-invoice.coupon-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2, 'order-invoice.payment-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3, 'delivery.product-list', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4, 'invoice.product-list', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+5, 'email-html.order-confirmation.product-list', 4, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+6, 'email-txt.order-confirmation.product-list', 4, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+7, 'account-order.product-list', 1, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
    (@max_id+1, 'ar_SA', NULL, NULL, NULL),
    (@max_id+2, 'ar_SA', NULL, NULL, NULL),
    (@max_id+3, 'ar_SA', NULL, NULL, NULL),
    (@max_id+4, 'ar_SA', NULL, NULL, NULL),
    (@max_id+5, 'ar_SA', NULL, NULL, NULL),
    (@max_id+6, 'ar_SA', NULL, NULL, NULL),
    (@max_id+7, 'ar_SA', NULL, NULL, NULL),
    (@max_id+1, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+2, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+3, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+4, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+5, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+6, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+7, 'cs_CZ', NULL, NULL, NULL),
    (@max_id+1, 'de_DE', NULL, NULL, NULL),
    (@max_id+2, 'de_DE', NULL, NULL, NULL),
    (@max_id+3, 'de_DE', NULL, NULL, NULL),
    (@max_id+4, 'de_DE', NULL, NULL, NULL),
    (@max_id+5, 'de_DE', NULL, NULL, NULL),
    (@max_id+6, 'de_DE', NULL, NULL, NULL),
    (@max_id+7, 'de_DE', NULL, NULL, NULL),
    (@max_id+1, 'el_GR', NULL, NULL, NULL),
    (@max_id+2, 'el_GR', NULL, NULL, NULL),
    (@max_id+3, 'el_GR', NULL, NULL, NULL),
    (@max_id+4, 'el_GR', NULL, NULL, NULL),
    (@max_id+5, 'el_GR', NULL, NULL, NULL),
    (@max_id+6, 'el_GR', NULL, NULL, NULL),
    (@max_id+7, 'el_GR', NULL, NULL, NULL),
    (@max_id+1, 'en_US', 'Order invoice page - bottom of coupon form', NULL, NULL),
    (@max_id+2, 'en_US', 'Order invoice page - bottom of payment form', NULL, NULL),
    (@max_id+3, 'en_US', 'Delivery - after product information', NULL, NULL),
    (@max_id+4, 'en_US', 'Invoice - after product information', NULL, NULL),
    (@max_id+5, 'en_US', 'Email html - order notification - after product information', NULL, NULL),
    (@max_id+6, 'en_US', 'Email txt - order notification - after product information', NULL, NULL),
    (@max_id+7, 'en_US', 'Account order - after product information', NULL, NULL),
    (@max_id+1, 'es_ES', NULL, NULL, NULL),
    (@max_id+2, 'es_ES', NULL, NULL, NULL),
    (@max_id+3, 'es_ES', NULL, NULL, NULL),
    (@max_id+4, 'es_ES', NULL, NULL, NULL),
    (@max_id+5, 'es_ES', NULL, NULL, NULL),
    (@max_id+6, 'es_ES', NULL, NULL, NULL),
    (@max_id+7, 'es_ES', NULL, NULL, NULL),
    (@max_id+1, 'fa_IR', NULL, NULL, NULL),
    (@max_id+2, 'fa_IR', NULL, NULL, NULL),
    (@max_id+3, 'fa_IR', NULL, NULL, NULL),
    (@max_id+4, 'fa_IR', NULL, NULL, NULL),
    (@max_id+5, 'fa_IR', NULL, NULL, NULL),
    (@max_id+6, 'fa_IR', NULL, NULL, NULL),
    (@max_id+7, 'fa_IR', NULL, NULL, NULL),
    (@max_id+1, 'fr_FR', NULL, NULL, NULL),
    (@max_id+2, 'fr_FR', NULL, NULL, NULL),
    (@max_id+3, 'fr_FR', NULL, NULL, NULL),
    (@max_id+4, 'fr_FR', NULL, NULL, NULL),
    (@max_id+5, 'fr_FR', NULL, NULL, NULL),
    (@max_id+6, 'fr_FR', NULL, NULL, NULL),
    (@max_id+7, 'fr_FR', NULL, NULL, NULL),
    (@max_id+1, 'hu_HU', NULL, NULL, NULL),
    (@max_id+2, 'hu_HU', NULL, NULL, NULL),
    (@max_id+3, 'hu_HU', NULL, NULL, NULL),
    (@max_id+4, 'hu_HU', NULL, NULL, NULL),
    (@max_id+5, 'hu_HU', NULL, NULL, NULL),
    (@max_id+6, 'hu_HU', NULL, NULL, NULL),
    (@max_id+7, 'hu_HU', NULL, NULL, NULL),
    (@max_id+1, 'id_ID', NULL, NULL, NULL),
    (@max_id+2, 'id_ID', NULL, NULL, NULL),
    (@max_id+3, 'id_ID', NULL, NULL, NULL),
    (@max_id+4, 'id_ID', NULL, NULL, NULL),
    (@max_id+5, 'id_ID', NULL, NULL, NULL),
    (@max_id+6, 'id_ID', NULL, NULL, NULL),
    (@max_id+7, 'id_ID', NULL, NULL, NULL),
    (@max_id+1, 'it_IT', NULL, NULL, NULL),
    (@max_id+2, 'it_IT', NULL, NULL, NULL),
    (@max_id+3, 'it_IT', NULL, NULL, NULL),
    (@max_id+4, 'it_IT', NULL, NULL, NULL),
    (@max_id+5, 'it_IT', NULL, NULL, NULL),
    (@max_id+6, 'it_IT', NULL, NULL, NULL),
    (@max_id+7, 'it_IT', NULL, NULL, NULL),
    (@max_id+1, 'pl_PL', NULL, NULL, NULL),
    (@max_id+2, 'pl_PL', NULL, NULL, NULL),
    (@max_id+3, 'pl_PL', NULL, NULL, NULL),
    (@max_id+4, 'pl_PL', NULL, NULL, NULL),
    (@max_id+5, 'pl_PL', NULL, NULL, NULL),
    (@max_id+6, 'pl_PL', NULL, NULL, NULL),
    (@max_id+7, 'pl_PL', NULL, NULL, NULL),
    (@max_id+1, 'pt_BR', NULL, NULL, NULL),
    (@max_id+2, 'pt_BR', NULL, NULL, NULL),
    (@max_id+3, 'pt_BR', NULL, NULL, NULL),
    (@max_id+4, 'pt_BR', NULL, NULL, NULL),
    (@max_id+5, 'pt_BR', NULL, NULL, NULL),
    (@max_id+6, 'pt_BR', NULL, NULL, NULL),
    (@max_id+7, 'pt_BR', NULL, NULL, NULL),
    (@max_id+1, 'pt_PT', NULL, NULL, NULL),
    (@max_id+2, 'pt_PT', NULL, NULL, NULL),
    (@max_id+3, 'pt_PT', NULL, NULL, NULL),
    (@max_id+4, 'pt_PT', NULL, NULL, NULL),
    (@max_id+5, 'pt_PT', NULL, NULL, NULL),
    (@max_id+6, 'pt_PT', NULL, NULL, NULL),
    (@max_id+7, 'pt_PT', NULL, NULL, NULL),
    (@max_id+1, 'ru_RU', NULL, NULL, NULL),
    (@max_id+2, 'ru_RU', NULL, NULL, NULL),
    (@max_id+3, 'ru_RU', NULL, NULL, NULL),
    (@max_id+4, 'ru_RU', NULL, NULL, NULL),
    (@max_id+5, 'ru_RU', NULL, NULL, NULL),
    (@max_id+6, 'ru_RU', NULL, NULL, NULL),
    (@max_id+7, 'ru_RU', NULL, NULL, NULL),
    (@max_id+1, 'sk_SK', NULL, NULL, NULL),
    (@max_id+2, 'sk_SK', NULL, NULL, NULL),
    (@max_id+3, 'sk_SK', NULL, NULL, NULL),
    (@max_id+4, 'sk_SK', NULL, NULL, NULL),
    (@max_id+5, 'sk_SK', NULL, NULL, NULL),
    (@max_id+6, 'sk_SK', NULL, NULL, NULL),
    (@max_id+7, 'sk_SK', NULL, NULL, NULL),
    (@max_id+1, 'tr_TR', NULL, NULL, NULL),
    (@max_id+2, 'tr_TR', NULL, NULL, NULL),
    (@max_id+3, 'tr_TR', NULL, NULL, NULL),
    (@max_id+4, 'tr_TR', NULL, NULL, NULL),
    (@max_id+5, 'tr_TR', NULL, NULL, NULL),
    (@max_id+6, 'tr_TR', NULL, NULL, NULL),
    (@max_id+7, 'tr_TR', NULL, NULL, NULL),
    (@max_id+1, 'uk_UA', NULL, NULL, NULL),
    (@max_id+2, 'uk_UA', NULL, NULL, NULL),
    (@max_id+3, 'uk_UA', NULL, NULL, NULL),
    (@max_id+4, 'uk_UA', NULL, NULL, NULL),
    (@max_id+5, 'uk_UA', NULL, NULL, NULL),
    (@max_id+6, 'uk_UA', NULL, NULL, NULL),
    (@max_id+7, 'uk_UA', NULL, NULL, NULL)
;

-- Customer confirmation

ALTER TABLE `customer` ADD `enable` TINYINT DEFAULT 0 AFTER `remember_me_serial`;
ALTER TABLE `customer` ADD `confirmation_token` VARCHAR(255) AFTER `enable`;
ALTER TABLE `customer_version` ADD `enable` TINYINT DEFAULT 0 AFTER `remember_me_serial`;
ALTER TABLE `customer_version` ADD `confirmation_token` VARCHAR(255) AFTER `enable`;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'customer_email_confirmation', '0', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@max_id + 1, 'ar_SA', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'de_DE', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'el_GR', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'en_US', 'Customer account creation should be confirmed by email (1: yes, 0: no)', NULL, NULL, NULL),
    (@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'fa_IR', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'fr_FR', 'La création d\'un compte client doit être confirmée par email (1: oui, 0: non)', NULL, NULL, NULL),
    (@max_id + 1, 'hu_HU', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'id_ID', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'it_IT', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'pl_PL', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'pt_BR', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'pt_PT', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'ru_RU', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'sk_SK', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'tr_TR', NULL, NULL, NULL, NULL),
    (@max_id + 1, 'uk_UA', NULL, NULL, NULL, NULL)
;

SELECT @max_id :=IFNULL(MAX(`id`),0) FROM `message`;
INSERT INTO `message` (`id`, `name`, `secured`, `text_layout_file_name`, `text_template_file_name`, `html_layout_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'customer_confirmation', NULL, NULL, 'customer_confirmation.txt', NULL, 'customer_confirmation.html', NOW(), NOW());

INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
    (@max_id+1, 'ar_SA', NULL, NULL, NULL, NULL),
    (@max_id+1, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@max_id+1, 'de_DE', NULL, NULL, NULL, NULL),
    (@max_id+1, 'el_GR', NULL, NULL, NULL, NULL),
    (@max_id+1, 'en_US', 'Mail sent to the customer to confirm its account', 'Confirm your {config key=\"store_name\"} account', NULL, NULL),
    (@max_id+1, 'es_ES', NULL, NULL, NULL, NULL),
    (@max_id+1, 'fa_IR', NULL, NULL, NULL, NULL),
    (@max_id+1, 'fr_FR', 'E-mail de confirmation de création de compte client', 'Confirmez la création de votre compte {config key=\"store_name\"}', NULL, NULL),
    (@max_id+1, 'hu_HU', NULL, NULL, NULL, NULL),
    (@max_id+1, 'id_ID', NULL, NULL, NULL, NULL),
    (@max_id+1, 'it_IT', NULL, NULL, NULL, NULL),
    (@max_id+1, 'pl_PL', NULL, NULL, NULL, NULL),
    (@max_id+1, 'pt_BR', NULL, NULL, NULL, NULL),
    (@max_id+1, 'pt_PT', NULL, NULL, NULL, NULL),
    (@max_id+1, 'ru_RU', NULL, NULL, NULL, NULL),
    (@max_id+1, 'sk_SK', NULL, NULL, NULL, NULL),
    (@max_id+1, 'tr_TR', NULL, NULL, NULL, NULL),
    (@max_id+1, 'uk_UA', NULL, NULL, NULL, NULL)
;

-- Order status improvement

ALTER TABLE `order_status` ADD `color` CHAR(7) NOT NULL AFTER `code`;
ALTER TABLE `order_status` ADD `position` INT(11) NOT NULL AFTER `color`;
ALTER TABLE `order_status` ADD `protected_status` TINYINT(1) NOT NULL DEFAULT '0' AFTER `position`;

UPDATE `order_status` SET `position` = `id` WHERE 1;
UPDATE `order_status` SET `color` = '#f0ad4e' WHERE `code` = 'not_paid';
UPDATE `order_status` SET `color` = '#5cb85c' WHERE `code` = 'paid';
UPDATE `order_status` SET `color` = '#f39922' WHERE `code` = 'processing';
UPDATE `order_status` SET `color` = '#5bc0de' WHERE `code` = 'sent';
UPDATE `order_status` SET `color` = '#d9534f' WHERE `code` = 'canceled';
UPDATE `order_status` SET `color` = '#986dff' WHERE `code` = 'refunded';
UPDATE `order_status` SET `color` = '#777777' WHERE `code` NOT IN ('not_paid', 'paid', 'processing', 'sent', 'canceled', 'refunded');
UPDATE `order_status` SET `protected_status` = 1 WHERE `code` IN ('not_paid', 'paid', 'processing', 'sent', 'canceled', 'refunded');

SELECT @max_id := MAX(`id`) FROM `resource`;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES (@max_id+1, 'admin.configuration.order-status', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
  (@max_id+1, 'ar_SA', NULL),
  (@max_id+1, 'cs_CZ', NULL),
  (@max_id+1, 'de_DE', NULL),
  (@max_id+1, 'el_GR', NULL),
  (@max_id+1, 'en_US', 'Configuration order status'),
  (@max_id+1, 'es_ES', NULL),
  (@max_id+1, 'fa_IR', NULL),
  (@max_id+1, 'fr_FR', NULL),
  (@max_id+1, 'hu_HU', NULL),
  (@max_id+1, 'id_ID', NULL),
  (@max_id+1, 'it_IT', NULL),
  (@max_id+1, 'pl_PL', NULL),
  (@max_id+1, 'pt_BR', NULL),
  (@max_id+1, 'pt_PT', NULL),
  (@max_id+1, 'ru_RU', NULL),
  (@max_id+1, 'sk_SK', NULL),
  (@max_id+1, 'tr_TR', NULL),
  (@max_id+1, 'uk_UA', NULL)
;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1,  'configuration.order-path.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2,  'configuration.order-path.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3,  'order-status.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4,  'order-status.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+5,  'order-status.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+6,  'order-status.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+7,  'order-status.form.creation', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+8,  'order-status.form.modification', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+9,  'order-status.js', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id+1,  'ar_SA', NULL, NULL, NULL),
(@max_id+2,  'ar_SA', NULL, NULL, NULL),
(@max_id+3,  'ar_SA', NULL, NULL, NULL),
(@max_id+4,  'ar_SA', NULL, NULL, NULL),
(@max_id+5,  'ar_SA', NULL, NULL, NULL),
(@max_id+6,  'ar_SA', NULL, NULL, NULL),
(@max_id+7,  'ar_SA', NULL, NULL, NULL),
(@max_id+8,  'ar_SA', NULL, NULL, NULL),
(@max_id+9,  'ar_SA', NULL, NULL, NULL),
(@max_id+1,  'cs_CZ', NULL, NULL, NULL),
(@max_id+2,  'cs_CZ', NULL, NULL, NULL),
(@max_id+3,  'cs_CZ', NULL, NULL, NULL),
(@max_id+4,  'cs_CZ', NULL, NULL, NULL),
(@max_id+5,  'cs_CZ', NULL, NULL, NULL),
(@max_id+6,  'cs_CZ', NULL, NULL, NULL),
(@max_id+7,  'cs_CZ', NULL, NULL, NULL),
(@max_id+8,  'cs_CZ', NULL, NULL, NULL),
(@max_id+9,  'cs_CZ', NULL, NULL, NULL),
(@max_id+1,  'de_DE', NULL, NULL, NULL),
(@max_id+2,  'de_DE', NULL, NULL, NULL),
(@max_id+3,  'de_DE', NULL, NULL, NULL),
(@max_id+4,  'de_DE', NULL, NULL, NULL),
(@max_id+5,  'de_DE', NULL, NULL, NULL),
(@max_id+6,  'de_DE', NULL, NULL, NULL),
(@max_id+7,  'de_DE', NULL, NULL, NULL),
(@max_id+8,  'de_DE', NULL, NULL, NULL),
(@max_id+9,  'de_DE', NULL, NULL, NULL),
(@max_id+1,  'el_GR', NULL, NULL, NULL),
(@max_id+2,  'el_GR', NULL, NULL, NULL),
(@max_id+3,  'el_GR', NULL, NULL, NULL),
(@max_id+4,  'el_GR', NULL, NULL, NULL),
(@max_id+5,  'el_GR', NULL, NULL, NULL),
(@max_id+6,  'el_GR', NULL, NULL, NULL),
(@max_id+7,  'el_GR', NULL, NULL, NULL),
(@max_id+8,  'el_GR', NULL, NULL, NULL),
(@max_id+9,  'el_GR', NULL, NULL, NULL),
(@max_id+1,  'en_US', 'Configuration - Order path - top', NULL, NULL),
(@max_id+2,  'en_US', 'Configuration - Order path - bottom', NULL, NULL),
(@max_id+3,  'en_US', 'Order status - top', NULL, NULL),
(@max_id+4,  'en_US', 'Order status - bottom', NULL, NULL),
(@max_id+5,  'en_US', 'Order status - table header', NULL, NULL),
(@max_id+6,  'en_US', 'Order status - table row', NULL, NULL),
(@max_id+7,  'en_US', 'Order status - form creation', NULL, NULL),
(@max_id+8,  'en_US', 'Order status - form modification', NULL, NULL),
(@max_id+9,  'en_US', 'Order status - JavaScript', NULL, NULL),
(@max_id+1,  'es_ES', NULL, NULL, NULL),
(@max_id+2,  'es_ES', NULL, NULL, NULL),
(@max_id+3,  'es_ES', NULL, NULL, NULL),
(@max_id+4,  'es_ES', NULL, NULL, NULL),
(@max_id+5,  'es_ES', NULL, NULL, NULL),
(@max_id+6,  'es_ES', NULL, NULL, NULL),
(@max_id+7,  'es_ES', NULL, NULL, NULL),
(@max_id+8,  'es_ES', NULL, NULL, NULL),
(@max_id+9,  'es_ES', NULL, NULL, NULL),
(@max_id+1,  'fa_IR', NULL, NULL, NULL),
(@max_id+2,  'fa_IR', NULL, NULL, NULL),
(@max_id+3,  'fa_IR', NULL, NULL, NULL),
(@max_id+4,  'fa_IR', NULL, NULL, NULL),
(@max_id+5,  'fa_IR', NULL, NULL, NULL),
(@max_id+6,  'fa_IR', NULL, NULL, NULL),
(@max_id+7,  'fa_IR', NULL, NULL, NULL),
(@max_id+8,  'fa_IR', NULL, NULL, NULL),
(@max_id+9,  'fa_IR', NULL, NULL, NULL),
(@max_id+1,  'fr_FR', NULL, NULL, NULL),
(@max_id+2,  'fr_FR', NULL, NULL, NULL),
(@max_id+3,  'fr_FR', NULL, NULL, NULL),
(@max_id+4,  'fr_FR', NULL, NULL, NULL),
(@max_id+5,  'fr_FR', NULL, NULL, NULL),
(@max_id+6,  'fr_FR', NULL, NULL, NULL),
(@max_id+7,  'fr_FR', NULL, NULL, NULL),
(@max_id+8,  'fr_FR', NULL, NULL, NULL),
(@max_id+9,  'fr_FR', NULL, NULL, NULL),
(@max_id+1,  'hu_HU', NULL, NULL, NULL),
(@max_id+2,  'hu_HU', NULL, NULL, NULL),
(@max_id+3,  'hu_HU', NULL, NULL, NULL),
(@max_id+4,  'hu_HU', NULL, NULL, NULL),
(@max_id+5,  'hu_HU', NULL, NULL, NULL),
(@max_id+6,  'hu_HU', NULL, NULL, NULL),
(@max_id+7,  'hu_HU', NULL, NULL, NULL),
(@max_id+8,  'hu_HU', NULL, NULL, NULL),
(@max_id+9,  'hu_HU', NULL, NULL, NULL),
(@max_id+1,  'id_ID', NULL, NULL, NULL),
(@max_id+2,  'id_ID', NULL, NULL, NULL),
(@max_id+3,  'id_ID', NULL, NULL, NULL),
(@max_id+4,  'id_ID', NULL, NULL, NULL),
(@max_id+5,  'id_ID', NULL, NULL, NULL),
(@max_id+6,  'id_ID', NULL, NULL, NULL),
(@max_id+7,  'id_ID', NULL, NULL, NULL),
(@max_id+8,  'id_ID', NULL, NULL, NULL),
(@max_id+9,  'id_ID', NULL, NULL, NULL),
(@max_id+1,  'it_IT', NULL, NULL, NULL),
(@max_id+2,  'it_IT', NULL, NULL, NULL),
(@max_id+3,  'it_IT', NULL, NULL, NULL),
(@max_id+4,  'it_IT', NULL, NULL, NULL),
(@max_id+5,  'it_IT', NULL, NULL, NULL),
(@max_id+6,  'it_IT', NULL, NULL, NULL),
(@max_id+7,  'it_IT', NULL, NULL, NULL),
(@max_id+8,  'it_IT', NULL, NULL, NULL),
(@max_id+9,  'it_IT', NULL, NULL, NULL),
(@max_id+1,  'pl_PL', NULL, NULL, NULL),
(@max_id+2,  'pl_PL', NULL, NULL, NULL),
(@max_id+3,  'pl_PL', NULL, NULL, NULL),
(@max_id+4,  'pl_PL', NULL, NULL, NULL),
(@max_id+5,  'pl_PL', NULL, NULL, NULL),
(@max_id+6,  'pl_PL', NULL, NULL, NULL),
(@max_id+7,  'pl_PL', NULL, NULL, NULL),
(@max_id+8,  'pl_PL', NULL, NULL, NULL),
(@max_id+9,  'pl_PL', NULL, NULL, NULL),
(@max_id+1,  'pt_BR', NULL, NULL, NULL),
(@max_id+2,  'pt_BR', NULL, NULL, NULL),
(@max_id+3,  'pt_BR', NULL, NULL, NULL),
(@max_id+4,  'pt_BR', NULL, NULL, NULL),
(@max_id+5,  'pt_BR', NULL, NULL, NULL),
(@max_id+6,  'pt_BR', NULL, NULL, NULL),
(@max_id+7,  'pt_BR', NULL, NULL, NULL),
(@max_id+8,  'pt_BR', NULL, NULL, NULL),
(@max_id+9,  'pt_BR', NULL, NULL, NULL),
(@max_id+1,  'pt_PT', NULL, NULL, NULL),
(@max_id+2,  'pt_PT', NULL, NULL, NULL),
(@max_id+3,  'pt_PT', NULL, NULL, NULL),
(@max_id+4,  'pt_PT', NULL, NULL, NULL),
(@max_id+5,  'pt_PT', NULL, NULL, NULL),
(@max_id+6,  'pt_PT', NULL, NULL, NULL),
(@max_id+7,  'pt_PT', NULL, NULL, NULL),
(@max_id+8,  'pt_PT', NULL, NULL, NULL),
(@max_id+9,  'pt_PT', NULL, NULL, NULL),
(@max_id+1,  'ru_RU', NULL, NULL, NULL),
(@max_id+2,  'ru_RU', NULL, NULL, NULL),
(@max_id+3,  'ru_RU', NULL, NULL, NULL),
(@max_id+4,  'ru_RU', NULL, NULL, NULL),
(@max_id+5,  'ru_RU', NULL, NULL, NULL),
(@max_id+6,  'ru_RU', NULL, NULL, NULL),
(@max_id+7,  'ru_RU', NULL, NULL, NULL),
(@max_id+8,  'ru_RU', NULL, NULL, NULL),
(@max_id+9,  'ru_RU', NULL, NULL, NULL),
(@max_id+1,  'sk_SK', NULL, NULL, NULL),
(@max_id+2,  'sk_SK', NULL, NULL, NULL),
(@max_id+3,  'sk_SK', NULL, NULL, NULL),
(@max_id+4,  'sk_SK', NULL, NULL, NULL),
(@max_id+5,  'sk_SK', NULL, NULL, NULL),
(@max_id+6,  'sk_SK', NULL, NULL, NULL),
(@max_id+7,  'sk_SK', NULL, NULL, NULL),
(@max_id+8,  'sk_SK', NULL, NULL, NULL),
(@max_id+9,  'sk_SK', NULL, NULL, NULL),
(@max_id+1,  'tr_TR', NULL, NULL, NULL),
(@max_id+2,  'tr_TR', NULL, NULL, NULL),
(@max_id+3,  'tr_TR', NULL, NULL, NULL),
(@max_id+4,  'tr_TR', NULL, NULL, NULL),
(@max_id+5,  'tr_TR', NULL, NULL, NULL),
(@max_id+6,  'tr_TR', NULL, NULL, NULL),
(@max_id+7,  'tr_TR', NULL, NULL, NULL),
(@max_id+8,  'tr_TR', NULL, NULL, NULL),
(@max_id+9,  'tr_TR', NULL, NULL, NULL),
(@max_id+1,  'uk_UA', NULL, NULL, NULL),
(@max_id+2,  'uk_UA', NULL, NULL, NULL),
(@max_id+3,  'uk_UA', NULL, NULL, NULL),
(@max_id+4,  'uk_UA', NULL, NULL, NULL),
(@max_id+5,  'uk_UA', NULL, NULL, NULL),
(@max_id+6,  'uk_UA', NULL, NULL, NULL),
(@max_id+7,  'uk_UA', NULL, NULL, NULL),
(@max_id+8,  'uk_UA', NULL, NULL, NULL),
(@max_id+9,  'uk_UA', NULL, NULL, NULL)
;


-- Additional usage_canceled column in order_coupon table
ALTER TABLE `order_coupon` ADD `usage_canceled` TINYINT(1) DEFAULT '0' AFTER `per_customer_usage_count`;

-- add new config variables number_default_results_per_page
SELECT @max := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max+1, 'number_default_results_per_page.coupon_list', '20', '0', '0', NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@max+1, 'ar_SA', NULL, NUll, NULL, NULL),    (@max+1, 'cs_CZ', NULL, NUll, NULL, NULL),    (@max+1, 'de_DE', NULL, NUll, NULL, NULL),    (@max+1, 'el_GR', NULL, NUll, NULL, NULL),    (@max+1, 'en_US', 'Default number of coupons per page on coupon list', NUll, NULL, NULL),    (@max+1, 'es_ES', NULL, NUll, NULL, NULL),    (@max+1, 'fa_IR', NULL, NUll, NULL, NULL),    (@max+1, 'fr_FR', 'Nombre de coupons par page dans la liste des coupons', NUll, NULL, NULL),    (@max+1, 'hu_HU', NULL, NUll, NULL, NULL),    (@max+1, 'id_ID', NULL, NUll, NULL, NULL),    (@max+1, 'it_IT', NULL, NUll, NULL, NULL),    (@max+1, 'pl_PL', NULL, NUll, NULL, NULL),    (@max+1, 'pt_BR', NULL, NUll, NULL, NULL),    (@max+1, 'pt_PT', NULL, NUll, NULL, NULL),    (@max+1, 'ru_RU', NULL, NUll, NULL, NULL),    (@max+1, 'sk_SK', NULL, NUll, NULL, NULL),    (@max+1, 'tr_TR', NULL, NUll, NULL, NULL),    (@max+1, 'uk_UA', NULL, NUll, NULL, NULL);

ALTER TABLE `module` ADD `mandatory` TINYINT NOT NULL DEFAULT '0' AFTER `full_namespace`, ADD `hidden` TINYINT NOT NULL DEFAULT '0' AFTER `mandatory`;
UPDATE `module` SET `mandatory` = 0, `hidden` = 0;
UPDATE `module` SET `hidden` = 1 WHERE `code` = 'Front';
UPDATE `module` SET `mandatory` = 1, `hidden` = 1 WHERE `code` = 'TheliaSmarty';

SET FOREIGN_KEY_CHECKS = 1;
