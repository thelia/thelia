SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @maxHookId := MAX(`id`) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  (@maxHookId + 1, 'coupon.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
  (@maxHookId + 1, 'fr_FR', 'Page coupon - formulaire de suppression', '', ''),
  (@maxHookId + 1, 'en_US', 'Coupon page - in deletion form', '', '')
;

UPDATE `config_i18n` SET `title`='Utiliser un cookie persistant pour memoriser le panier du client' WHERE `locale`='fr_FR' AND `id`=(SELECT`id` FROM `config` WHERE `name`='cart.use_persistent_cookie');

SET FOREIGN_KEY_CHECKS = 1;
