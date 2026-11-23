SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Dormancy threshold of the customer reports
-- ---------------------------------------------------------------------
-- `customer_dormant_after_days` is the number of days without an order after which the
-- customer reports count a customer as dormant. INSERT IGNORE leaves a value a shop
-- already wrote. Replaying the script changes nothing.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('customer_dormant_after_days', '180', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, 'en_US', 'Days without an order after which a customer is dormant in the customer reports (180 when invalid)', NULL, NULL, NULL
FROM `config` WHERE `config`.`name` = 'customer_dormant_after_days'
UNION ALL
SELECT `config`.`id`, 'fr_FR', 'Jours sans commande après lesquels un client est dormant dans les rapports clients (180 si invalide)', NULL, NULL, NULL
FROM `config` WHERE `config`.`name` = 'customer_dormant_after_days';

SET FOREIGN_KEY_CHECKS = 1;
