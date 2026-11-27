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

-- ---------------------------------------------------------------------
-- Company accounts
--
-- A company groups customer accounts (`company_customer`), each with a role
-- (`company_role`, a JSON list of permissions) and optional spending limits.
-- An order keeps the company it was placed for and the account that placed it
-- (`order.company_id`, `order.placed_by_customer_id`), and an address can be
-- shared with a company (`address.company_id`). An invitation to join a company
-- is used once: `company_invitation_use` keeps the digest of each token that
-- was, and `company_departure` the digest of the address of each buyer the
-- company detached, which spends the invitations sent to it before. A shop without a company is untouched: every new column is nullable and no row is rewritten.
--
-- Replayable: each table is created through PREPARE/EXECUTE only when it is
-- missing (a bare CREATE TABLE IF NOT EXISTS commits the transaction
-- ModuleManagement opened, even when the table exists), each column, index and
-- foreign key is added only when missing, and the three roles every company
-- may give are seeded only while no role of the same code is shared by all
-- companies. `company` references `address`, which references `company`:
-- foreign key checks are off for the whole script.
-- ---------------------------------------------------------------------

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'company');
SET @statement := IF(@create_table, 'CREATE TABLE `company`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `siret` VARCHAR(20) COMMENT \'copied onto the billing address of the company each time the company is saved\',
    `vat_number` VARCHAR(20) COMMENT \'copied onto the billing address of the company each time the company is saved\',
    `parent_id` INTEGER COMMENT \'the company this one belongs to; a plain column with no foreign key, read by nothing yet\',
    `active` TINYINT DEFAULT 1 NOT NULL COMMENT \'a suspended company (0) keeps its buyers and its history but places no order\',
    `notification_email` VARCHAR(255) COMMENT \'receives a copy of every e-mail about an order of the company\',
    `internal_note` TEXT COMMENT \'a note for the shop administrators, never shown to the company\',
    `billing_address_id` INTEGER COMMENT \'the address invoices of the company are made out to, NULL when none is chosen\',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_company_parent_id` (`parent_id`),
    INDEX `idx_company_billing_address_id` (`billing_address_id`),
    CONSTRAINT `fk_company_billing_address_id`
        FOREIGN KEY (`billing_address_id`)
        REFERENCES `address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'company_role');
SET @statement := IF(@create_table, 'CREATE TABLE `company_role`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(100) NOT NULL COMMENT \'stable machine name of the role, which the code of the company looks it up by\',
    `company_id` INTEGER COMMENT \'the company the role belongs to, NULL for a role every company may give\',
    `permissions` TEXT NOT NULL COMMENT \'JSON list of the permissions the role grants, each a string\',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_company_role_code_company_id` (`code`, `company_id`),
    INDEX `idx_company_role_company_id` (`company_id`),
    CONSTRAINT `fk_company_role_company_id`
        FOREIGN KEY (`company_id`)
        REFERENCES `company` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'company_role_i18n');
SET @statement := IF(@create_table, 'CREATE TABLE `company_role_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT \'en_US\' NOT NULL,
    `title` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `company_role_i18n_fk_29ffc7`
        FOREIGN KEY (`id`)
        REFERENCES `company_role` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'company_customer');
