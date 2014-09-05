SET FOREIGN_KEY_CHECKS = 0;

# ======================================================================================================================
# Add relation between order and cart
# ======================================================================================================================
ALTER TABLE `order`
  ADD COLUMN `cart_id` INTEGER NOT NULL
  AFTER `lang_id`
;

ALTER TABLE `order`
  ADD CONSTRAINT `fk_order_cart_id`
    FOREIGN KEY (`cart_id`) REFERENCES `cart`(`id`)
;

ALTER TABLE `order`
  ADD INDEX idx_order_cart_fk
    (`cart_id`)
;

SET FOREIGN_KEY_CHECKS = 1;
