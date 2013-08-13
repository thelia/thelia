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

    $customer = Thelia\Model\CustomerQuery::create()
        ->find();
    $customer->delete();

    $customer = new Thelia\Model\Customer();
    $customer->createOrUpdate(
        1,
        "thelia",
        "thelia",
        "5 rue rochon",
        "",
        "",
        "0102030405",
        "0601020304",
        "63000",
        "clermont-ferrand",
        64,
        "test@thelia.net",
        "azerty"
    );


    $folder = Thelia\Model\FolderQuery::create()
        ->find();
    $folder->delete();

    $content = Thelia\Model\ContentQuery::create()
        ->find();
    $content->delete();

    $accessory = Thelia\Model\AccessoryQuery::create()
        ->find();
    $accessory->delete();

    //features and features_av
    $featureList = array();
    for($i=0; $i<4; $i++) {
        $feature = new Thelia\Model\Feature();
        $feature->setVisible(rand(1, 10)>7 ? 0 : 1);
        $feature->setPosition($i);
        $feature->setTitle($faker->text(20));
        $feature->setDescription($faker->text(50));

        $feature->save();
        $featureId = $feature->getId();
        $featureList[$featureId] = array();

        for($j=0; $j<rand(1, 5); $j++) {
            $featureAv = new Thelia\Model\FeatureAv();
            $featureAv->setFeature($feature);
            $featureAv->setPosition($j);
            $featureAv->setTitle($faker->text(20));
            $featureAv->setDescription($faker->text(255));

            $featureAv->save();
            $featureList[$featureId][] = $featureAv->getId();
        }
    }

    //categories and products
    $productIdList = array();
    $categoryIdList = array();
    for($i=0; $i<4; $i++) {
        $category = new Thelia\Model\Category();
        $category->setParent(0);
        $category->setVisible(rand(1, 10)>7 ? 0 : 1);
        $category->setPosition($i);
        $category->setTitle($faker->text(20));
        $category->setDescription($faker->text(255));

        $category->save();
        $categoryIdList[] = $category->getId();

        for($j=0; $j<rand(0, 4); $j++) {
            $subcategory = new Thelia\Model\Category();
            $subcategory->setParent($category->getId());
            $subcategory->setVisible(rand(1, 10)>7 ? 0 : 1);
            $subcategory->setPosition($j);
            $subcategory->setTitle($faker->text(20));
            $subcategory->setDescription($faker->text(255));

            $subcategory->save();
            $categoryIdList[] = $subcategory->getId();

            for($k=0; $k<rand(1, 5); $k++) {
                $product = new Thelia\Model\Product();
                $product->setRef($subcategory->getId() . '_' . $k . '_' . $faker->randomNumber(8));
                $product->addCategory($subcategory);
                $product->setVisible(rand(1, 10)>7 ? 0 : 1);
                $product->setPosition($k);
                $product->setTitle($faker->text(20));
                $product->setDescription($faker->text(255));

                $product->save();
                $productId = $product->getId();
                $productIdList[] = $productId;

                //add random accessories - or not
                for($l=0; $l<rand(0, 3); $l++) {
                    $accessory = new Thelia\Model\Accessory();
                    $accessory->setAccessory($productIdList[array_rand($productIdList, 1)]);
                    $accessory->setProductId($productId);
                    $accessory->setPosition($l);

                    $accessory->save();
                }
            }
        }

        for($k=0; $k<rand(1, 5); $k++) {
            $product = new Thelia\Model\Product();
            $product->setRef($category->getId() . '_' . $k . '_' . $faker->randomNumber(8));
            $product->addCategory($category);
            $product->setVisible(rand(1, 10)>7 ? 0 : 1);
            $product->setPosition($k);
            $product->setTitle($faker->text(20));
            $product->setDescription($faker->text(255));

            $product->save();
            $productId = $product->getId();
            $productIdList[] = $productId;

            //add random accessories
            for($l=0; $l<rand(0, 3); $l++) {
                $accessory = new Thelia\Model\Accessory();
                $accessory->setAccessory($productIdList[array_rand($productIdList, 1)]);
                $accessory->setProductId($productId);
                $accessory->setPosition($l);

                $accessory->save();
            }
        }
    }

    //folders and contents
    for($i=0; $i<4; $i++) {
        $folder = new Thelia\Model\Folder();
        $folder->setParent(0);
        $folder->setVisible(rand(1, 10)>7 ? 0 : 1);
        $folder->setPosition($i);
        $folder->setTitle($faker->text(20));
        $folder->setDescription($faker->text(255));

        $folder->save();

        for($j=0; $j<rand(0, 4); $j++) {
            $subfolder = new Thelia\Model\Folder();
            $subfolder->setParent($folder->getId());
            $subfolder->setVisible(rand(1, 10)>7 ? 0 : 1);
            $subfolder->setPosition($j);
            $subfolder->setTitle($faker->text(20));
            $subfolder->setDescription($faker->text(255));

            $subfolder->save();

            for($k=0; $k<rand(1, 5); $k++) {
                $content = new Thelia\Model\Content();
                $content->addFolder($subfolder);
                $content->setVisible(rand(1, 10)>7 ? 0 : 1);
                $content->setPosition($k);
                $content->setTitle($faker->text(20));
                $content->setDescription($faker->text(255));

                $content->save();
            }
        }
    }

    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}



