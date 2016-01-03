SET FOREIGN_KEY_CHECKS = 0;

# ======================================================================================================================
# Add relation between order and cart
# ======================================================================================================================
ALTER TABLE `order`
  ADD COLUMN `cart_id` INTEGER NOT NULL
  AFTER `lang_id`
;

ALTER TABLE `order_version`
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

# ======================================================================================================================
# Add product_sale_elements_id IN order_product
# ======================================================================================================================

ALTER TABLE  `order_product`
  ADD  `product_sale_elements_id` INT NOT NULL
  AFTER  `product_sale_elements_ref`;

UPDATE `config` SET `value`='2.0.4' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

# ======================================================================================================================
# End of changes
# ======================================================================================================================

SET FOREIGN_KEY_CHECKS = 1;
