SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tax types of a shop migrated from Thelia 2
--
-- A tax records the class of its type in `tax`.`type`, and the three types
-- moved from Thelia\TaxEngine\TaxType to Thelia\Domain\Taxation\TaxEngine\TaxType
-- in Thelia 3. Until this release the update only carried the percentage type
-- over, so a fixed amount tax or a tax on a product feature kept its Thelia 2
-- class name, which no tax type answers to any more: computing the price of a
-- product under such a rule failed with "Recorded type ... does not exists".
--
-- Each statement only matches a row still on the Thelia 2 name, so the
-- script can be replayed.
-- ---------------------------------------------------------------------

UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\PricePercentTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\PricePercentTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FixAmountTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FeatureFixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FeatureFixAmountTaxType';

-- The manufacturer part number and the manufacturer brand of a combination, beside its GTIN
-- kept in `ean_code`. Null on every combination that predates them: no part number, and the
-- brand of the product stands for the manufacturer. The order line freezes the part number
-- sold, as it already does for the GTIN.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'mpn');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `mpn` VARCHAR(255) NULL AFTER `ean_code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'manufacturer_brand_id');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `manufacturer_brand_id` INTEGER NULL AFTER `mpn`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The search by part number and the manufacturer brand filter read these columns: without an
-- index they scan the whole table. The index on `ean_code` is added by 3.2.0.sql, for the quick order.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_mpn');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_mpn` (`mpn`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_manufacturer_brand_id');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_manufacturer_brand_id` (`manufacturer_brand_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `CONSTRAINT_NAME` = 'fk_product_sale_elements_manufacturer_brand_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `product_sale_elements` ADD CONSTRAINT `fk_product_sale_elements_manufacturer_brand_id` FOREIGN KEY (`manufacturer_brand_id`) REFERENCES `brand` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'mpn');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `mpn` VARCHAR(255) NULL AFTER `ean_code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Back-office hooks of the default Twig back-office template
--
-- The templates of the back office call these hooks, and its documentation
-- offers some of them to modules (customer.tab, customer.tab-content,
-- customer-edit.actions), but no seed declared them. A module subscribing to
-- one was dropped when the container was built ("Hook customer.tab is
-- unknown."), and its section never showed.
--
-- A module may have declared some of them already, with an id of its own:
-- each hook is only added when no back-office hook has its code, and its
-- titles only for the languages it has none in. The script can be replayed.
-- ---------------------------------------------------------------------

INSERT INTO `hook` (`code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`)
SELECT `missing`.`code`, 2, 0, `missing`.`block`, 1, 1, 1, NOW(), NOW()
FROM (
    SELECT 'advanced-configuration.bottom' AS `code`, 0 AS `block`
    UNION ALL SELECT 'advanced-configuration.top', 0
    UNION ALL SELECT 'attribute.update-form', 0
    UNION ALL SELECT 'brand.seo.update-form', 0
    UNION ALL SELECT 'catalog-price-rule-edit.bottom', 0
    UNION ALL SELECT 'catalog-price-rule-edit.top', 0
    UNION ALL SELECT 'catalog-price-rule.bottom', 0
    UNION ALL SELECT 'catalog-price-rule.create-form', 0
    UNION ALL SELECT 'catalog-price-rule.js', 0
    UNION ALL SELECT 'catalog-price-rule.top', 0
    UNION ALL SELECT 'catalog.tree.bottom', 0
    UNION ALL SELECT 'catalog.tree.top', 0
    UNION ALL SELECT 'category.seo.update-form', 0
    UNION ALL SELECT 'category.update-form', 0
    UNION ALL SELECT 'checkout-step.bottom', 0
    UNION ALL SELECT 'checkout-step.js', 0
    UNION ALL SELECT 'checkout-step.top', 0
    UNION ALL SELECT 'configuration.customers.bottom', 0
    UNION ALL SELECT 'configuration.customers.top', 0
    UNION ALL SELECT 'configuration.store.bottom', 0
    UNION ALL SELECT 'configuration.store.top', 0
    UNION ALL SELECT 'consent-edit.bottom', 0
    UNION ALL SELECT 'consent-edit.top', 0
    UNION ALL SELECT 'consent.bottom', 0
    UNION ALL SELECT 'consent.edit-js', 0
    UNION ALL SELECT 'consent.js', 0
    UNION ALL SELECT 'consent.top', 0
    UNION ALL SELECT 'consent.update-form', 0
    UNION ALL SELECT 'content.seo.update-form', 0
    UNION ALL SELECT 'content.update-form', 0
    UNION ALL SELECT 'coupon.edit-bottom', 0
    UNION ALL SELECT 'coupon.edit-top', 0
    UNION ALL SELECT 'currency.edit-form', 0
    UNION ALL SELECT 'customer-edit.actions', 0
    UNION ALL SELECT 'customer.personal-data', 0
    UNION ALL SELECT 'customer.tab', 1
    UNION ALL SELECT 'customer.tab-content', 0
    UNION ALL SELECT 'feature.update-form', 0
    UNION ALL SELECT 'folder.seo.update-form', 0
    UNION ALL SELECT 'folder.update-form', 0
    UNION ALL SELECT 'gift-wrapping-edit.bottom', 0
    UNION ALL SELECT 'gift-wrapping-edit.top', 0
    UNION ALL SELECT 'gift-wrapping.bottom', 0
    UNION ALL SELECT 'gift-wrapping.edit-js', 0
    UNION ALL SELECT 'gift-wrapping.js', 0
    UNION ALL SELECT 'gift-wrapping.top', 0
    UNION ALL SELECT 'gift-wrapping.update-form', 0
    UNION ALL SELECT 'main.top-menu-reports', 1
    UNION ALL SELECT 'message.update-form', 0
    UNION ALL SELECT 'order-return-edit.bottom', 0
    UNION ALL SELECT 'order-return-edit.top', 0
    UNION ALL SELECT 'order-return-reason-edit.bottom', 0
    UNION ALL SELECT 'order-return-reason-edit.top', 0
    UNION ALL SELECT 'order-return-reason.bottom', 0
    UNION ALL SELECT 'order-return-reason.js', 0
    UNION ALL SELECT 'order-return-reason.top', 0
    UNION ALL SELECT 'order-return-reason.update-form', 0
    UNION ALL SELECT 'order-returns.bottom', 0
    UNION ALL SELECT 'order-returns.js', 0
    UNION ALL SELECT 'order-returns.top', 0
    UNION ALL SELECT 'order-status-edit.bottom', 0
    UNION ALL SELECT 'order-status-edit.top', 0
    UNION ALL SELECT 'order-status.edit-js', 0
    UNION ALL SELECT 'order-status.tab', 1
    UNION ALL SELECT 'order-status.tab-content', 0
    UNION ALL SELECT 'order-status.update-form', 0
    UNION ALL SELECT 'product-association-type-edit.bottom', 0
    UNION ALL SELECT 'product-association-type-edit.top', 0
    UNION ALL SELECT 'product-association-type.bottom', 0
    UNION ALL SELECT 'product-association-type.edit-js', 0
    UNION ALL SELECT 'product-association-type.js', 0
    UNION ALL SELECT 'product-association-type.top', 0
    UNION ALL SELECT 'product-association-type.update-form', 0
    UNION ALL SELECT 'product-edit.attributes-tab', 0
    UNION ALL SELECT 'product-edit.related-tab', 0
    UNION ALL SELECT 'product.clone-form', 0
    UNION ALL SELECT 'product.combinations-tab.bottom', 0
    UNION ALL SELECT 'product.combinations-tab.top', 0
    UNION ALL SELECT 'product.seo.update-form', 0
    UNION ALL SELECT 'product.update-form', 0
    UNION ALL SELECT 'products.bottom', 0
    UNION ALL SELECT 'products.top', 0
    UNION ALL SELECT 'reports.conversion.bottom', 0
    UNION ALL SELECT 'reports.conversion.top', 0
    UNION ALL SELECT 'system-information.bottom', 0
    UNION ALL SELECT 'system-information.js', 0
    UNION ALL SELECT 'system-information.top', 0
    UNION ALL SELECT 'tools.col2-bottom', 0
    UNION ALL SELECT 'tools.col2-top', 0
    UNION ALL SELECT 'variable.edit-form', 0
    UNION ALL SELECT 'administrator.edit-form', 0
    UNION ALL SELECT 'catalog-price-rule.delete-form', 0
    UNION ALL SELECT 'catalog-price-rule.table-header', 0
    UNION ALL SELECT 'catalog-price-rule.table-row', 0
    UNION ALL SELECT 'checkout-step.table-header', 0
    UNION ALL SELECT 'checkout-step.table-row', 0
    UNION ALL SELECT 'consent.table-header', 0
    UNION ALL SELECT 'consent.table-row', 0
    UNION ALL SELECT 'customer-title.delete-form', 0
    UNION ALL SELECT 'customer.anonymize-form', 0
    UNION ALL SELECT 'gift-wrapping.table-header', 0
    UNION ALL SELECT 'gift-wrapping.table-row', 0
    UNION ALL SELECT 'product-association-type.table-header', 0
    UNION ALL SELECT 'product-association-type.table-row', 0
    UNION ALL SELECT 'tag.delete-form', 0
) AS `missing`
WHERE NOT EXISTS (SELECT 1 FROM `hook` WHERE `hook`.`code` = `missing`.`code` AND `hook`.`type` = 2);

-- The English title, as the fresh install seeds it; the other languages have no wording yet.
INSERT INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`)
SELECT `hook`.`id`, `lang`.`locale`, IF(`lang`.`locale` = 'en_US', `missing`.`title`, NULL), NULL, NULL
FROM (
    SELECT 'advanced-configuration.bottom' AS `code`, 'Advanced configuration - bottom' AS `title`
    UNION ALL SELECT 'advanced-configuration.top', 'Advanced configuration - top'
    UNION ALL SELECT 'attribute.update-form', 'Attribute - update form'
    UNION ALL SELECT 'brand.seo.update-form', 'Brand - SEO update form'
    UNION ALL SELECT 'catalog-price-rule-edit.bottom', 'Catalog price rule edit - bottom'
    UNION ALL SELECT 'catalog-price-rule-edit.top', 'Catalog price rule edit - top'
    UNION ALL SELECT 'catalog-price-rule.bottom', 'Catalog price rule - bottom'
    UNION ALL SELECT 'catalog-price-rule.create-form', 'Catalog price rule - create form'
    UNION ALL SELECT 'catalog-price-rule.js', 'Catalog price rule - JavaScript'
    UNION ALL SELECT 'catalog-price-rule.top', 'Catalog price rule - top'
    UNION ALL SELECT 'catalog.tree.bottom', 'Catalog - tree bottom'
    UNION ALL SELECT 'catalog.tree.top', 'Catalog - tree top'
    UNION ALL SELECT 'category.seo.update-form', 'Category - SEO update form'
    UNION ALL SELECT 'category.update-form', 'Category - update form'
    UNION ALL SELECT 'checkout-step.bottom', 'Checkout step - bottom'
    UNION ALL SELECT 'checkout-step.js', 'Checkout step - JavaScript'
    UNION ALL SELECT 'checkout-step.top', 'Checkout step - top'
    UNION ALL SELECT 'configuration.customers.bottom', 'Configuration - customers bottom'
    UNION ALL SELECT 'configuration.customers.top', 'Configuration - customers top'
    UNION ALL SELECT 'configuration.store.bottom', 'Configuration - store bottom'
    UNION ALL SELECT 'configuration.store.top', 'Configuration - store top'
    UNION ALL SELECT 'consent-edit.bottom', 'Consent edit - bottom'
    UNION ALL SELECT 'consent-edit.top', 'Consent edit - top'
    UNION ALL SELECT 'consent.bottom', 'Consent - bottom'
    UNION ALL SELECT 'consent.edit-js', 'Consent - edit JavaScript'
    UNION ALL SELECT 'consent.js', 'Consent - JavaScript'
    UNION ALL SELECT 'consent.top', 'Consent - top'
    UNION ALL SELECT 'consent.update-form', 'Consent - update form'
    UNION ALL SELECT 'content.seo.update-form', 'Content - SEO update form'
    UNION ALL SELECT 'content.update-form', 'Content - update form'
    UNION ALL SELECT 'coupon.edit-bottom', 'Coupon - edit bottom'
    UNION ALL SELECT 'coupon.edit-top', 'Coupon - edit top'
    UNION ALL SELECT 'currency.edit-form', 'Currency - edit form'
    UNION ALL SELECT 'customer-edit.actions', 'Customer edit - actions'
    UNION ALL SELECT 'customer.personal-data', 'Customer - personal data'
    UNION ALL SELECT 'customer.tab', 'Customer - tab'
    UNION ALL SELECT 'customer.tab-content', 'Customer - tab content'
    UNION ALL SELECT 'feature.update-form', 'Feature - update form'
    UNION ALL SELECT 'folder.seo.update-form', 'Folder - SEO update form'
    UNION ALL SELECT 'folder.update-form', 'Folder - update form'
    UNION ALL SELECT 'gift-wrapping-edit.bottom', 'Gift wrapping edit - bottom'
    UNION ALL SELECT 'gift-wrapping-edit.top', 'Gift wrapping edit - top'
    UNION ALL SELECT 'gift-wrapping.bottom', 'Gift wrapping - bottom'
    UNION ALL SELECT 'gift-wrapping.edit-js', 'Gift wrapping - edit JavaScript'
    UNION ALL SELECT 'gift-wrapping.js', 'Gift wrapping - JavaScript'
    UNION ALL SELECT 'gift-wrapping.top', 'Gift wrapping - top'
    UNION ALL SELECT 'gift-wrapping.update-form', 'Gift wrapping - update form'
    UNION ALL SELECT 'main.top-menu-reports', 'Main - top menu reports'
    UNION ALL SELECT 'message.update-form', 'Message - update form'
    UNION ALL SELECT 'order-return-edit.bottom', 'Order return edit - bottom'
    UNION ALL SELECT 'order-return-edit.top', 'Order return edit - top'
    UNION ALL SELECT 'order-return-reason-edit.bottom', 'Order return reason edit - bottom'
    UNION ALL SELECT 'order-return-reason-edit.top', 'Order return reason edit - top'
    UNION ALL SELECT 'order-return-reason.bottom', 'Order return reason - bottom'
    UNION ALL SELECT 'order-return-reason.js', 'Order return reason - JavaScript'
    UNION ALL SELECT 'order-return-reason.top', 'Order return reason - top'
    UNION ALL SELECT 'order-return-reason.update-form', 'Order return reason - update form'
    UNION ALL SELECT 'order-returns.bottom', 'Order returns - bottom'
    UNION ALL SELECT 'order-returns.js', 'Order returns - JavaScript'
    UNION ALL SELECT 'order-returns.top', 'Order returns - top'
    UNION ALL SELECT 'order-status-edit.bottom', 'Order status edit - bottom'
    UNION ALL SELECT 'order-status-edit.top', 'Order status edit - top'
    UNION ALL SELECT 'order-status.edit-js', 'Order status - edit JavaScript'
    UNION ALL SELECT 'order-status.tab', 'Order status - tab'
    UNION ALL SELECT 'order-status.tab-content', 'Order status - tab content'
    UNION ALL SELECT 'order-status.update-form', 'Order status - update form'
    UNION ALL SELECT 'product-association-type-edit.bottom', 'Product association type edit - bottom'
    UNION ALL SELECT 'product-association-type-edit.top', 'Product association type edit - top'
    UNION ALL SELECT 'product-association-type.bottom', 'Product association type - bottom'
    UNION ALL SELECT 'product-association-type.edit-js', 'Product association type - edit JavaScript'
    UNION ALL SELECT 'product-association-type.js', 'Product association type - JavaScript'
    UNION ALL SELECT 'product-association-type.top', 'Product association type - top'
    UNION ALL SELECT 'product-association-type.update-form', 'Product association type - update form'
    UNION ALL SELECT 'product-edit.attributes-tab', 'Product edit - attributes tab'
    UNION ALL SELECT 'product-edit.related-tab', 'Product edit - related tab'
    UNION ALL SELECT 'product.clone-form', 'Product - clone form'
    UNION ALL SELECT 'product.combinations-tab.bottom', 'Product - combinations tab bottom'
    UNION ALL SELECT 'product.combinations-tab.top', 'Product - combinations tab top'
    UNION ALL SELECT 'product.seo.update-form', 'Product - SEO update form'
    UNION ALL SELECT 'product.update-form', 'Product - update form'
    UNION ALL SELECT 'products.bottom', 'Products - bottom'
    UNION ALL SELECT 'products.top', 'Products - top'
    UNION ALL SELECT 'reports.conversion.bottom', 'Reports - conversion bottom'
    UNION ALL SELECT 'reports.conversion.top', 'Reports - conversion top'
    UNION ALL SELECT 'system-information.bottom', 'System information - bottom'
    UNION ALL SELECT 'system-information.js', 'System information - JavaScript'
    UNION ALL SELECT 'system-information.top', 'System information - top'
    UNION ALL SELECT 'tools.col2-bottom', 'Tools - second column bottom'
    UNION ALL SELECT 'tools.col2-top', 'Tools - second column top'
    UNION ALL SELECT 'variable.edit-form', 'Variable - edit form'
    UNION ALL SELECT 'administrator.edit-form', 'Administrator - edit form'
    UNION ALL SELECT 'catalog-price-rule.delete-form', 'Catalog price rule - delete form'
    UNION ALL SELECT 'catalog-price-rule.table-header', 'Catalog price rule - table header'
    UNION ALL SELECT 'catalog-price-rule.table-row', 'Catalog price rule - table row'
    UNION ALL SELECT 'checkout-step.table-header', 'Checkout step - table header'
    UNION ALL SELECT 'checkout-step.table-row', 'Checkout step - table row'
    UNION ALL SELECT 'consent.table-header', 'Consent - table header'
    UNION ALL SELECT 'consent.table-row', 'Consent - table row'
    UNION ALL SELECT 'customer-title.delete-form', 'Customer title - delete form'
    UNION ALL SELECT 'customer.anonymize-form', 'Customer - anonymize form'
    UNION ALL SELECT 'gift-wrapping.table-header', 'Gift wrapping - table header'
    UNION ALL SELECT 'gift-wrapping.table-row', 'Gift wrapping - table row'
    UNION ALL SELECT 'product-association-type.table-header', 'Product association type - table header'
    UNION ALL SELECT 'product-association-type.table-row', 'Product association type - table row'
    UNION ALL SELECT 'tag.delete-form', 'Tag - delete form'
) AS `missing`
JOIN `hook` ON `hook`.`code` = `missing`.`code` AND `hook`.`type` = 2
JOIN (SELECT DISTINCT `locale` FROM `lang`) AS `lang`
WHERE NOT EXISTS (SELECT 1 FROM `hook_i18n` WHERE `hook_i18n`.`id` = `hook`.`id` AND `hook_i18n`.`locale` = `lang`.`locale`);

