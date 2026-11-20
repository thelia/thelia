SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;
