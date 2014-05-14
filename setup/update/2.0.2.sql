# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

# Remove useless rewriting_url indexes
ALTER TABLE `rewriting_url` DROP INDEX `idx_rewriting_url_view_updated_at`;
ALTER TABLE `rewriting_url` DROP INDEX `idx_rewriting_url_view_id_view_view_locale_updated_at`;

SET FOREIGN_KEY_CHECKS = 1;