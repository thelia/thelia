SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Days an unpaid order holds the coupon usages it was placed with
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('coupon_unpaid_order_hold_days', '7', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'coupon_unpaid_order_hold_days' AS `name`, 'en_US' AS `locale`, 'Days an unpaid order holds the coupon usages it was placed with (0: an unpaid order holds none)' AS `title`
    UNION ALL SELECT 'coupon_unpaid_order_hold_days', 'fr_FR', 'Nombre de jours pendant lesquels une commande impayée retient les usages de coupon avec lesquels elle a été passée (0 : une commande impayée n''en retient aucun)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

SET FOREIGN_KEY_CHECKS = 1;
