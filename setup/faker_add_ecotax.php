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

// Intialize URL management

try {
    $options = getopt('f::e::');

    $forceEcotaxFeatureId = $options['f'];
    if (null !== $forceEcotaxFeatureId && !filter_var($forceEcotaxFeatureId, FILTER_VALIDATE_INT)) {
        exit('[ERROR] bad value for f option\n');
    }

    $forceEcotaxId = $options['e'];
    if (null !== $forceEcotaxId && !filter_var($forceEcotaxId, FILTER_VALIDATE_INT)) {
        exit('[ERROR] bad value for e option\n');
    }

    echo "Adding Ecotax feature\n";
    $feature = null;
    if (null !== $forceEcotaxFeatureId) {
        $feature = \Thelia\Model\FeatureQuery::create()->findPk($forceEcotaxFeatureId);
        if (null === $feature) {
            echo "Feature `$forceEcotaxFeatureId` not found\n";
        }
    }
    if (null === $feature) {
        $feature = new \Thelia\Model\Feature();
        $feature->setVisible(1);
        $feature->save();
        echo sprintf("Ecotax feature added with ID \n", $feature->getId());
    }

    $fr = \Thelia\Model\Base\FeatureI18nQuery::create()
        ->filterByLocale('fr_FR')
        ->filterByFeature($feature)
        ->findOne();
    if (null === $fr) {
        $fr = new \Thelia\Model\FeatureI18n();
        $fr->setLocale('fr_FR')
            ->setFeature($feature);
    }
    $fr->setTitle('Ecotaxe');
    $fr->save($con);

    $us = \Thelia\Model\Base\FeatureI18nQuery::create()
        ->filterByLocale('en_US')
        ->filterByFeature($feature)
        ->findOne();
    if (null === $us) {
        $us = new \Thelia\Model\FeatureI18n();
        $us->setLocale('en_US')
            ->setFeature($feature);
    }
    $us->setTitle('Ecotax');
    $us->save($con);

    echo "Adding ecotax\n";

    $tax = null;
    if (null !== $forceEcotaxId) {
        $tax = \Thelia\Model\TaxQuery::create()->findPk($forceEcotaxId);
        if (null === $tax) {
            echo "Tax `$forceEcotaxId` not found\n";
        }
    }
    if (null === $tax) {
        $tax = new \Thelia\Model\Tax();
        $tax->setType('FeatureFixAmountTaxType')
            ->setSerializedRequirements(
                base64_encode(sprintf('{"feature":%s}', $feature->getId()))
            );
        $tax->save();
        echo sprintf("Ecotax added with ID \n", $tax->getId());
    }

    $fr = \Thelia\Model\Base\TaxI18nQuery::create()
        ->filterByLocale('fr_FR')
        ->filterByTax($tax)
        ->findOne();
    if (null === $fr) {
        $fr = new \Thelia\Model\TaxI18n();
        $fr->setLocale('fr_FR')
            ->setTax($tax);
    }
    $fr->setTitle('Ecotaxe');
    $fr->save($con);

    $us = \Thelia\Model\Base\TaxI18nQuery::create()
        ->filterByLocale('en_US')
        ->filterByTax($tax)
        ->findOne();
    if (null === $us) {
        $us = new \Thelia\Model\TaxI18n();
        $us->setLocale('en_US')
            ->setTax($tax);
    }
    $us->setTitle('Ecotax');
    $us->save($con);

    $con->commit();

    echo "Successfully terminated.\n";

} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}