-- ---------------------------------------------------------------------
-- Shipping e-mail
--
-- The customer is told their order has left, with the carrier, the tracking
-- number and the tracking page, when the order enters the "sent" status. No
-- e-mail existed for it, so a shop that upgrades loses nothing by receiving it
-- switched on; INSERT IGNORE leaves alone a shop that already chose a value.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_shipped_email_enabled', '1', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'order_shipped_email_enabled' AS `name`, 'en_US' AS `locale`, 'Send the customer an e-mail when their order is shipped (1 = yes, 0 = no)' AS `title`
    UNION ALL SELECT 'order_shipped_email_enabled', 'fr_FR', 'Envoyer un e-mail au client quand sa commande est expédiée (1 = oui, 0 = non)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('order_shipped', NULL, 'order_shipped.txt', 'order_shipped.html', NOW(), NOW());

-- Read back by name rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @order_shipped_message_id := (SELECT `id` FROM `message` WHERE `name` = 'order_shipped');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@order_shipped_message_id, 'cs_CZ', NULL, 'Vaše objednávka {{ order_ref }} byla odeslána'),
    (@order_shipped_message_id, 'de_DE', 'Versandbestätigung an den Kunden gesendet', 'Ihre Bestellung {{ order_ref }} wurde versandt'),
    (@order_shipped_message_id, 'en_US', 'Shipping notice sent to the customer', 'Your order {{ order_ref }} has been shipped'),
    (@order_shipped_message_id, 'es_ES', 'Aviso de envío enviado al cliente', 'Tu pedido {{ order_ref }} ha sido enviado'),
    (@order_shipped_message_id, 'fr_FR', 'Avis d''expédition envoyé au client', 'Votre commande {{ order_ref }} a été expédiée'),
    (@order_shipped_message_id, 'it_IT', NULL, 'Il tuo ordine {{ order_ref }} è stato spedito'),
    (@order_shipped_message_id, 'nl_NL', 'Verzendbericht naar de klant verzonden', 'Je bestelling {{ order_ref }} is verzonden'),
    (@order_shipped_message_id, 'ru_RU', 'Уведомление об отправке отправлено клиенту', 'Ваш заказ {{ order_ref }} отправлен');

