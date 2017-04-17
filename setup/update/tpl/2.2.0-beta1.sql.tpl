SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

-- fix currency already created
update currency set by_default = 0 where by_default is NULL;

ALTER TABLE `category_version` ADD COLUMN `default_template_id` INTEGER AFTER  `position`;

-- new hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
    (@max_id + 1, 'order-edit.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id + 2, 'order-edit.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id + 3, 'mini-cart', 1, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
    (@max_id + 1, '{$locale}', {intl l='Order - table header' locale=$locale}, '', ''),
    (@max_id + 2, '{$locale}', {intl l='Order - table row' locale=$locale}, '', ''),
    (@max_id + 3, '{$locale}', {intl l='Mini cart' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

ALTER TABLE `rewriting_url` CHANGE `url` `url` VARBINARY( 255 ) NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
