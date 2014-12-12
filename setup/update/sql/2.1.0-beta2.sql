SET FOREIGN_KEY_CHECKS = 0;

-- Hooks

-- front hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

NSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'account.additional', 1, 0, 1, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'fr_FR', 'Compte client - informations additionnelles', '', ''),
(@max_id + 1, 'en_US', 'Customer account - additional information', '', '')
;


SET FOREIGN_KEY_CHECKS = 1;