-- ---------------------------------------------------------------------
-- Delivery day and slot picked by the buyer
--
-- A carrier that accepts dates lets the buyer pick a day, and a slot of that
-- day when it offers slots. The settings live in four tables; the choice is
-- held on the cart and copied on the order. A shop that updates gets the
-- tables empty: no carrier has a rule, so the delivery step is unchanged until
-- the merchant sets one.
--
-- Tables are created only if missing and every column, index and key is
-- guarded, so the script can be replayed.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `delivery_date_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `choice_mode` VARCHAR(16) DEFAULT 'none' NOT NULL COMMENT 'what the buyer picks: none, date or slot',
    `minimum_delay_days` INTEGER DEFAULT 0 NOT NULL COMMENT 'how many days after today the first day offered is',
    `horizon_days` INTEGER DEFAULT 30 NOT NULL COMMENT 'how many days after today the last day offered is',
    `closed_weekdays` VARCHAR(20) COMMENT 'the days of the week the carrier does not deliver, ISO numbers from 1 (Monday) to 7 (Sunday) separated by commas; NULL follows the shop days',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `delivery_date_rule_module_id_UNIQUE` (`module_id`),
    CONSTRAINT `fk_delivery_date_rule_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_slot`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `start_time` TIME NOT NULL COMMENT 'the local hour the slot starts at',
    `end_time` TIME NOT NULL COMMENT 'the local hour the slot ends at',
    `capacity` INTEGER COMMENT 'how many orders the slot takes on a given day, NULL for no limit',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_delivery_slot_module_id` (`module_id`),
    CONSTRAINT `fk_delivery_slot_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_slot_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `delivery_slot_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `delivery_slot` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_closure`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER COMMENT 'the carrier the closure applies to, NULL for the whole shop',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `label` VARCHAR(255) COMMENT 'a note for the merchant, never shown to the buyer',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_delivery_closure_module_id_end_date` (`module_id`, `end_date`),
    CONSTRAINT `fk_delivery_closure_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_slot_booking`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `delivery_slot_id` INTEGER NOT NULL,
    `delivery_date` DATE NOT NULL,
    `booked` INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `delivery_slot_booking_slot_date_UNIQUE` (`delivery_slot_id`, `delivery_date`),
    CONSTRAINT `fk_delivery_slot_booking_delivery_slot_id`
        FOREIGN KEY (`delivery_slot_id`)
        REFERENCES `delivery_slot` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'delivery_date');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `delivery_date` DATE NULL COMMENT ''the delivery day the buyer picked for the selected carrier, NULL while they picked none'' AFTER `gift_message`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'delivery_slot_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `delivery_slot_id` INTEGER NULL COMMENT ''the slot of that day the buyer picked, when the carrier offers slots'' AFTER `delivery_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_delivery_slot_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_delivery_slot_id` (`delivery_slot_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_delivery_slot_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_delivery_slot_id` FOREIGN KEY (`delivery_slot_id`) REFERENCES `delivery_slot` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

