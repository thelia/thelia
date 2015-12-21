SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Add new column in module_hook table
ALTER TABLE `module_hook` ADD `templates` TEXT AFTER`position`;

-- Add new columns in currency table
ALTER TABLE `currency` ADD  `format` CHAR( 10 ) NOT NULL AFTER  `symbol`;
ALTER TABLE `currency` ADD  `visible` TINYINT( 1 ) NOT NULL DEFAULT '0' AFTER  `rate`;

-- Update currencies
UPDATE `currency` SET `visible` = 1 WHERE 1;
UPDATE `currency` SET `format` = '%n %s' WHERE `code` NOT IN ('USD', 'GBP');
UPDATE `currency` SET `format` = '%s%n' WHERE `code` IN ('USD', 'GBP');

-- Additional hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1,  'brand.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2,  'brand.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3,  'category.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4,  'category.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+5,  'content.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+6,  'content.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+7,  'folder.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+8,  'folder.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+9,  'product.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+10, 'product.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
    (@max_id+1,  '{$locale}', {intl l='Brand edit - right column top' locale=$locale}, NULL, NULL),
    (@max_id+2,  '{$locale}', {intl l='Brand edit - right column bottom' locale=$locale}, NULL, NULL),
    (@max_id+3,  '{$locale}', {intl l='Category edit - right column top' locale=$locale}, NULL, NULL),
    (@max_id+4,  '{$locale}', {intl l='Category edit - right column bottom' locale=$locale}, NULL, NULL),
    (@max_id+5,  '{$locale}', {intl l='Content edit - right column top' locale=$locale}, NULL, NULL),
    (@max_id+6,  '{$locale}', {intl l='Content edit - right column bottom' locale=$locale}, NULL, NULL),
    (@max_id+7,  '{$locale}', {intl l='Folder edit - right column top' locale=$locale}, NULL, NULL),
    (@max_id+8,  '{$locale}', {intl l='Folder edit - right column bottom' locale=$locale}, NULL, NULL),
    (@max_id+9,  '{$locale}', {intl l='Product edit - right column top' locale=$locale}, NULL, NULL),
    (@max_id+10, '{$locale}', {intl l='Product edit - right column bottom' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}
{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;