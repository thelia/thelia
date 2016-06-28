SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.4' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @max_id := MAX(`id`) FROM hook;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
    (@max_id+1, 'export.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+2, 'export.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`) VALUES
{foreach $locales as $locale}
    (@max_id+1, '{$locale}', {intl l="Export modal or page - top" locale=$locale}, '', ''),
    (@max_id+2, '{$locale}', {intl l="Export modal or page - bottom" locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;