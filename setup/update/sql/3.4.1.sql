SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Back-office hooks of the module columns of the combinations grid
-- ---------------------------------------------------------------------
-- The combinations tab of a product offers a column to modules through
-- `product.combinations-table-header` and `product.combinations-table-row`.
-- A module listens to a hook only if the hook has a row: without these two,
-- its column never shows. A hook goes in only when no back-office hook has
-- its code (a module may have declared it), its English title only for the
-- languages it has none in. The script can be replayed.
-- ---------------------------------------------------------------------

INSERT INTO `hook` (`code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`)
SELECT `missing`.`code`, 2, 0, `missing`.`block`, 1, 1, 1, NOW(), NOW()
FROM (
    SELECT 'product.combinations-table-header' AS `code`, 0 AS `block`
    UNION ALL SELECT 'product.combinations-table-row', 0
) AS `missing`
WHERE NOT EXISTS (SELECT 1 FROM `hook` WHERE `hook`.`code` = `missing`.`code` AND `hook`.`type` = 2);

INSERT INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`)
SELECT `hook`.`id`, `lang`.`locale`, IF(`lang`.`locale` = 'en_US', `missing`.`title`, NULL), NULL, NULL
FROM (
    SELECT 'product.combinations-table-header' AS `code`, 'Product - combinations table header' AS `title`
    UNION ALL SELECT 'product.combinations-table-row', 'Product - combinations table row'
) AS `missing`
JOIN `hook` ON `hook`.`code` = `missing`.`code` AND `hook`.`type` = 2
JOIN (SELECT DISTINCT `locale` FROM `lang`) AS `lang`
WHERE NOT EXISTS (SELECT 1 FROM `hook_i18n` WHERE `hook_i18n`.`id` = `hook`.`id` AND `hook_i18n`.`locale` = `lang`.`locale`);

SET FOREIGN_KEY_CHECKS = 1;
