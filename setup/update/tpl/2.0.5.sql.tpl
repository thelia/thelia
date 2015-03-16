SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `order` DROP FOREIGN KEY `fk_order_cart_id`;

UPDATE `config` SET `value`='2.0.5' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

UPDATE `config` SET `name`='form_firewall_active' WHERE `name`='from_firewall_active';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `resource`;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'admin.search', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Search' locale=$locale}){if ! $locale@last},{/if}

{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;