SET @statement := IF(@create_table, 'CREATE TABLE `company_customer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `company_id` INTEGER NOT NULL,
    `customer_id` INTEGER NOT NULL COMMENT \'the buyer; an account belongs to one company at most\',
    `company_role_id` INTEGER NOT NULL,
    `order_limit` DECIMAL(16,6) COMMENT \'the most the buyer may spend on one order, taxes and delivery included, in the default currency; NULL for no limit\',
    `period_limit` DECIMAL(16,6) COMMENT \'the most the buyer may spend over one period, taxes and delivery included, in the default currency; NULL for no limit\',
    `period` VARCHAR(10) COMMENT \'the period period_limit counts over: week, month or year\',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `company_customer_customer_id_unique` (`customer_id`),
    INDEX `idx_company_customer_company_id` (`company_id`),
    INDEX `idx_company_customer_company_role_id` (`company_role_id`),
    CONSTRAINT `fk_company_customer_company_id`
        FOREIGN KEY (`company_id`)
        REFERENCES `company` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_company_customer_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_company_customer_company_role_id`
        FOREIGN KEY (`company_role_id`)
        REFERENCES `company_role` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'company_invitation_use');
SET @statement := IF(@create_table, 'CREATE TABLE `company_invitation_use`
(
    `fingerprint` CHAR(64) NOT NULL COMMENT \'SHA-256 of the invitation token, in hexadecimal; the key makes an invitation usable once, whatever became of the buyer it took in\',
    `company_id` INTEGER NOT NULL,
    `customer_id` INTEGER COMMENT \'the account the invitation took in; NULL once that account is deleted\',
    `addressee` CHAR(64) NOT NULL COMMENT \'SHA-256 of the address the invitation was sent to, lowercased, in hexadecimal: the earlier join of an address is found whatever address its account holds now\',
    `used_at` DATETIME NOT NULL,
    PRIMARY KEY (`fingerprint`),
    INDEX `idx_company_invitation_use_company_id` (`company_id`),
    INDEX `idx_company_invitation_use_customer_id` (`customer_id`),
    INDEX `idx_company_invitation_use_company_id_addressee` (`company_id`, `addressee`),
    CONSTRAINT `fk_company_invitation_use_company_id`
        FOREIGN KEY (`company_id`)
        REFERENCES `company` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_company_invitation_use_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'company_departure');
SET @statement := IF(@create_table, 'CREATE TABLE `company_departure`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `company_id` INTEGER NOT NULL,
    `addressee` CHAR(64) NOT NULL COMMENT \'SHA-256 of the address the account held when it left, lowercased, in hexadecimal: an invitation sent to that address before is spent\',
    `left_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_company_departure_company_id_addressee` (`company_id`, `addressee`),
    CONSTRAINT `fk_company_departure_company_id`
        FOREIGN KEY (`company_id`)
        REFERENCES `company` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'address' AND `COLUMN_NAME` = 'company_id');
SET @statement := IF(@add_column, 'ALTER TABLE `address` ADD COLUMN `company_id` INTEGER COMMENT ''the company the address is shared with, NULL for an address of the customer alone; customer_id stays the account that created it'' AFTER `is_default`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'address' AND `INDEX_NAME` = 'idx_address_company_id');
SET @statement := IF(@add_index, 'ALTER TABLE `address` ADD INDEX `idx_address_company_id` (`company_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'address' AND `CONSTRAINT_NAME` = 'fk_address_company_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `address` ADD CONSTRAINT `fk_address_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

-- On a shop that never had the customer group columns, the first of the two goes
-- after `sale_unit_id`, the last column of the published 3.3 `order`.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'company_id');
SET @after_column := IF((SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'customer_group_display_taxed_price') > 0, 'customer_group_display_taxed_price', 'sale_unit_id');
SET @statement := IF(@add_column, CONCAT('ALTER TABLE `order` ADD COLUMN `company_id` INTEGER COMMENT ''the company the order was placed for, frozen when it was placed: NULL for an order of a customer alone, kept when the buyer leaves the company'' AFTER `', @after_column, '`'), 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'placed_by_customer_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `placed_by_customer_id` INTEGER COMMENT ''the account that placed the order, NULL once that account is deleted; customer_id stays the owner of the order'' AFTER `company_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'company_id');
SET @after_column := IF((SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'customer_group_display_taxed_price') > 0, 'customer_group_display_taxed_price', 'sale_unit_id');
SET @statement := IF(@add_column, CONCAT('ALTER TABLE `order_version` ADD COLUMN `company_id` INTEGER COMMENT ''the company the order was placed for, frozen when it was placed: NULL for an order of a customer alone, kept when the buyer leaves the company'' AFTER `', @after_column, '`'), 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'placed_by_customer_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `placed_by_customer_id` INTEGER COMMENT ''the account that placed the order, NULL once that account is deleted; customer_id stays the owner of the order'' AFTER `company_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'placed_by_customer_id_version');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `placed_by_customer_id_version` INTEGER DEFAULT 0 AFTER `customer_id_version`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'idx_order_company_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `idx_order_company_id` (`company_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'idx_order_placed_by_customer_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `idx_order_placed_by_customer_id` (`placed_by_customer_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_company_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_placed_by_customer_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_placed_by_customer_id` FOREIGN KEY (`placed_by_customer_id`) REFERENCES `customer` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

-- The roles every company may give: one row per code with no company, added
-- only when no such row exists, so a role an administrator already created or
-- edited keeps its permissions. Their titles are read back by code.
INSERT INTO `company_role` (`code`, `company_id`, `permissions`, `created_at`, `updated_at`)
SELECT 'company_admin', NULL, '["order.place","order.view_own","order.view_company","address.manage_shared","buyer.manage","company.edit","quote.request"]', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `company_role` WHERE `code` = 'company_admin' AND `company_id` IS NULL);

INSERT INTO `company_role` (`code`, `company_id`, `permissions`, `created_at`, `updated_at`)
SELECT 'buyer', NULL, '["order.place","order.view_own","quote.request"]', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `company_role` WHERE `code` = 'buyer' AND `company_id` IS NULL);

INSERT INTO `company_role` (`code`, `company_id`, `permissions`, `created_at`, `updated_at`)
SELECT 'approver', NULL, '["order.view_own","order.view_company","order.approve"]', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `company_role` WHERE `code` = 'approver' AND `company_id` IS NULL);

SET @company_role_company_admin_id := (SELECT `id` FROM `company_role` WHERE `code` = 'company_admin' AND `company_id` IS NULL ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `company_role_i18n` (`id`, `locale`, `title`) VALUES
    (@company_role_company_admin_id, 'cs_CZ', NULL),
    (@company_role_company_admin_id, 'de_DE', 'Unternehmensadministrator'),
    (@company_role_company_admin_id, 'en_US', 'Company administrator'),
    (@company_role_company_admin_id, 'es_ES', 'Administrador de la empresa'),
    (@company_role_company_admin_id, 'fr_FR', 'Administrateur de la société'),
    (@company_role_company_admin_id, 'it_IT', 'Amministratore dell''azienda'),
    (@company_role_company_admin_id, 'nl_NL', 'Bedrijfsbeheerder'),
    (@company_role_company_admin_id, 'ru_RU', 'Администратор компании');

SET @company_role_buyer_id := (SELECT `id` FROM `company_role` WHERE `code` = 'buyer' AND `company_id` IS NULL ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `company_role_i18n` (`id`, `locale`, `title`) VALUES
    (@company_role_buyer_id, 'cs_CZ', NULL),
    (@company_role_buyer_id, 'de_DE', 'Einkäufer'),
    (@company_role_buyer_id, 'en_US', 'Buyer'),
    (@company_role_buyer_id, 'es_ES', 'Comprador'),
    (@company_role_buyer_id, 'fr_FR', 'Acheteur'),
    (@company_role_buyer_id, 'it_IT', 'Acquirente'),
    (@company_role_buyer_id, 'nl_NL', 'Inkoper'),
    (@company_role_buyer_id, 'ru_RU', 'Покупатель');

SET @company_role_approver_id := (SELECT `id` FROM `company_role` WHERE `code` = 'approver' AND `company_id` IS NULL ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `company_role_i18n` (`id`, `locale`, `title`) VALUES
    (@company_role_approver_id, 'cs_CZ', NULL),
    (@company_role_approver_id, 'de_DE', 'Genehmiger'),
    (@company_role_approver_id, 'en_US', 'Approver'),
    (@company_role_approver_id, 'es_ES', 'Aprobador'),
    (@company_role_approver_id, 'fr_FR', 'Valideur'),
    (@company_role_approver_id, 'it_IT', 'Approvatore'),
    (@company_role_approver_id, 'nl_NL', 'Goedkeurder'),
    (@company_role_approver_id, 'ru_RU', 'Утверждающий');

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.company', NOW(), NOW());

SET @company_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.company');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@company_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@company_resource_id, 'de_DE', 'Unternehmen', NULL, NULL, NULL),
    (@company_resource_id, 'en_US', 'Companies', NULL, NULL, NULL),
    (@company_resource_id, 'es_ES', 'Empresas', NULL, NULL, NULL),
    (@company_resource_id, 'fr_FR', 'Sociétés', NULL, NULL, NULL),
    (@company_resource_id, 'it_IT', 'Aziende', NULL, NULL, NULL),
    (@company_resource_id, 'nl_NL', 'Bedrijven', NULL, NULL, NULL),
    (@company_resource_id, 'ru_RU', 'Компании', NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- E-mail sent to the address a company invites to join it
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('company_invitation', NULL, 'company_invitation.txt', 'company_invitation.html', NOW(), NOW());

SET @company_invitation_message_id := (SELECT `id` FROM `message` WHERE `name` = 'company_invitation');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@company_invitation_message_id, 'cs_CZ', NULL, 'Připojte se k {{ company_name }} na {{ config("store_name") }}'),
    (@company_invitation_message_id, 'de_DE', 'Einladung, einem Unternehmen beizutreten, an die eingeladene Person gesendet', 'Treten Sie {{ company_name }} bei {{ config("store_name") }} bei'),
    (@company_invitation_message_id, 'en_US', 'Invitation to join a company, sent to the person invited', 'Join {{ company_name }} on {{ config("store_name") }}'),
    (@company_invitation_message_id, 'es_ES', 'Invitación a unirse a una empresa, enviada a la persona invitada', 'Únase a {{ company_name }} en {{ config("store_name") }}'),
    (@company_invitation_message_id, 'fr_FR', 'Invitation à rejoindre une société, envoyée à la personne invitée', 'Rejoignez {{ company_name }} sur {{ config("store_name") }}'),
    (@company_invitation_message_id, 'it_IT', NULL, 'Unisciti a {{ company_name }} su {{ config("store_name") }}'),
    (@company_invitation_message_id, 'nl_NL', 'Uitnodiging om zich bij een bedrijf aan te sluiten, verstuurd aan de uitgenodigde persoon', 'Word lid van {{ company_name }} bij {{ config("store_name") }}'),
    (@company_invitation_message_id, 'ru_RU', 'Приглашение присоединиться к компании, отправленное приглашённому', 'Присоединяйтесь к {{ company_name }} в {{ config("store_name") }}');

-- ---------------------------------------------------------------------
-- Purchase lists: decimal quantities
--
-- `customer_list_item.quantity` holds what a buyer saves for a sale element,
-- 1.25 kg of a product sold by measure as well as 3 pieces. It moves from an
-- INTEGER to a DOUBLE, as `cart_item.quantity` did in 3.4.0. A whole number
-- converts as it is: no saved list changes. The statement runs only while the
-- column is still an integer, so the script can be replayed.
--
SET @list_quantity_to_double := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_list_item' AND `COLUMN_NAME` = 'quantity' AND `DATA_TYPE` = 'int');
SET @statement := IF(@list_quantity_to_double, 'ALTER TABLE `customer_list_item` MODIFY `quantity` DOUBLE NOT NULL', 'DO 0');
PREPARE list_quantity_to_double_statement FROM @statement;
EXECUTE list_quantity_to_double_statement;
DEALLOCATE PREPARE list_quantity_to_double_statement;

-- ---------------------------------------------------------------------
-- PDF hooks of the picking slip template
--
-- The picking slip template calls these hooks, but no seed declared them: a module
-- subscribing to one was dropped when the container was built ("Hook preparation.css
-- is unknown."). A hook is only added when no PDF hook has its code, and its titles
-- only for the languages it has none in. The script can be replayed.
-- ---------------------------------------------------------------------

INSERT INTO `hook` (`code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`)
SELECT `missing`.`code`, 3, 0, 0, 1, 1, 1, NOW(), NOW()
FROM (
    SELECT 'preparation.css' AS `code`
    UNION ALL SELECT 'preparation.after-information'
    UNION ALL SELECT 'preparation.product-location'
) AS `missing`
WHERE NOT EXISTS (SELECT 1 FROM `hook` WHERE `hook`.`code` = `missing`.`code` AND `hook`.`type` = 3);

-- English and French titles, as the fresh install seeds them: the other languages have no wording yet.
INSERT INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`)
SELECT `hook`.`id`, `lang`.`locale`,
    CASE `lang`.`locale` WHEN 'en_US' THEN `missing`.`title_en` WHEN 'fr_FR' THEN `missing`.`title_fr` ELSE NULL END,
    NULL, NULL
FROM (
    SELECT 'preparation.css' AS `code`, 'Picking slip - CSS' AS `title_en`, 'Bon de préparation - CSS' AS `title_fr`
    UNION ALL SELECT 'preparation.after-information', 'Picking slip - after the order information', 'Bon de préparation - après les informations de la commande'
    UNION ALL SELECT 'preparation.product-location', 'Picking slip - storage location of a line', 'Bon de préparation - emplacement de stockage d\'une ligne'
) AS `missing`
JOIN `hook` ON `hook`.`code` = `missing`.`code` AND `hook`.`type` = 3
JOIN (SELECT DISTINCT `locale` FROM `lang`) AS `lang`
WHERE NOT EXISTS (SELECT 1 FROM `hook_i18n` WHERE `hook_i18n`.`id` = `hook`.`id` AND `hook_i18n`.`locale` = `lang`.`locale`);

-- ---------------------------------------------------------------------
-- Name of the picking slip view in the PDF template
--
-- `pdf_preparation_file` names the picking slip view of the PDF template, as
-- `pdf_invoice_file` and `pdf_delivery_file` name the two other documents, and
-- starts the name of the file downloaded. INSERT IGNORE leaves a value a shop
-- already wrote. Replaying the script changes nothing.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('pdf_preparation_file', 'preparation', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, 'en_US', 'Name of the picking slip view in the current PDF template (without extension)', NULL, NULL, NULL
FROM `config` WHERE `config`.`name` = 'pdf_preparation_file'
UNION ALL
SELECT `config`.`id`, 'fr_FR', 'Nom de la vue du bon de préparation dans le modèle PDF en cours (sans extension)', NULL, NULL, NULL
FROM `config` WHERE `config`.`name` = 'pdf_preparation_file';

-- ---------------------------------------------------------------------
-- Search dictionary
-- ---------------------------------------------------------------------

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'search_synonym');
SET @statement := IF(@create_table, 'CREATE TABLE `search_synonym` (`id` INTEGER NOT NULL AUTO_INCREMENT, `lang_id` INTEGER NOT NULL, `term` VARCHAR(255) NOT NULL, `synonyms` TEXT NOT NULL, `bidirectional` TINYINT DEFAULT 0 NOT NULL, `active` TINYINT DEFAULT 1 NOT NULL, `created_at` DATETIME, `updated_at` DATETIME, PRIMARY KEY (`id`), UNIQUE INDEX `search_synonym_lang_term_UNIQUE` (`lang_id`, `term`), CONSTRAINT `fk_search_synonym_lang_id` FOREIGN KEY (`lang_id`) REFERENCES `lang` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ROW_FORMAT=DYNAMIC', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'search_term_redirect');
SET @statement := IF(@create_table, 'CREATE TABLE `search_term_redirect` (`id` INTEGER NOT NULL AUTO_INCREMENT, `lang_id` INTEGER NOT NULL, `term` VARCHAR(255) NOT NULL, `target_type` VARCHAR(16) NOT NULL, `target_id` INTEGER, `url` VARCHAR(2048), `active` TINYINT DEFAULT 1 NOT NULL, `created_at` DATETIME, `updated_at` DATETIME, PRIMARY KEY (`id`), UNIQUE INDEX `search_term_redirect_lang_term_UNIQUE` (`lang_id`, `term`), INDEX `idx_search_term_redirect_target` (`target_type`, `target_id`), CONSTRAINT `fk_search_term_redirect_lang_id` FOREIGN KEY (`lang_id`) REFERENCES `lang` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ROW_FORMAT=DYNAMIC', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

-- ---------------------------------------------------------------------
-- Payment refund right
-- ---------------------------------------------------------------------
-- Giving money back is a right of its own, distinct from editing orders and from
-- taking money. It is granted to no profile. Replaying the script changes nothing.
--
-- setup/update/php/3.5.0.php then writes the capture of each order paid before the
-- payment journal of 3.4.0, so that what it collected can be refunded.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.order.payment-refund', NOW(), NOW());

SET @payment_refund_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.order.payment-refund');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@payment_refund_resource_id, 'cs_CZ', 'Vrácení platby objednávky', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'de_DE', 'Erstattung der Bestellzahlung', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'en_US', 'Order payment refund', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'es_ES', 'Reembolso del pago del pedido', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'fr_FR', 'Remboursement du paiement d\'une commande', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'it_IT', 'Rimborso del pagamento dell\'ordine', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'nl_NL', 'Terugbetaling van de orderbetaling', NULL, NULL, NULL),
    (@payment_refund_resource_id, 'ru_RU', 'Возврат оплаты заказа', NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Negotiated quotes
--
-- A buyer asks for a quote on a cart; the shop answers with revisions, line by
-- line, and the buyer accepts one, counters it or turns it down (`quote`,
-- `quote_revision`, `quote_item`, `quote_item_request`). An accepted quote is
-- paid through a cart that carries it (`cart.quote_id`, `cart_item.quote_item_id`,
-- `cart.set_aside_cart_id` for the ordinary cart set aside meanwhile) and gives at
-- most one order (`order.quote_id`, unique). A shop that never gets a quote is
-- untouched: every new column is nullable and no row is rewritten. The quote
-- mode starts disabled.
--
-- Replayable: each table is created through PREPARE/EXECUTE only when it is
-- missing, each column, index and foreign key is added only when missing, and
-- each setting, message and resource is inserted only when its name is unknown.
-- ---------------------------------------------------------------------

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote');
SET @statement := IF(@create_table, 'CREATE TABLE `quote`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(45) NOT NULL COMMENT \'the reference shown to the buyer and the shop, allocated when the quote is created\',
    `customer_id` INTEGER NOT NULL COMMENT \'the account the quote was requested by, or created for\',
    `company_id` INTEGER COMMENT \'the company the quote was requested for, NULL for a customer alone; kept when the company is deleted\',
    `state` VARCHAR(16) NOT NULL COMMENT \'draft, requested, quoted, countered, accepted, rejected, expired or ordered\',
    `current_revision` INTEGER DEFAULT 1 NOT NULL COMMENT \'the number of the revision the quote stands at\',
    `currency_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) COMMENT \'the language of the buyer, frozen when the quote is sent\',
    `buyer_comment` TEXT COMMENT \'what the buyer wrote when they asked for the quote\',
    `needed_by` DATE COMMENT \'the day the buyer needs the goods by\',
    `valid_until` DATE COMMENT \'the last day the quote can be accepted\',
    `sent_at` DATETIME COMMENT \'when the quote was last sent to the buyer\',
    `reminded_at` DATETIME COMMENT \'when the shop last reminded the buyer of the quote\',
    `coupon_dropped` TINYINT DEFAULT 0 NOT NULL COMMENT \'the coupon of the cart the quote was requested from was dropped: 1 once the buyer was told\',
    `invoice_address_id` INTEGER,
    `delivery_address_id` INTEGER,
    `delivery_module_id` INTEGER COMMENT \'the carrier the shop picked with the postage it negotiated\',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uk_quote_ref` (`ref`),
    INDEX `idx_quote_state` (`state`),
    INDEX `idx_quote_customer_id` (`customer_id`),
    INDEX `idx_quote_company_id` (`company_id`),
    INDEX `idx_quote_valid_until` (`valid_until`),
    INDEX `idx_quote_currency_id` (`currency_id`),
    INDEX `idx_quote_invoice_address_id` (`invoice_address_id`),
    INDEX `idx_quote_delivery_address_id` (`delivery_address_id`),
    INDEX `idx_quote_delivery_module_id` (`delivery_module_id`),
    CONSTRAINT `fk_quote_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_quote_company_id`
        FOREIGN KEY (`company_id`)
        REFERENCES `company` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_quote_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_quote_invoice_address_id`
        FOREIGN KEY (`invoice_address_id`)
        REFERENCES `address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_quote_delivery_address_id`
        FOREIGN KEY (`delivery_address_id`)
        REFERENCES `address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_quote_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision');
SET @statement := IF(@create_table, 'CREATE TABLE `quote_revision`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `quote_id` INTEGER NOT NULL,
    `number` INTEGER NOT NULL COMMENT \'1 is the request of the buyer, each sending of the shop adds one\',
    `author` VARCHAR(16) NOT NULL COMMENT \'customer or admin: who wrote the revision\',
    `admin_id` INTEGER COMMENT \'the administrator who wrote the revision, NULL when the buyer did or once the administrator is deleted\',
    `state` VARCHAR(16) NOT NULL COMMENT \'draft while it can be changed, sent once it is final\',
    `merchant_note` TEXT COMMENT \'what the shop wrote to the buyer with the revision\',
    `discount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT \'the discount on the whole revision, taxes included\',
    `postage` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT \'the postage of the revision, taxes excluded\',
    `total_untaxed` DECIMAL(16,6) COMMENT \'the total without taxes, frozen when the revision is sent\',
    `total_taxed` DECIMAL(16,6) COMMENT \'the total with taxes, frozen when the revision is sent\',
    `items_untaxed` DECIMAL(16,6) COMMENT \'the total of the lines without taxes, frozen when the revision is sent\',
    `items_tax` DECIMAL(16,6) COMMENT \'the taxes on the lines, frozen when the revision is sent\',
    `postage_tax` DECIMAL(16,6) COMMENT \'the taxes on the postage, frozen when the revision is sent\',
    `reverse_charge` TINYINT COMMENT \'1 when the buyer owes the VAT himself (reverse charge), frozen when the revision is sent\',
    `valid_until` DATE COMMENT \'the last day the revision can be accepted: chosen by the shop on a draft, kept as it was when the revision is sent\',
    `delivery_module_id` INTEGER COMMENT \'the carrier the revision proposes with its postage\',
    `sent_at` DATETIME COMMENT \'when the revision was sent\',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uk_quote_revision_quote_id_number` (`quote_id`, `number`),
    INDEX `idx_quote_revision_admin_id` (`admin_id`),
    INDEX `idx_quote_revision_delivery_module_id` (`delivery_module_id`),
    CONSTRAINT `fk_quote_revision_quote_id`
        FOREIGN KEY (`quote_id`)
        REFERENCES `quote` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_quote_revision_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_quote_revision_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

-- A revision table written by an earlier form of this script: the columns that freeze a revision
-- and the validity and carrier of a draft are added in the same order.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `COLUMN_NAME` = 'items_untaxed');
SET @statement := IF(@add_column, 'ALTER TABLE `quote_revision` ADD COLUMN `items_untaxed` DECIMAL(16,6) COMMENT ''the total of the lines without taxes, frozen when the revision is sent'' AFTER `total_taxed`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `COLUMN_NAME` = 'items_tax');
SET @statement := IF(@add_column, 'ALTER TABLE `quote_revision` ADD COLUMN `items_tax` DECIMAL(16,6) COMMENT ''the taxes on the lines, frozen when the revision is sent'' AFTER `items_untaxed`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `COLUMN_NAME` = 'postage_tax');
SET @statement := IF(@add_column, 'ALTER TABLE `quote_revision` ADD COLUMN `postage_tax` DECIMAL(16,6) COMMENT ''the taxes on the postage, frozen when the revision is sent'' AFTER `items_tax`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `COLUMN_NAME` = 'reverse_charge');
SET @statement := IF(@add_column, 'ALTER TABLE `quote_revision` ADD COLUMN `reverse_charge` TINYINT COMMENT ''1 when the buyer owes the VAT himself (reverse charge), frozen when the revision is sent'' AFTER `postage_tax`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `COLUMN_NAME` = 'valid_until');
SET @statement := IF(@add_column, 'ALTER TABLE `quote_revision` ADD COLUMN `valid_until` DATE COMMENT ''the last day the revision can be accepted: chosen by the shop on a draft, kept as it was when the revision is sent'' AFTER `reverse_charge`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `COLUMN_NAME` = 'delivery_module_id');
SET @statement := IF(@add_column, 'ALTER TABLE `quote_revision` ADD COLUMN `delivery_module_id` INTEGER COMMENT ''the carrier the revision proposes with its postage'' AFTER `valid_until`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `INDEX_NAME` = 'idx_quote_revision_delivery_module_id');
SET @statement := IF(@add_index, 'ALTER TABLE `quote_revision` ADD INDEX `idx_quote_revision_delivery_module_id` (`delivery_module_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision' AND `CONSTRAINT_NAME` = 'fk_quote_revision_delivery_module_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `quote_revision` ADD CONSTRAINT `fk_quote_revision_delivery_module_id` FOREIGN KEY (`delivery_module_id`) REFERENCES `module` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_revision_address');
SET @statement := IF(@create_table, 'CREATE TABLE `quote_revision_address`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `quote_revision_id` INTEGER NOT NULL,
    `kind` VARCHAR(16) NOT NULL COMMENT \'invoice or delivery\',
    `firstname` VARCHAR(255),
    `lastname` VARCHAR(255),
    `company` VARCHAR(255),
    `address1` VARCHAR(255),
    `address2` VARCHAR(255),
    `address3` VARCHAR(255),
    `zipcode` VARCHAR(10),
    `city` VARCHAR(255),
    `country_id` INTEGER COMMENT \'kept when the country is deleted: the title below stays\',
    `country_title` VARCHAR(255) COMMENT \'the name of the country in the language of the quote when the revision was sent\',
    `vat_number` VARCHAR(20) COMMENT \'the VAT number of the address when the revision was sent\',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uk_quote_revision_address_quote_revision_id_kind` (`quote_revision_id`, `kind`),
    INDEX `idx_quote_revision_address_country_id` (`country_id`),
    CONSTRAINT `fk_quote_revision_address_quote_revision_id`
        FOREIGN KEY (`quote_revision_id`)
        REFERENCES `quote_revision` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_quote_revision_address_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_item');
SET @statement := IF(@create_table, 'CREATE TABLE `quote_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `quote_revision_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `product_id` INTEGER COMMENT \'kept when the product is deleted: the line stays readable through its labels\',
    `product_sale_elements_id` INTEGER,
    `product_ref` VARCHAR(255) NOT NULL,
    `product_sale_elements_ref` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `quantity` DOUBLE NOT NULL,
    `catalog_price` DECIMAL(16,6) NOT NULL COMMENT \'the catalogue price of one unit without taxes when the line was written\',
    `price` DECIMAL(16,6) NOT NULL COMMENT \'the price of one unit without taxes the line proposes\',
    `tax_rule_id` INTEGER,
    `tax_rule_title` VARCHAR(255),
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_quote_item_quote_revision_id` (`quote_revision_id`),
    INDEX `idx_quote_item_product_id` (`product_id`),
    INDEX `idx_quote_item_product_sale_elements_id` (`product_sale_elements_id`),
    INDEX `idx_quote_item_tax_rule_id` (`tax_rule_id`),
    CONSTRAINT `fk_quote_item_quote_revision_id`
        FOREIGN KEY (`quote_revision_id`)
        REFERENCES `quote_revision` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_quote_item_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_quote_item_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_quote_item_tax_rule_id`
        FOREIGN KEY (`tax_rule_id`)
        REFERENCES `tax_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'quote_item_request');
SET @statement := IF(@create_table, 'CREATE TABLE `quote_item_request`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `quote_item_id` INTEGER NOT NULL,
    `quantity` DOUBLE COMMENT \'the quantity the buyer asks for, NULL when they leave it as it is\',
    `price` DECIMAL(16,6) COMMENT \'the price of one unit without taxes the buyer asks for, NULL when they leave it as it is\',
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uk_quote_item_request_quote_item_id` (`quote_item_id`),
    CONSTRAINT `fk_quote_item_request_quote_item_id`
        FOREIGN KEY (`quote_item_id`)
        REFERENCES `quote_item` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\' ROW_FORMAT=DYNAMIC;', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'quote_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD COLUMN `quote_id` INTEGER COMMENT ''the quote the cart carries the accepted revision of, NULL for an ordinary cart'' AFTER `sale_unit_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'quote_revision');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD COLUMN `quote_revision` INTEGER COMMENT ''the number of the accepted revision of the quote'' AFTER `quote_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'set_aside_cart_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD COLUMN `set_aside_cart_id` INTEGER COMMENT ''the ordinary cart of the buyer, set aside while the cart of a quote takes its place'' AFTER `quote_revision`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_quote_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_quote_id` (`quote_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_set_aside_cart_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_set_aside_cart_id` (`set_aside_cart_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_quote_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_quote_id` FOREIGN KEY (`quote_id`) REFERENCES `quote` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_set_aside_cart_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_set_aside_cart_id` FOREIGN KEY (`set_aside_cart_id`) REFERENCES `cart` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'quote_item_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_item` ADD COLUMN `quote_item_id` INTEGER COMMENT ''the line of a quote the line carries the frozen price of; NULL for an ordinary line'' AFTER `subscription_plan_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `INDEX_NAME` = 'idx_cart_item_quote_item_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart_item` ADD INDEX `idx_cart_item_quote_item_id` (`quote_item_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `CONSTRAINT_NAME` = 'fk_cart_item_quote_item_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `cart_item` ADD CONSTRAINT `fk_cart_item_quote_item_id` FOREIGN KEY (`quote_item_id`) REFERENCES `quote_item` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'quote_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `quote_id` INTEGER COMMENT ''the quote the order was placed from, NULL for an ordinary order; a quote gives one order at most'' AFTER `placed_by_customer_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'quote_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `quote_id` INTEGER COMMENT ''the quote the order was placed from, NULL for an ordinary order; a quote gives one order at most'' AFTER `placed_by_customer_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'uk_order_quote_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD UNIQUE INDEX `uk_order_quote_id` (`quote_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_quote_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_quote_id` FOREIGN KEY (`quote_id`) REFERENCES `quote` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

-- Settings. INSERT IGNORE leaves a value a shop already wrote.
INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('quote_mode', 'disabled', 0, 0, NOW(), NOW()),
    ('quote_validity_days', '30', 0, 0, NOW(), NOW()),
    ('quote_ref_format', 'QUO%08d', 0, 0, NOW(), NOW());

SET @quote_mode_config_id := (SELECT `id` FROM `config` WHERE `name` = 'quote_mode');

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@quote_mode_config_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@quote_mode_config_id, 'de_DE', 'Wie Käufer Angebote anfragen: disabled (keine Angebote), alongside (ein Angebot oder eine sofortige Bestellung) oder only (Bestellung nur über ein Angebot)', NULL, NULL, NULL),
    (@quote_mode_config_id, 'en_US', 'How buyers ask for quotes: disabled (no quote), alongside (a quote or an immediate order) or only (an order only through a quote)', NULL, NULL, NULL),
    (@quote_mode_config_id, 'es_ES', 'Cómo piden presupuesto los compradores: disabled (sin presupuesto), alongside (un presupuesto o un pedido inmediato) o only (pedido solo mediante un presupuesto)', NULL, NULL, NULL),
    (@quote_mode_config_id, 'fr_FR', 'Comment les acheteurs demandent un devis : disabled (aucun devis), alongside (un devis ou une commande immédiate) ou only (commande uniquement par un devis)', NULL, NULL, NULL),
    (@quote_mode_config_id, 'it_IT', 'Come gli acquirenti richiedono un preventivo: disabled (nessun preventivo), alongside (un preventivo o un ordine immediato) o only (ordine solo tramite un preventivo)', NULL, NULL, NULL),
    (@quote_mode_config_id, 'nl_NL', 'Hoe kopers een offerte aanvragen: disabled (geen offertes), alongside (een offerte of een directe bestelling) of only (bestellen alleen via een offerte)', NULL, NULL, NULL),
    (@quote_mode_config_id, 'ru_RU', 'Как покупатели запрашивают коммерческое предложение: disabled (без предложений), alongside (предложение или немедленный заказ) или only (заказ только через предложение)', NULL, NULL, NULL);

SET @quote_validity_days_config_id := (SELECT `id` FROM `config` WHERE `name` = 'quote_validity_days');

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@quote_validity_days_config_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'de_DE', 'Tage, die ein Angebot nach dem Versand gültig bleibt (30, wenn ungültig)', NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'en_US', 'Days a quote stays valid after it is sent (30 when invalid)', NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'es_ES', 'Días de validez de un presupuesto tras su envío (30 si no es válido)', NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'fr_FR', 'Jours de validité d''un devis après son envoi (30 si invalide)', NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'it_IT', 'Giorni di validità di un preventivo dopo l''invio (30 se non valido)', NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'nl_NL', 'Dagen dat een offerte geldig blijft na verzending (30 indien ongeldig)', NULL, NULL, NULL),
    (@quote_validity_days_config_id, 'ru_RU', 'Срок действия коммерческого предложения после отправки в днях (30, если значение недопустимо)', NULL, NULL, NULL);

SET @quote_ref_format_config_id := (SELECT `id` FROM `config` WHERE `name` = 'quote_ref_format');

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@quote_ref_format_config_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'de_DE', 'Format der Referenz eines Angebots, ein printf-Muster für die Folgenummer (QUO%08d, wenn ungültig)', NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'en_US', 'Format of the reference of a quote, a printf pattern for its sequence number (QUO%08d when invalid)', NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'es_ES', 'Formato de la referencia de un presupuesto, un patrón printf para su número de secuencia (QUO%08d si no es válido)', NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'fr_FR', 'Format de la référence d''un devis, un motif printf pour son numéro de séquence (QUO%08d si invalide)', NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'it_IT', 'Formato del riferimento di un preventivo, un modello printf per il suo numero di sequenza (QUO%08d se non valido)', NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'nl_NL', 'Formaat van de referentie van een offerte, een printf-patroon voor het volgnummer (QUO%08d indien ongeldig)', NULL, NULL, NULL),
    (@quote_ref_format_config_id, 'ru_RU', 'Формат номера коммерческого предложения, шаблон printf для порядкового номера (QUO%08d, если значение недопустимо)', NULL, NULL, NULL);

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.quote', NOW(), NOW());

SET @quote_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.quote');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@quote_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@quote_resource_id, 'de_DE', 'Angebote', NULL, NULL, NULL),
    (@quote_resource_id, 'en_US', 'Quotes', NULL, NULL, NULL),
    (@quote_resource_id, 'es_ES', 'Presupuestos', NULL, NULL, NULL),
    (@quote_resource_id, 'fr_FR', 'Devis', NULL, NULL, NULL),
    (@quote_resource_id, 'it_IT', 'Preventivi', NULL, NULL, NULL),
    (@quote_resource_id, 'nl_NL', 'Offertes', NULL, NULL, NULL),
    (@quote_resource_id, 'ru_RU', 'Коммерческие предложения', NULL, NULL, NULL);

-- The e-mails of the quote exchange.
INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('quote_requested', NULL, 'quote_requested.txt', 'quote_requested.html', NOW(), NOW()),
    ('quote_sent', NULL, 'quote_sent.txt', 'quote_sent.html', NOW(), NOW()),
    ('quote_countered', NULL, 'quote_countered.txt', 'quote_countered.html', NOW(), NOW()),
    ('quote_accepted', NULL, 'quote_accepted.txt', 'quote_accepted.html', NOW(), NOW()),
    ('quote_rejected', NULL, 'quote_rejected.txt', 'quote_rejected.html', NOW(), NOW());

SET @quote_requested_message_id := (SELECT `id` FROM `message` WHERE `name` = 'quote_requested');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@quote_requested_message_id, 'cs_CZ', NULL, 'Nový požadavek na nabídku {{ quote_ref }} na {{ config("store_name") }}'),
    (@quote_requested_message_id, 'de_DE', 'Nachricht an die Shop-Verantwortlichen, wenn ein Käufer ein Angebot anfragt', 'Neue Angebotsanfrage {{ quote_ref }} bei {{ config("store_name") }}'),
    (@quote_requested_message_id, 'en_US', 'Message sent to the shop managers when a buyer asks for a quote', 'New quote request {{ quote_ref }} on {{ config("store_name") }}'),
    (@quote_requested_message_id, 'es_ES', 'Mensaje enviado a los gestores de la tienda cuando un comprador pide un presupuesto', 'Nueva solicitud de presupuesto {{ quote_ref }} en {{ config("store_name") }}'),
    (@quote_requested_message_id, 'fr_FR', 'Message envoyé aux gestionnaires de la boutique quand un acheteur demande un devis', 'Nouvelle demande de devis {{ quote_ref }} sur {{ config("store_name") }}'),
    (@quote_requested_message_id, 'it_IT', 'Messaggio inviato ai gestori del negozio quando un acquirente richiede un preventivo', 'Nuova richiesta di preventivo {{ quote_ref }} su {{ config("store_name") }}'),
    (@quote_requested_message_id, 'nl_NL', 'Bericht aan de winkelbeheerders wanneer een koper een offerte aanvraagt', 'Nieuwe offerteaanvraag {{ quote_ref }} bij {{ config("store_name") }}'),
    (@quote_requested_message_id, 'ru_RU', 'Сообщение, отправляемое менеджерам магазина, когда покупатель запрашивает коммерческое предложение', 'Новый запрос коммерческого предложения {{ quote_ref }} в {{ config("store_name") }}');

SET @quote_sent_message_id := (SELECT `id` FROM `message` WHERE `name` = 'quote_sent');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@quote_sent_message_id, 'cs_CZ', NULL, 'Vaše nabídka {{ quote_ref }} na {{ config("store_name") }}'),
    (@quote_sent_message_id, 'de_DE', 'Nachricht an den Kunden, wenn der Shop ein Angebot sendet', 'Ihr Angebot {{ quote_ref }} bei {{ config("store_name") }}'),
    (@quote_sent_message_id, 'en_US', 'Message sent to the customer when the shop sends a quote', 'Your quote {{ quote_ref }} on {{ config("store_name") }}'),
    (@quote_sent_message_id, 'es_ES', 'Mensaje enviado al cliente cuando la tienda le envía un presupuesto', 'Su presupuesto {{ quote_ref }} en {{ config("store_name") }}'),
    (@quote_sent_message_id, 'fr_FR', 'Message envoyé au client quand la boutique lui envoie un devis', 'Votre devis {{ quote_ref }} sur {{ config("store_name") }}'),
    (@quote_sent_message_id, 'it_IT', 'Messaggio inviato al cliente quando il negozio gli invia un preventivo', 'Il tuo preventivo {{ quote_ref }} su {{ config("store_name") }}'),
    (@quote_sent_message_id, 'nl_NL', 'Bericht aan de klant wanneer de winkel een offerte verstuurt', 'Uw offerte {{ quote_ref }} bij {{ config("store_name") }}'),
    (@quote_sent_message_id, 'ru_RU', 'Сообщение, отправляемое клиенту, когда магазин присылает коммерческое предложение', 'Ваше коммерческое предложение {{ quote_ref }} в {{ config("store_name") }}');

SET @quote_countered_message_id := (SELECT `id` FROM `message` WHERE `name` = 'quote_countered');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@quote_countered_message_id, 'cs_CZ', NULL, 'Protinávrh k nabídce {{ quote_ref }} na {{ config("store_name") }}'),
    (@quote_countered_message_id, 'de_DE', 'Nachricht an die Shop-Verantwortlichen, wenn ein Käufer ein Angebot mit einem Gegenvorschlag beantwortet', 'Gegenvorschlag zum Angebot {{ quote_ref }} bei {{ config("store_name") }}'),
    (@quote_countered_message_id, 'en_US', 'Message sent to the shop managers when a buyer counters a quote', 'Counter-proposal on quote {{ quote_ref }} on {{ config("store_name") }}'),
    (@quote_countered_message_id, 'es_ES', 'Mensaje enviado a los gestores de la tienda cuando un comprador responde a un presupuesto con una contraoferta', 'Contraoferta sobre el presupuesto {{ quote_ref }} en {{ config("store_name") }}'),
    (@quote_countered_message_id, 'fr_FR', 'Message envoyé aux gestionnaires de la boutique quand un acheteur conteste un devis par une contre-proposition', 'Contre-proposition sur le devis {{ quote_ref }} sur {{ config("store_name") }}'),
    (@quote_countered_message_id, 'it_IT', 'Messaggio inviato ai gestori del negozio quando un acquirente risponde a un preventivo con una controproposta', 'Controproposta sul preventivo {{ quote_ref }} su {{ config("store_name") }}'),
    (@quote_countered_message_id, 'nl_NL', 'Bericht aan de winkelbeheerders wanneer een koper op een offerte antwoordt met een tegenvoorstel', 'Tegenvoorstel op offerte {{ quote_ref }} bij {{ config("store_name") }}'),
    (@quote_countered_message_id, 'ru_RU', 'Сообщение, отправляемое менеджерам магазина, когда покупатель отвечает на предложение встречным предложением', 'Встречное предложение по коммерческому предложению {{ quote_ref }} в {{ config("store_name") }}');

SET @quote_accepted_message_id := (SELECT `id` FROM `message` WHERE `name` = 'quote_accepted');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@quote_accepted_message_id, 'cs_CZ', NULL, 'Nabídka {{ quote_ref }} přijata na {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'de_DE', 'Nachricht an die Shop-Verantwortlichen, wenn ein Käufer ein Angebot annimmt', 'Angebot {{ quote_ref }} angenommen bei {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'en_US', 'Message sent to the shop managers when a buyer accepts a quote', 'Quote {{ quote_ref }} accepted on {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'es_ES', 'Mensaje enviado a los gestores de la tienda cuando un comprador acepta un presupuesto', 'Presupuesto {{ quote_ref }} aceptado en {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'fr_FR', 'Message envoyé aux gestionnaires de la boutique quand un acheteur accepte un devis', 'Devis {{ quote_ref }} accepté sur {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'it_IT', 'Messaggio inviato ai gestori del negozio quando un acquirente accetta un preventivo', 'Preventivo {{ quote_ref }} accettato su {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'nl_NL', 'Bericht aan de winkelbeheerders wanneer een koper een offerte accepteert', 'Offerte {{ quote_ref }} geaccepteerd bij {{ config("store_name") }}'),
    (@quote_accepted_message_id, 'ru_RU', 'Сообщение, отправляемое менеджерам магазина, когда покупатель принимает коммерческое предложение', 'Коммерческое предложение {{ quote_ref }} принято в {{ config("store_name") }}');

SET @quote_rejected_message_id := (SELECT `id` FROM `message` WHERE `name` = 'quote_rejected');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@quote_rejected_message_id, 'cs_CZ', NULL, 'Nabídka {{ quote_ref }} zamítnuta na {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'de_DE', 'Nachricht an die Shop-Verantwortlichen, wenn ein Käufer ein Angebot ablehnt', 'Angebot {{ quote_ref }} abgelehnt bei {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'en_US', 'Message sent to the shop managers when a buyer turns a quote down', 'Quote {{ quote_ref }} rejected on {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'es_ES', 'Mensaje enviado a los gestores de la tienda cuando un comprador rechaza un presupuesto', 'Presupuesto {{ quote_ref }} rechazado en {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'fr_FR', 'Message envoyé aux gestionnaires de la boutique quand un acheteur refuse un devis', 'Devis {{ quote_ref }} refusé sur {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'it_IT', 'Messaggio inviato ai gestori del negozio quando un acquirente rifiuta un preventivo', 'Preventivo {{ quote_ref }} rifiutato su {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'nl_NL', 'Bericht aan de winkelbeheerders wanneer een koper een offerte afwijst', 'Offerte {{ quote_ref }} afgewezen bij {{ config("store_name") }}'),
    (@quote_rejected_message_id, 'ru_RU', 'Сообщение, отправляемое менеджерам магазина, когда покупатель отклоняет коммерческое предложение', 'Коммерческое предложение {{ quote_ref }} отклонено в {{ config("store_name") }}');

SET FOREIGN_KEY_CHECKS = 1;