-- The order keeps the day and the hours of the slot: a slot edited or deleted from the
-- carrier settings afterwards does not change what the order says. `order_version`
-- mirrors every column of `order`.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_date');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_date` DATE NULL COMMENT ''the day the buyer asked for, in the shop time zone, NULL when the carrier offered no date'' AFTER `gift_message`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_slot_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_slot_id` INTEGER NULL COMMENT ''the slot the buyer picked, NULL once that slot is deleted from the carrier settings'' AFTER `delivery_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_slot_start');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_slot_start` TIME NULL COMMENT ''the local hour the picked slot started at, copied off the slot'' AFTER `delivery_slot_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_slot_end');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_slot_end` TIME NULL COMMENT ''the local hour the picked slot ended at, copied off the slot'' AFTER `delivery_slot_start`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_date');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_date` DATE NULL COMMENT ''the day the buyer asked for, in the shop time zone, NULL when the carrier offered no date'' AFTER `gift_message`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_slot_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_slot_id` INTEGER NULL COMMENT ''the slot the buyer picked, NULL once that slot is deleted from the carrier settings'' AFTER `delivery_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_slot_start');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_slot_start` TIME NULL COMMENT ''the local hour the picked slot started at, copied off the slot'' AFTER `delivery_slot_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_slot_end');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_slot_end` TIME NULL COMMENT ''the local hour the picked slot ended at, copied off the slot'' AFTER `delivery_slot_start`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'idx_order_delivery_slot_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `idx_order_delivery_slot_id` (`delivery_slot_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_delivery_slot_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_delivery_slot_id` FOREIGN KEY (`delivery_slot_id`) REFERENCES `delivery_slot` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.delivery-date', NOW(), NOW());

