<?php
use Thelia\Constraint\ConstraintFactory;
use Thelia\Constraint\ConstraintManager;
use Thelia\Constraint\Rule\AvailableForTotalAmount;
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
use Thelia\Constraint\Rule\AvailableForXArticlesManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\ConditionCollection;
use Thelia\Model\ProductImage;
use Thelia\Model\CategoryImage;
use Thelia\Model\FolderImage;
use Thelia\Model\ContentImage;
use Imagine\Image\Color;
use Imagine\Image\Point;

require __DIR__ . '/../core/bootstrap.php';

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

    foreach(\Thelia\Model\ProductQuery::create()->find() as $productActiveRecord) {
        $productActiveRecord->setTaxRule($taxRule)
            ->save();
    }

    $con->commit();

} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}
