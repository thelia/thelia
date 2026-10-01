-- Retires a column, an index and a foreign key that TheliaMain.sql no longer creates.

ALTER TABLE `absent_drop_probe` DROP FOREIGN KEY `fk_absent_drop_probe_retired`;

ALTER TABLE `absent_drop_probe` DROP INDEX `idx_absent_drop_probe_retired`;

ALTER TABLE `absent_drop_probe` DROP COLUMN `retired_id`;
