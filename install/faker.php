<?php
require __DIR__ . '/../core/bootstrap.php';

$thelia = new Thelia\Core\Thelia("dev", true);

$faker = Faker\Factory::create();

$con = \Propel::getConnection(Thelia\Model\ProductPeer::DATABASE_NAME);
$con->beginTransaction();
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
        $product->setQuantity($faker->randomNumber(1,50));
        $product->setPrice($faker->randomFloat(2, 20, 2500));
        $product->setVisible(1);
        $product->setPosition($i);
        $product->setRef($faker->text(255));
        $product->save();

    }

    for ($i=1; $i <= 5; $i++) {
        $product = new \Thelia\Model\Product();
        $product->addCategory($jeans);
        $product->setTitle($faker->bs);
        $product->setDescription($faker->text(250));
        $product->setQuantity($faker->randomNumber(1,50));
        $product->setPrice($faker->randomFloat(2, 20, 2500));
        $product->setVisible(1);
        $product->setPosition($i);
        $product->setRef($faker->text(255));
        $product->save();

    }


    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}



