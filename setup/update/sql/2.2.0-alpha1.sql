SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- admin hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  (@max_id + 1, 'order.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
  (@max_id + 1, 'fr_FR', 'Commande - Onglet', '', ''),
  (@max_id + 1, 'en_US', 'Order - Tab', '', '')
;

SELECT @max_id := MAX(`id`) FROM `order_status`;

INSERT INTO `order_status` VALUES
  (@max_id + 1, "refunded", NOW(), NOW())
;

INSERT INTO  `order_status_i18n` VALUES
  (@max_id + 1, "en_US", "Refunded", "", "", ""),
  (@max_id + 1, "fr_FR", "Remboursée", "", "", "")
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
(@max_id + 1, 'fr_FR', 'Permettre aux clients de changer leur email. 1 pour oui, 0 pour non', NULL, NULL, NULL),
(@max_id + 2, 'en_US', 'Ask the customers to confirm their email, 1 for yes, 0 for no', NULL, NULL, NULL),
(@max_id + 2, 'fr_FR', 'Demander aux clients de confirmer leur email. 1 pour oui, 0 pour non', NULL, NULL, NULL)
;


SET FOREIGN_KEY_CHECKS = 1;