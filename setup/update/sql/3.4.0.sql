SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Express payment zones
--
-- Whether a shop shows the express payment buttons of its payment modules,
-- and where. Empty means nowhere, which is the shop every existing boutique
-- already is: no wallet appears until the merchant turns the checkout zone on
-- and installs a module that fills it. INSERT IGNORE leaves alone a shop that
-- already chose a value.
--
-- The title goes through a temporary table so that one list serves both the
-- rows to create (INSERT IGNORE leaves alone a shop that already wrote its own
-- title) and the rows that exist with an empty title (UPDATE fills only those).
-- The temporary table lives on the connection and is dropped at the end:
-- replaying the script changes nothing.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('express_payment_zones', '', 0, 0, NOW(), NOW());

DROP TEMPORARY TABLE IF EXISTS `config_title_seed`;

CREATE TEMPORARY TABLE `config_title_seed` (
    `name` VARCHAR(255) NOT NULL,
    `locale` CHAR(5) NOT NULL,
    `title` VARCHAR(255) NOT NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci';

INSERT IGNORE INTO `config_title_seed` (`name`, `locale`, `title`) VALUES
    ('express_payment_zones', 'en_US', 'Where the express payment buttons of the payment modules show, comma separated among checkout (empty: nowhere)'),
    ('express_payment_zones', 'fr_FR', 'Emplacements où s''affichent les boutons de paiement express des modules de paiement, séparés par des virgules parmi checkout (vide : nulle part)'),
    ('express_payment_zones', 'de_DE', 'Wo die Express-Zahlungsschaltflächen der Zahlungsmodule erscheinen, kommagetrennt aus checkout (leer: nirgends)'),
    ('express_payment_zones', 'es_ES', 'Dónde aparecen los botones de pago exprés de los módulos de pago, separados por comas entre checkout (vacío: en ningún sitio)'),
    ('express_payment_zones', 'nl_NL', 'Waar de expresbetaalknoppen van de betaalmodules verschijnen, door komma''s gescheiden uit checkout (leeg: nergens)'),
    ('express_payment_zones', 'ru_RU', 'Где показываются кнопки экспресс-оплаты платёжных модулей, через запятую из checkout (пусто: нигде)');

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `config_title_seed`.`locale`, `config_title_seed`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN `config_title_seed` ON `config_title_seed`.`name` = `config`.`name`;

UPDATE `config_i18n`
INNER JOIN `config` ON `config`.`id` = `config_i18n`.`id`
INNER JOIN `config_title_seed` ON `config_title_seed`.`name` = `config`.`name` AND `config_title_seed`.`locale` = `config_i18n`.`locale`
SET `config_i18n`.`title` = `config_title_seed`.`title`
WHERE `config_i18n`.`title` IS NULL OR `config_i18n`.`title` = '';

DROP TEMPORARY TABLE `config_title_seed`;

SET FOREIGN_KEY_CHECKS = 1;
