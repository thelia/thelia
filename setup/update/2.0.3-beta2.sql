SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `export` ADD INDEX `fk_export_1_idx` (`export_category_id`);
ALTER TABLE `import` ADD INDEX `fk_import_1_idx` (`import_category_id`);

SET FOREIGN_KEY_CHECKS = 1;