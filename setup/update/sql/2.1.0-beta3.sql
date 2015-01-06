SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-beta3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta3' WHERE `name`='thelia_extra_version';

-- Order

ALTER TABLE `order` ADD `postage_tax` FLOAT DEFAULT 0 NOT NULL AFTER `postage` ;
ALTER TABLE `order` ADD `postage_tax_rule_title` VARCHAR(255) AFTER `postage_tax` ;

ALTER TABLE `order_version` ADD `postage_tax` FLOAT DEFAULT 0 NOT NULL AFTER `postage` ;
ALTER TABLE `order_version` ADD `postage_tax_rule_title` VARCHAR(255) AFTER `postage_tax` ;

-- Hooks
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'brand.update-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'sale.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'api.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'api.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'api.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'fr_FR', 'Page brand edit - dans formulaire', '', ''),
(@max_id + 1, 'en_US', 'Brand edit page - in the form', '', ''),
(@max_id + 2, 'fr_FR', 'Page sale edit - appel javascript', '', ''),
(@max_id + 2, 'en_US', 'Sale edit page - javascript last call block', '', ''),
(@max_id + 3, 'fr_FR', 'Page api - en haut', '', ''),
(@max_id + 3, 'en_US', 'Api page - at top', '', ''),
(@max_id + 4, 'fr_FR', 'Page api - en bas', '', ''),
(@max_id + 4, 'en_US', 'Api page - at bottom', '', ''),
(@max_id + 5, 'fr_FR', 'Page api - formulaire de suppression', '', ''),
(@max_id + 5, 'en_US', 'Api page - in deletion form', '', '')
;

SET FOREIGN_KEY_CHECKS = 1;