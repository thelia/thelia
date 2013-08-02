<?php
require __DIR__ . '/../core/bootstrap.php';

$thelia = new Thelia\Core\Thelia("dev", true);

$faker = Faker\Factory::create();

$con = \Propel\Runtime\Propel::getConnection(Thelia\Model\Map\ProductTableMap::DATABASE_NAME);
$con->beginTransaction();

$currency = \Thelia\Model\CurrencyQuery::create()->filterByCode('EUR')->findOne();

try {

    $category = Thelia\Model\CategoryQuery::create()
        ->find();
    $category->delete();

    $product = Thelia\Model\ProductQuery::create()
        ->find();
    $product->delete();

    //first category
    $sweet = new Thelia\Model\Category();
    $sweet->setParent(0);
    $sweet->setVisible(1);
    $sweet->setPosition(1);
    $sweet->setDescription($faker->text(255));
    $sweet->setTitle($faker->bs);

    $sweet->save();

    //second category
    $jeans = new Thelia\Model\Category();
    $jeans->setParent(0);
    $jeans->setVisible(1);
    $jeans->setPosition(2);
    $jeans->setDescription($faker->text(255));
    $jeans->setTitle($faker->bs);

    $jeans->save();

    //third category
    $other = new Thelia\Model\Category();
    $other->setParent($jeans->getId());
    $other->setVisible(1);
    $other->setPosition(3);
    $other->setDescription($faker->text(255));
    $other->setTitle($faker->bs);

    $other->save();

    for ($i=1; $i <= 5; $i++) {
        $product = new \Thelia\Model\Product();
        $product->addCategory($sweet);
        $product->setTitle($faker->bs);
        $product->setDescription($faker->text(250));
/*        $product->setQuantity($faker->randomNumber(1,50));
        $product->setPrice($faker->randomFloat(2, 20, 2500));*/
        $product->setVisible(1);
        $product->setPosition($i);
        $product->setRef($faker->text(255));
        $product->save();

        $stock = new \Thelia\Model\ProductSaleElements();
        $stock->setProduct($product);
        $stock->setQuantity($faker->randomNumber(1,50));
        $stock->setPromo($faker->randomNumber(0,1));
        $stock->save();

        $productPrice = new \Thelia\Model\ProductPrice();
        $productPrice->setProductSaleElements($stock);
        $productPrice->setCurrency($currency);
        $productPrice->setPrice($faker->randomFloat(2, 20, 2500));
        $productPrice->save();

    }

    for ($i=1; $i <= 5; $i++) {
        $product = new \Thelia\Model\Product();
        $product->addCategory($jeans);
        $product->setTitle($faker->bs);
        $product->setDescription($faker->text(250));
/*        $product->setQuantity($faker->randomNumber(1,50));
        $product->setPrice($faker->randomFloat(2, 20, 2500));*/
        $product->setVisible(1);
        $product->setPosition($i);
        $product->setRef($faker->text(255));
        $product->save();

        $stock = new \Thelia\Model\ProductSaleElements();
        $stock->setProduct($product);
        $stock->setQuantity($faker->randomNumber(1,50));
        $stock->setPromo($faker->randomNumber(0,1));
        $stock->save();

        $productPrice = new \Thelia\Model\ProductPrice();
        $productPrice->setProductSaleElements($stock);
        $productPrice->setCurrency($currency);
        $productPrice->setPrice($faker->randomFloat(2, 20, 2500));
        $productPrice->save();

    }


    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}



