# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

# Remove useless rewriting_url indexes
ALTER TABLE `rewriting_url` DROP INDEX `idx_rewriting_url_view_updated_at`;
ALTER TABLE `rewriting_url` DROP INDEX `idx_rewriting_url_view_id_view_view_locale_updated_at`;

# Add coupon country/modules crossref tables
# ------------------------------------------

DROP TABLE IF EXISTS `coupon_country`;

CREATE TABLE `coupon_country`
(
    `coupon_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`country_id`),
    INDEX `fk_country_id_idx` (`country_id`),
    CONSTRAINT `fk_coupon_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_country_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

DROP TABLE IF EXISTS `coupon_module`;

CREATE TABLE `coupon_module`
(
    `coupon_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`module_id`),
    INDEX `fk_module_id_idx` (`module_id`),
    CONSTRAINT `fk_coupon_module_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_module_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';


DROP TABLE IF EXISTS `order_coupon_country`;

CREATE TABLE `order_coupon_country`
(
    `coupon_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`country_id`),
    INDEX `fk_country_id_idx` (`country_id`),
    CONSTRAINT `fk_order_coupon_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_coupon_country_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `order_coupon` (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

DROP TABLE IF EXISTS `order_coupon_module`;

CREATE TABLE `order_coupon_module`
(
    `coupon_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`module_id`),
    INDEX `fk_module_id_idx` (`module_id`),
    CONSTRAINT `fk_coupon_module_coupon_id0`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `order_coupon` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_module_module_id0`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

# Per customer usage count
# ------------------------

# Add new column to coupon tables (coupon, order_coupon, coupon_version)

ALTER TABLE `coupon` ADD `per_customer_usage_count` BOOLEAN NOT NULL DEFAULT FALSE AFTER `serialized_conditions`;
ALTER TABLE `order_coupon` ADD `per_customer_usage_count` BOOLEAN NOT NULL DEFAULT FALSE AFTER `serialized_conditions`;
ALTER TABLE `coupon_version` ADD `per_customer_usage_count` BOOLEAN NOT NULL DEFAULT FALSE AFTER `serialized_conditions`;

DROP TABLE IF EXISTS `coupon_customer_count`;

CREATE TABLE `coupon_customer_count`
(
    `coupon_id` INTEGER NOT NULL,
    `customer_id` INTEGER NOT NULL,
    `count` INTEGER DEFAULT 0 NOT NULL,
    INDEX `fk_coupon_customer_customer_id_idx` (`customer_id`),
    INDEX `fk_coupon_customer_coupon_id_idx` (`coupon_id`),
    CONSTRAINT `fk_coupon_customer_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_customer_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

SELECT @max := MAX(`id`) FROM `country`;
SET @max := @max+1;

INSERT INTO `country` (`id`, `area_id`, `isocode`, `isoalpha2`, `isoalpha3`, `by_default`, `shop_country`, `created_at`, `updated_at`) VALUES
(@max, 5, '344', 'HK', 'HKG', 0, 0, NOW(), NOW());
 
INSERT INTO `country_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max, 'de_DE', 'Hong Kong', '', '', ''),
(@max, 'en_US', 'Hong Kong', '', '', ''),
(@max, 'es_ES', 'Hong Kong', '', '', ''),
(@max, 'fr_FR', 'Hong Kong', '', '', '')
;

SET FOREIGN_KEY_CHECKS = 1;
