SET FOREIGN_KEY_CHECKS = 0;

-- Hooks

-- front hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'account.additional', 1, 0, 1, 1, 1, 1, NOW(), NOW()),

(@max_id + 2, 'product.modification.form_top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'product.modification.form_bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Customer account - additional information' locale=$locale}, '', ''),
(@max_id + 2, '{$locale}', {intl l='Product page - On the top of the form' locale=$locale}, '', ''),
(@max_id + 3, '{$locale}', {intl l='Product page - On the bottom of the form' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;