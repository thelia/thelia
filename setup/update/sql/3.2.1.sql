SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tax types of a shop migrated from Thelia 2
--
-- A tax records the class of its type in `tax`.`type`, and the three types
-- moved from Thelia\TaxEngine\TaxType to Thelia\Domain\Taxation\TaxEngine\TaxType
-- in Thelia 3. Until this release the update only carried the percentage type
-- over, so a fixed amount tax or a tax on a product feature kept its Thelia 2
-- class name, which no tax type answers to any more: computing the price of a
-- product under such a rule failed with "Recorded type ... does not exists".
--
-- Each statement only matches a row still on the Thelia 2 name, so the
-- script can be replayed.
-- ---------------------------------------------------------------------

UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\PricePercentTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\PricePercentTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FixAmountTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FeatureFixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FeatureFixAmountTaxType';

SET FOREIGN_KEY_CHECKS = 1;
