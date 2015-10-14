SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

ALTER TABLE `product_sale_elements_product_image` DROP FOREIGN KEY `fk_pse_product_image_product_sale_elements_id`;

ALTER TABLE product_sale_elements_product_image
ADD CONSTRAINT `fk_pse_product_image_product_sale_elements_id`
FOREIGN KEY (`product_sale_elements_id`)
REFERENCES `product_sale_elements` (`id`)
ON UPDATE RESTRICT
ON DELETE CASCADE;

ALTER TABLE `product_sale_elements_product_image` DROP FOREIGN KEY `fk_pse_product_image_product_image_id`;

ALTER TABLE product_sale_elements_product_image
ADD CONSTRAINT `fk_pse_product_image_product_image_id`
FOREIGN KEY (`product_image_id`)
REFERENCES `product_image` (`id`)
ON UPDATE RESTRICT
ON DELETE CASCADE;

ALTER TABLE `product_sale_elements_product_document` DROP FOREIGN KEY `fk_pse_product_document_product_sale_elements_id`;

ALTER TABLE product_sale_elements_product_document
ADD CONSTRAINT `fk_pse_product_document_product_sale_elements_id`
FOREIGN KEY (`product_sale_elements_id`)
REFERENCES `product_sale_elements` (`id`)
ON UPDATE RESTRICT
ON DELETE CASCADE;

ALTER TABLE `product_sale_elements_product_document` DROP FOREIGN KEY `fk_pse_product_document_product_document_id`;

ALTER TABLE product_sale_elements_product_document
ADD CONSTRAINT `fk_pse_product_document_product_document_id`
FOREIGN KEY (`product_document_id`)
REFERENCES `product_document` (`id`)
ON UPDATE RESTRICT
ON DELETE CASCADE;

 SET FOREIGN_KEY_CHECKS = 1;