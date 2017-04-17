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
    (@max_id+1, 'de_DE', 'Export modal or page - oben', '', ''),
    (@max_id+2, 'de_DE', 'Export modal or page - unten', '', ''),
    (@max_id+1, 'en_US', 'Export modal or page - top', '', ''),
    (@max_id+2, 'en_US', 'Export modal or page - bottom', '', ''),
    (@max_id+1, 'es_ES', 'Modal o página de exportación - superior', '', ''),
    (@max_id+2, 'es_ES', 'Modal o página de exportación - inferior', '', ''),
    (@max_id+1, 'fr_FR', 'Modal ou page d\'export - en haut', '', ''),
    (@max_id+2, 'fr_FR', 'Modal ou page d\'export - en bas', '', '')
;

SET FOREIGN_KEY_CHECKS = 1;