SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.4.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Additional hooks on order-invoice page

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'order-invoice.coupon-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2, 'order-invoice.payment-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
    (@max_id+1, 'de_DE', NULL, NULL, NULL),
    (@max_id+2, 'de_DE', NULL, NULL, NULL),
    (@max_id+1, 'en_US', 'Order invoice page - bottom of coupon form', NULL, NULL),
    (@max_id+2, 'en_US', 'Order invoice page - bottom of payment form', NULL, NULL),
    (@max_id+1, 'es_ES', NULL, NULL, NULL),
    (@max_id+2, 'es_ES', NULL, NULL, NULL),
    (@max_id+1, 'fr_FR', NULL, NULL, NULL),
    (@max_id+2, 'fr_FR', NULL, NULL, NULL)
;

SET FOREIGN_KEY_CHECKS = 1;