SET @delivery_date_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.delivery-date');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@delivery_date_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'de_DE', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'en_US', 'Configuration delivery dates', NULL, NULL, NULL),
    (@delivery_date_resource_id, 'es_ES', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'fr_FR', 'Configuration des dates de livraison', NULL, NULL, NULL),
    (@delivery_date_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'nl_NL', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'ru_RU', NULL, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- German titles of the checkout steps
--
-- 3.1.0 named the four steps of the checkout in English, Spanish, French
-- and Italian only, while the shop ships German. A German storefront drew
-- its progress trail in the shop language instead ("Paiement" on a German
-- page of a French shop). The step is found by its code, as in 3.1.0, and
-- INSERT IGNORE keeps a German title the merchant already wrote, so the
-- statement can be replayed.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `checkout_step_i18n` (`id`, `locale`, `title`)
    SELECT `checkout_step`.`id`, 'de_DE', `wording`.`title`
    FROM `checkout_step`
    JOIN (
        SELECT 'cart' AS `code`, 'Ihr Warenkorb' AS `title`
        UNION ALL SELECT 'delivery', 'Lieferung'
        UNION ALL SELECT 'payment', 'Zahlung'
        UNION ALL SELECT 'confirmation', 'Bestätigung'
    ) AS `wording` ON `wording`.`code` = `checkout_step`.`code`;

-- ---------------------------------------------------------------------
-- Cumulative count of the returned quantity of an order line
--
-- 1, the rule until now: the returns already opened on a line hold part of
-- its quantity.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_return_cumulative_quantity', '1', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'order_return_cumulative_quantity' AS `name`, 'en_US' AS `locale`, 'Whether the returns already opened on an order line hold part of its quantity (1), or each return request is bounded by the ordered quantity alone (0)' AS `title`
    UNION ALL SELECT 'order_return_cumulative_quantity', 'fr_FR', 'Les retours déjà ouverts sur une ligne de commande retiennent une part de sa quantité (1), ou chaque demande de retour n''est bornée que par la quantité commandée (0)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- Order statuses a return may be opened on
--
-- Empty: any paid status, the rule until now.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_return_order_statuses', '', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'order_return_order_statuses' AS `name`, 'en_US' AS `locale`, 'Order statuses a customer may open a return on, comma separated status codes (empty: any paid status)' AS `title`
    UNION ALL SELECT 'order_return_order_statuses', 'fr_FR', 'Statuts de commande sur lesquels un client peut ouvrir un retour, codes séparés par des virgules (vide : tout statut payé)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- Shipments
--
-- An order may now leave in several parcels, each with its own lines,
-- tracking number and delivery note: a shipment. A shipment is a draft
-- while the merchant composes it, shipped once handed to the carrier,
-- cancelled when given up before it left. The tracking number the order
-- itself carries stays, the delivery modules keep writing it there.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `order_shipment`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `ref` VARCHAR(55) NOT NULL COMMENT 'the shipment number: the order reference followed by the rank of the shipment in the order',
    `status` VARCHAR(20) DEFAULT 'draft' NOT NULL COMMENT 'draft while being composed, shipped once handed to the carrier, cancelled when given up before it left',
    `delivery_module_id` INTEGER COMMENT 'the carrier module the parcel left with, NULL once that module is gone',
    `delivery_module_title` VARCHAR(255) COMMENT 'the carrier name snapshot, kept when the module is gone',
    `delivery_ref` VARCHAR(100) COMMENT 'the tracking number the carrier gave for this parcel',
    `shipped_at` DATETIME COMMENT 'when the parcel was handed to the carrier, NULL while it is a draft',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `order_shipment_ref_UNIQUE` (`ref`),
    INDEX `idx_order_shipment_order_id` (`order_id`),
    INDEX `idx_order_shipment_delivery_module_id` (`delivery_module_id`),
    CONSTRAINT `fk_order_shipment_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_shipment_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `order_shipment_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_shipment_id` INTEGER NOT NULL,
    `order_product_id` INTEGER NOT NULL,
    `quantity` FLOAT NOT NULL COMMENT 'how much of the order line this shipment carries',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `order_shipment_item_line_UNIQUE` (`order_shipment_id`, `order_product_id`),
    INDEX `idx_order_shipment_item_order_shipment_id` (`order_shipment_id`),
    INDEX `idx_order_shipment_item_order_product_id` (`order_product_id`),
    CONSTRAINT `fk_order_shipment_item_order_shipment_id`
        FOREIGN KEY (`order_shipment_id`)
        REFERENCES `order_shipment` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_shipment_item_order_product_id`
        FOREIGN KEY (`order_product_id`)
        REFERENCES `order_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- The partially shipped status
--
-- Where an order sits once some of its parcels left and some are still to
-- go. A custom status, declared equivalent to paid so that the stock, the
-- invoice and the documents keep behaving as on a paid order; the merchant
-- may rename it, recolour it or delete it. Listed after the existing ones.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `order_status` (`code`, `equivalent_code`, `color`, `position`, `protected_status`, `created_at`, `updated_at`)
SELECT 'partially_shipped', 'paid', '#20c997', `ranks`.`next_position`, 0, NOW(), NOW()
FROM (SELECT COALESCE(MAX(`position`), 0) + 1 AS `next_position` FROM `order_status`) AS `ranks`;

-- Read back by code rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @partially_shipped_status_id := (SELECT `id` FROM `order_status` WHERE `code` = 'partially_shipped');

INSERT IGNORE INTO `order_status_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
    (@partially_shipped_status_id, 'cs_CZ', 'Částečně odesláno', '', '', ''),
    (@partially_shipped_status_id, 'de_DE', 'Teilweise versandt', '', '', ''),
    (@partially_shipped_status_id, 'en_US', 'Partially shipped', '', '', ''),
    (@partially_shipped_status_id, 'es_ES', 'Enviado parcialmente', '', '', ''),
    (@partially_shipped_status_id, 'fr_FR', 'Partiellement expédiée', '', '', ''),
    (@partially_shipped_status_id, 'it_IT', 'Parzialmente spedito', '', '', ''),
    (@partially_shipped_status_id, 'nl_NL', 'Gedeeltelijk verzonden', '', '', ''),
    (@partially_shipped_status_id, 'ru_RU', 'Частично отправлен', '', '', '');

-- ---------------------------------------------------------------------
-- The orders that left before shipments existed
--
-- Each of them is shown as a single shipment carrying all its lines, so that
-- the customer account and the order sheet read the same whatever the age of
-- the order. An order in a sent status gets a shipped shipment, dated from its
-- last change; an order that only carries a tracking number, the label printed
-- but the order not marked sent, gets a draft, shipped when the order is.
--
-- Replayable: an order that already has a shipment is left alone, and only a
-- shipment with no line at all, which the application never writes, receives
-- the lines of its order.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `order_shipment` (`order_id`, `ref`, `status`, `delivery_module_id`, `delivery_module_title`, `delivery_ref`, `shipped_at`, `created_at`, `updated_at`)
SELECT
    `o`.`id`,
    CONCAT(`o`.`ref`, '-1'),
    IF(`s`.`code` = 'sent' OR `s`.`equivalent_code` = 'sent', 'shipped', 'draft'),
    `m`.`id`,
    `o`.`delivery_module_title`,
    NULLIF(`o`.`delivery_ref`, ''),
    IF(`s`.`code` = 'sent' OR `s`.`equivalent_code` = 'sent', `o`.`updated_at`, NULL),
    NOW(),
    NOW()
FROM `order` AS `o`
INNER JOIN `order_status` AS `s` ON `s`.`id` = `o`.`status_id`
LEFT JOIN `module` AS `m` ON `m`.`id` = `o`.`delivery_module_id`
WHERE (`s`.`code` = 'sent' OR `s`.`equivalent_code` = 'sent' OR (`o`.`delivery_ref` IS NOT NULL AND `o`.`delivery_ref` <> ''))
  AND NOT EXISTS (SELECT 1 FROM `order_shipment` AS `existing` WHERE `existing`.`order_id` = `o`.`id`);

INSERT IGNORE INTO `order_shipment_item` (`order_shipment_id`, `order_product_id`, `quantity`, `created_at`, `updated_at`)
SELECT `sh`.`id`, `op`.`id`, `op`.`quantity`, NOW(), NOW()
FROM `order_shipment` AS `sh`
INNER JOIN `order_product` AS `op` ON `op`.`order_id` = `sh`.`order_id`
WHERE NOT EXISTS (SELECT 1 FROM `order_shipment_item` AS `existing` WHERE `existing`.`order_shipment_id` = `sh`.`id`);

-- Shared cache for the public pages
--
-- A shop that upgrades keeps answering the way it did: the mode arrives
-- disabled, and INSERT IGNORE leaves alone a shop that already chose a value.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('shared_cache_mode', 'disabled', 0, 0, NOW(), NOW()),
    ('shared_cache_ttl', '300', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'shared_cache_mode' AS `name`, 'en_US' AS `locale`, 'Shared cache for anonymous public pages: disabled, announce (the page states how long a shared cache may keep it, cookies untouched) or enabled (also removes the cookies of an anonymous page so that a proxy can keep it)' AS `title`
    UNION ALL SELECT 'shared_cache_mode', 'fr_FR', 'Cache partagé des pages publiques anonymes : disabled (désactivé), announce (la page annonce combien de temps un cache partagé peut la garder, cookies inchangés) ou enabled (retire aussi les cookies d''une page anonyme pour qu''un proxy puisse la garder)'
    UNION ALL SELECT 'shared_cache_ttl', 'en_US', 'Seconds a shared cache may keep a public page (between 1 and 86400, 300 when invalid)'
    UNION ALL SELECT 'shared_cache_ttl', 'fr_FR', 'Secondes pendant lesquelles un cache partagé peut garder une page publique (entre 1 et 86400, 300 si invalide)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- The promotional price of an order line, a number again
--
-- `order_product`.`promo_price` is a VARCHAR(45) on a shop created by Thelia 2 and a
-- DECIMAL(16,6) on a fresh install, and no earlier script puts the first in the shape of
-- the second. The total of an order is computed by SQL, and ROUND() of a text reads it as
-- a floating point number: 63.325 rounds to 63.32, where the same price in the DECIMAL
-- column, and in the cart the buyer paid from, rounds to 63.33. An order whose promotional
-- line ends in a half cent was one cent short on its confirmation, its invoice and the
-- order list.
--
-- The values are numbers already, an empty text is read as no price. Nothing runs on a shop
-- whose column is a DECIMAL, so the script can be replayed.
-- ---------------------------------------------------------------------

SET @convert_column := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'promo_price' AND `DATA_TYPE` = 'varchar');
SET @statement := IF(@convert_column, 'UPDATE `order_product` SET `promo_price` = NULL WHERE `promo_price` = \'\'', 'DO 0');
PREPARE convert_column_statement FROM @statement;
EXECUTE convert_column_statement;
DEALLOCATE PREPARE convert_column_statement;

SET @statement := IF(@convert_column, 'ALTER TABLE `order_product` MODIFY `promo_price` DECIMAL(16,6) DEFAULT 0.000000', 'DO 0');
PREPARE convert_column_statement FROM @statement;
EXECUTE convert_column_statement;
DEALLOCATE PREPARE convert_column_statement;

-- ---------------------------------------------------------------------
-- Sale units
--
-- A sale unit is a place the shop sells from: a storefront on its own
-- domain, a pick-up store behind a shared one, a logical outlet an API
-- consumer addresses by header. The catalog stays national and shared;
-- a unit owns its prices, its stock and the orders placed on it.
--
-- The tables alone change nothing: with no active row, every code path
-- behaves exactly as before. The multi-sale-unit mode is on as soon as
-- one row is active.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sale_unit`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'stable machine name a deployment references a sale unit by; never shown to a customer',
    `url` VARCHAR(255) COMMENT 'the domain that resolves to this sale unit, scheme-less (shop-a.example.com); null for a unit resolved by session or API header only',
    `active` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'an inactive unit is never resolved; the multi-sale-unit mode is on as soon as one unit is active',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_code_unique` (`code`),
    UNIQUE INDEX `sale_unit_url_unique` (`url`),
    INDEX `idx_sale_unit_active` (`active`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sale_unit_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `sale_unit_i18n_fk_e2d6ae`
        FOREIGN KEY (`id`)
        REFERENCES `sale_unit` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- What a sale unit sells at when it does not sell at the national price: one row
-- per (unit, sale element, currency), read with the exact semantics of
-- `product_price` — same columns, same from_default_currency conversion, same
-- obedience to `product_sale_elements.promo`. A sale element without a row keeps
-- the national price: the fallback is the mode of compatibility, not an error.
CREATE TABLE IF NOT EXISTS `sale_unit_product_price`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_unit_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `promo_price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `from_default_currency` TINYINT(1) DEFAULT 1 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_product_price_unique` (`sale_unit_id`, `product_sale_elements_id`, `currency_id`),
    INDEX `idx_sale_unit_product_price_product_sale_elements_id` (`product_sale_elements_id`),
    INDEX `idx_sale_unit_product_price_currency_id` (`currency_id`),
    CONSTRAINT `fk_sale_unit_product_price_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_product_price_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_product_price_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The stock a sale unit keeps of its own: one row per (unit, sale element). A
-- sale element without a row for the unit decrements the shared national counter
-- `product_sale_elements.quantity` — right for a dematerialised or centralised
-- stock, a compatibility mode rather than the target for units with a stock of
-- their own, whose sale elements are expected to each carry a row.
CREATE TABLE IF NOT EXISTS `sale_unit_product_sale_elements`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_unit_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL,
    `quantity` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_pse_unique` (`sale_unit_id`, `product_sale_elements_id`),
    INDEX `idx_sale_unit_pse_product_sale_elements_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_sale_unit_pse_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_pse_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The sale unit an order was placed on, so a shop selling from many units can
-- tell whose order is whose. NULL for a national (single-shop) order, so an existing shop is
-- untouched. `order` is versionable: the column is mirrored on `order_version`.
-- Added with the information_schema guard MySQL 8 needs to replay the script.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'sale_unit_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `sale_unit_id` INTEGER NULL DEFAULT NULL COMMENT ''the sale unit this order was placed on, NULL for a national (single-shop) order'' AFTER `delivery_slot_end`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'fi_order_sale_unit_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `fi_order_sale_unit_id` (`sale_unit_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_fk := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_sale_unit_id');
SET @statement := IF(@add_fk, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_sale_unit_id` FOREIGN KEY (`sale_unit_id`) REFERENCES `sale_unit` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT', 'DO 0');
PREPARE add_fk_statement FROM @statement;
EXECUTE add_fk_statement;
DEALLOCATE PREPARE add_fk_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'sale_unit_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `sale_unit_id` INTEGER NULL DEFAULT NULL COMMENT ''the sale unit this order was placed on, NULL for a national (single-shop) order'' AFTER `delivery_slot_end`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.sale-unit', NOW(), NOW());

-- One row per language the fresh install seeds, so the profile screen falls
-- back on the default language instead of showing no row at all.
SET @sale_unit_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.sale-unit');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@sale_unit_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@sale_unit_resource_id, 'de_DE', 'Verkaufseinheiten', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'en_US', 'Sale units', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'es_ES', 'Unidades de venta', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'fr_FR', 'Unités de vente', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'it_IT', 'Unità di vendita', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'nl_NL', 'Verkoopeenheden', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'ru_RU', 'Точки продаж', NULL, NULL, NULL);

-- The sale unit a cart was last priced and stocked for. When the visitor moves
-- to another unit, the cart is checked again against that unit's prices and
-- stock. NULL for a national cart, so an existing shop is untouched.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'sale_unit_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD COLUMN `sale_unit_id` INTEGER NULL DEFAULT NULL COMMENT ''the sale unit the cart was last priced and stocked for, NULL for a national cart'' AFTER `delivery_slot_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_sale_unit_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_sale_unit_id` (`sale_unit_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_fk := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_sale_unit_id');
SET @statement := IF(@add_fk, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_sale_unit_id` FOREIGN KEY (`sale_unit_id`) REFERENCES `sale_unit` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_fk_statement FROM @statement;
EXECUTE add_fk_statement;
DEALLOCATE PREPARE add_fk_statement;

-- The sale units an administrator is restricted to. An administrator with no
-- row reaches every unit, as before; a superadministrator is never restricted.
-- A unit some administrator is restricted to cannot be deleted (RESTRICT):
-- the administrator would be left with no row, which reads as every unit.
CREATE TABLE IF NOT EXISTS `admin_sale_unit`
(
    `admin_id` INTEGER NOT NULL,
    `sale_unit_id` INTEGER NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`admin_id`,`sale_unit_id`),
    INDEX `idx_admin_sale_unit_sale_unit_id` (`sale_unit_id`),
    CONSTRAINT `fk_admin_sale_unit_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_admin_sale_unit_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
