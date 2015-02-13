<?php
if (php_sapi_name() != 'cli') {
    throw new \Exception('this script can only be launched with cli sapi');
}

$bootstrapToggle = false;
$bootstraped = false;

// Autoload bootstrap

foreach ($argv as $arg) {
    if ($arg === '-b') {
        $bootstrapToggle = true;

        continue;
    }

    if ($bootstrapToggle) {
        require __DIR__ . DIRECTORY_SEPARATOR . $arg;

        $bootstraped = true;
    }
}

if (!$bootstraped) {
    if (isset($bootstrapFile)) {
        require $bootstrapFile;
    } elseif (is_file($file = __DIR__ . '/../core/vendor/autoload.php')) {
        require $file;
    } elseif (is_file($file = __DIR__ . '/../../bootstrap.php')) {
        // Here we are on a thelia/thelia-project
        require $file;
    } else {
        echo "No autoload file found. Please use the -b argument to include yours";
        exit(1);
    }
}

$thelia = new Thelia\Core\Thelia("dev", true);
$thelia->boot();

$faker = Faker\Factory::create();

$con = \Propel\Runtime\Propel::getConnection(
    Thelia\Model\Map\ProductTableMap::DATABASE_NAME
);
$con->beginTransaction();

$currency = \Thelia\Model\CurrencyQuery::create()->filterByCode('EUR')->findOne();

try {
    $stmt = $con->prepare("SET foreign_key_checks = 0");
    $stmt->execute();

    \Thelia\Model\TaxQuery::create()
        ->find()
        ->delete();

    \Thelia\Model\Base\TaxRuleQuery::create()
        ->find()
        ->delete();

    \Thelia\Model\Base\TaxRuleCountryQuery::create()
        ->find()
        ->delete();

    $stmt = $con->prepare("SET foreign_key_checks = 1");
    $stmt->execute();

    /* 10% tax */
    $tax10p = new \Thelia\Model\Tax();
    $tax10p->setType('PricePercentTaxType')
        ->setRequirements(array('percent' => 10))
        ->save();

    /* 8% tax */
    $tax8p = new \Thelia\Model\Tax();
    $tax8p->setType('PricePercentTaxType')
        ->setRequirements(array('percent' => 8))
        ->save();

    /* fix 5 tax */
    $tax5 = new \Thelia\Model\Tax();
    $tax5->setType('FixAmountTaxType')
        ->setRequirements(array('amount' => 5))
        ->save();

    /* 1% tax */
    $tax1p = new \Thelia\Model\Tax();
    $tax1p->setType('PricePercentTaxType')
        ->setRequirements(array('percent' => 1))
        ->save();

    /* tax rule */
    $taxRule = new \Thelia\Model\TaxRule();
    $taxRule->save();

    /* add 4 taxes to the rule for France (64) */
    $taxRuleCountry = new \Thelia\Model\TaxRuleCountry();
    $taxRuleCountry->setTaxRule($taxRule)
        ->setCountryId(64)
        ->setTax($tax10p)
        ->setPosition(1)
        ->save();

    $taxRuleCountry = new \Thelia\Model\TaxRuleCountry();
    $taxRuleCountry->setTaxRule($taxRule)
        ->setCountryId(64)
        ->setTax($tax8p)
        ->setPosition(1)
        ->save();

    $taxRuleCountry = new \Thelia\Model\TaxRuleCountry();
    $taxRuleCountry->setTaxRule($taxRule)
        ->setCountryId(64)
        ->setTax($tax5)
        ->setPosition(2)
        ->save();

    $taxRuleCountry = new \Thelia\Model\TaxRuleCountry();
    $taxRuleCountry->setTaxRule($taxRule)
        ->setCountryId(64)
        ->setTax($tax1p)
        ->setPosition(3)
        ->save();

    foreach (\Thelia\Model\ProductQuery::create()->find() as $productActiveRecord) {
        $productActiveRecord->setTaxRule($taxRule)
            ->save();
    }

    $con->commit();

} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}
