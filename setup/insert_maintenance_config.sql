INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`)
VALUES
  ('purification_cart_no_order_days',  '60',  0, 0, NOW(), NOW()),
  ('purification_cart_anonymous_days', '30',  0, 0, NOW(), NOW()),
  ('purification_admin_logs_days',     '180', 0, 0, NOW(), NOW());
