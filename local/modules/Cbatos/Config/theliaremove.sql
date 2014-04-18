SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `atos_log`;
DROP TABLE IF EXISTS `atos_config`;
DROP TABLE IF EXISTS `atos_transaction`;

DELETE FROM `message` WHERE name="mail_atos";
DELETE FROM `message_i18n` WHERE name="mail_atos";

SET FOREIGN_KEY_CHECKS = 1;
