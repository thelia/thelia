SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.3-beta2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='beta2' WHERE `name`='thelia_extra_version';

ALTER TABLE `export` ADD INDEX `fk_export_1_idx` (`export_category_id`);
ALTER TABLE `import` ADD INDEX `fk_import_1_idx` (`import_category_id`);

SET FOREIGN_KEY_CHECKS = 1;