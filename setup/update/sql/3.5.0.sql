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
SELECT 'company_admin', NULL, '["order.place","order.view_own","order.view_company","address.manage_shared","buyer.manage","company.edit"]', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `company_role` WHERE `code` = 'company_admin' AND `company_id` IS NULL);

INSERT INTO `company_role` (`code`, `company_id`, `permissions`, `created_at`, `updated_at`)
SELECT 'buyer', NULL, '["order.place","order.view_own"]', NOW(), NOW() FROM DUAL
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

SET FOREIGN_KEY_CHECKS = 1;
