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

SET FOREIGN_KEY_CHECKS = 1;
