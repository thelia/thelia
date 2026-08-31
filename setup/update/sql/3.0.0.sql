SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Lost password mail wording (#3840)
--
-- The mail no longer sends a new password: it sends a signed reset link.
-- Betas seeded the old subject; carry the new wording over, but only where
-- the row still holds the seeded value, so a subject the merchant edited
-- in the back-office is never overwritten.
-- ---------------------------------------------------------------------

UPDATE `message_i18n` `mi`
    INNER JOIN `message` `m` ON `m`.`id` = `mi`.`id` AND `m`.`name` = 'lost_password'
    SET `mi`.`title` = 'An den Kunden gesendeter Link zum Zurücksetzen des Passworts',
        `mi`.`subject` = 'Setzen Sie Ihr Passwort für {{ config("store_name") }} zurück'
    WHERE `mi`.`locale` = 'de_DE'
      AND `mi`.`subject` = 'Ihr neues Passwort für {{ config("store_name") }}';

UPDATE `message_i18n` `mi`
    INNER JOIN `message` `m` ON `m`.`id` = `mi`.`id` AND `m`.`name` = 'lost_password'
    SET `mi`.`title` = 'Password reset link sent to the customer',
        `mi`.`subject` = 'Reset your password on {{ config("store_name") }}'
    WHERE `mi`.`locale` = 'en_US'
      AND `mi`.`subject` = 'Your new password for {{ config("store_name") }}';

UPDATE `message_i18n` `mi`
    INNER JOIN `message` `m` ON `m`.`id` = `mi`.`id` AND `m`.`name` = 'lost_password'
    SET `mi`.`title` = 'Enlace de restablecimiento de contraseña enviado al cliente',
        `mi`.`subject` = 'Restablezca su contraseña en {{ config("store_name") }}'
    WHERE `mi`.`locale` = 'es_ES'
      AND `mi`.`subject` = 'Su nueva contraseña para {{ config("store_name") }}';

UPDATE `message_i18n` `mi`
    INNER JOIN `message` `m` ON `m`.`id` = `mi`.`id` AND `m`.`name` = 'lost_password'
    SET `mi`.`title` = 'Lien de réinitialisation du mot de passe envoyé au client',
        `mi`.`subject` = 'Réinitialisez votre mot de passe sur {{ config("store_name") }}'
    WHERE `mi`.`locale` = 'fr_FR'
      AND `mi`.`subject` = 'Votre nouveau mot de passe {{ config("store_name") }}';

UPDATE `message_i18n` `mi`
    INNER JOIN `message` `m` ON `m`.`id` = `mi`.`id` AND `m`.`name` = 'lost_password'
    SET `mi`.`title` = 'Ссылка для сброса пароля, отправленная клиенту',
        `mi`.`subject` = 'Сбросьте пароль на {{ config("store_name") }}'
    WHERE `mi`.`locale` = 'ru_RU'
      AND `mi`.`subject` = 'Ваш новый пароль для {{ config("store_name") }}';

-- ---------------------------------------------------------------------
-- Canonical mail template config row (#3858)
--
-- Betas carried a stray `active-email-template` row next to the canonical
-- `active-mail-template` one, and `template:set email` on a beta stored the
-- chosen template in the stray row while the mailer kept reading the
-- canonical one. Carry the value over — unless the canonical row was
-- already changed from its default, which means the merchant chose a
-- template through the back-office — then drop the stray row.
-- ---------------------------------------------------------------------

UPDATE `config` `stored`
    INNER JOIN `config` `stray` ON `stray`.`name` = 'active-email-template'
    SET `stored`.`value` = `stray`.`value`
    WHERE `stored`.`name` = 'active-mail-template'
      AND `stored`.`value` = 'default'
      AND `stray`.`value` <> 'default';

DELETE FROM `config` WHERE `name` = 'active-email-template';

-- ---------------------------------------------------------------------
-- VirtualProductControl left the default install
--
-- The module only ships a Thelia 2 Smarty back-office hook, so it renders
-- nothing on a Thelia 3 install. The default-twig theme no longer requires
-- it: after a composer update its code is gone from vendor, and a module
-- row that stays active without code on disk aborts the boot in debug
-- environments. Deactivating the row keeps updated shops clean; a module
-- reinstalled on purpose recreates its row on activation.
-- ---------------------------------------------------------------------

UPDATE `module` SET `activate` = 0 WHERE `code` = 'VirtualProductControl';

SET FOREIGN_KEY_CHECKS = 1;
