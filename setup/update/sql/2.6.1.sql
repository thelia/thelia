SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.6.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='6' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';


# ======================================================================================================================
# store informations IN config
# ======================================================================================================================

SET @locale := (
  SELECT locale
  FROM lang
  WHERE by_default = 1
  LIMIT 1
);

UPDATE config
SET name = CONCAT(name, '_', @locale)
WHERE name IN (
    'store_name',
    'store_description',
    'store_email',
    'store_notification_emails',
    'store_business_id',
    'store_phone',
    'store_fax',
    'store_address1',
    'store_address2',
    'store_address3',
    'store_zipcode',
    'store_city'
);

# ======================================================================================================================
# Add file IN product_image_i18n
# ======================================================================================================================

ALTER TABLE product_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

INSERT INTO product_image_i18n (id, locale, file)
SELECT pi.id,
       (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1) AS locale,
       pi.file
FROM product_image pi
    LEFT JOIN product_image_i18n pii
ON pii.id = pi.id
    AND pii.locale = (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1)
WHERE pii.id IS NULL;

UPDATE product_image_i18n AS dest
INNER JOIN product_image AS src ON dest.id = src.id
SET dest.file = src.file;

ALTER TABLE product_image DROP COLUMN `file`;

# ======================================================================================================================
# Add file IN category_image_i18n
# ======================================================================================================================

ALTER TABLE category_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

INSERT INTO category_image_i18n (id, locale, file)
SELECT ci.id,
       (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1) AS locale,
       ci.file
FROM category_image ci
    LEFT JOIN category_image_i18n cii
ON cii.id = ci.id
    AND cii.locale = (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1)
WHERE cii.id IS NULL;

UPDATE category_image_i18n AS dest
INNER JOIN category_image AS src ON dest.id = src.id
SET dest.file = src.file;

ALTER TABLE category_image DROP COLUMN `file`;

# ======================================================================================================================
# Add file IN folder_image_i18n
# ======================================================================================================================

ALTER TABLE folder_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

INSERT INTO folder_image_i18n (id, locale, file)
SELECT fi.id,
       (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1) AS locale,
       fi.file
FROM folder_image fi
    LEFT JOIN folder_image_i18n fii
ON fii.id = fi.id
    AND fii.locale = (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1)
WHERE fii.id IS NULL;

UPDATE folder_image_i18n AS dest
    INNER JOIN folder_image AS src ON dest.id = src.id
    SET dest.file = src.file;

ALTER TABLE folder_image DROP COLUMN `file`;

# ======================================================================================================================
# Add file IN content_image_i18n
# ======================================================================================================================

ALTER TABLE content_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

INSERT INTO content_image_i18n (id, locale, file)
SELECT ci.id,
       (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1) AS locale,
       ci.file
FROM content_image ci
    LEFT JOIN content_image_i18n cii
ON cii.id = ci.id
    AND cii.locale = (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1)
WHERE cii.id IS NULL;

UPDATE content_image_i18n AS dest
INNER JOIN content_image AS src ON dest.id = src.id
SET dest.file = src.file;

ALTER TABLE content_image DROP COLUMN `file`;

# ======================================================================================================================
# Add file IN module_image_i18n
# ======================================================================================================================

ALTER TABLE module_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

INSERT INTO module_image_i18n (id, locale, file)
SELECT mi.id,
       (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1) AS locale,
       mi.file
FROM module_image mi
    LEFT JOIN module_image_i18n mii
ON mii.id = mi.id
    AND mii.locale = (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1)
WHERE mii.id IS NULL;

UPDATE module_image_i18n AS dest
    INNER JOIN module_image AS src ON dest.id = src.id
    SET dest.file = src.file;

ALTER TABLE module_image DROP COLUMN `file`;

# ======================================================================================================================
# Add file IN brand_image_i18n
# ======================================================================================================================

ALTER TABLE brand_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

INSERT INTO brand_image_i18n (id, locale, file)
SELECT bi.id,
       (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1) AS locale,
       bi.file
FROM brand_image bi
    LEFT JOIN brand_image_i18n bii
ON bii.id = bi.id
    AND bii.locale = (SELECT locale FROM lang WHERE by_default = 1 LIMIT 1)
WHERE bii.id IS NULL;

UPDATE brand_image_i18n AS dest
    INNER JOIN brand_image AS src ON dest.id = src.id
    SET dest.file = src.file;

ALTER TABLE brand_image DROP COLUMN `file`;

# ======================================================================================================================
# End of changes
# ======================================================================================================================


SET FOREIGN_KEY_CHECKS = 1;
