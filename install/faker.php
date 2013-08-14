<?php
require __DIR__ . '/../core/bootstrap.php';

$thelia = new Thelia\Core\Thelia("dev", true);

$faker = Faker\Factory::create();

$con = \Propel\Runtime\Propel::getConnection(Thelia\Model\Map\ProductTableMap::DATABASE_NAME);
$con->beginTransaction();

$currency = \Thelia\Model\CurrencyQuery::create()->filterByCode('EUR')->findOne();

try {

    $feature = Thelia\Model\FeatureQuery::create()
        ->find();
    $feature->delete();

    $featureAv = Thelia\Model\FeatureAvQuery::create()
        ->find();
    $featureAv->delete();

    $attribute = Thelia\Model\AttributeQuery::create()
        ->find();
    $attribute->delete();

    $attributeAv = Thelia\Model\AttributeAvQuery::create()
        ->find();
    $attributeAv->delete();

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

    $stock = \Thelia\Model\ProductSaleElementsQuery::create()
        ->find();
    $stock->delete();

    $productPrice = \Thelia\Model\ProductPriceQuery::create()
        ->find();
    $productPrice->delete();

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

    //attributes and attributes_av
    $attributeList = array();
    for($i=0; $i<4; $i++) {
        $attribute = new Thelia\Model\Attribute();
        $attribute->setPosition($i);
        $attribute->setTitle($faker->text(20));
        $attribute->setDescription($faker->text(50));

        $attribute->save();
        $attributeId = $attribute->getId();
        $attributeList[$attributeId] = array();

        for($j=0; $j<rand(1, 5); $j++) {
            $attributeAv = new Thelia\Model\AttributeAv();
            $attributeAv->setAttribute($attribute);
            $attributeAv->setPosition($j);
            $attributeAv->setTitle($faker->text(20));
            $attributeAv->setDescription($faker->text(255));

            $attributeAv->save();
            $attributeList[$attributeId][] = $attributeAv->getId();
        }
    }

    //categories and products
    $productIdList = array();
    $categoryIdList = array();
    for($i=0; $i<4; $i++) {
        $category = createCategory($faker, 0, $i, $categoryIdList);

        for($j=1; $j<rand(0, 5); $j++) {
            $subcategory = createCategory($faker, $category->getId(), $j, $categoryIdList);

            for($k=0; $k<rand(0, 5); $k++) {
                createProduct($faker, $subcategory, $k, $currency, $productIdList);
            }
        }

        for($k=0; $k<rand(1, 5); $k++) {
            createProduct($faker, $category, $k, $currency, $productIdList);
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

        for($j=1; $j<rand(0, 5); $j++) {
            $subfolder = new Thelia\Model\Folder();
            $subfolder->setParent($folder->getId());
            $subfolder->setVisible(rand(1, 10)>7 ? 0 : 1);
            $subfolder->setPosition($j);
            $subfolder->setTitle($faker->text(20));
            $subfolder->setDescription($faker->text(255));

            $subfolder->save();

            for($k=0; $k<rand(0, 5); $k++) {
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

function createProduct($faker, $category, $position, $currency, &$productIdList)
{
    $product = new Thelia\Model\Product();
    $product->setRef($category->getId() . '_' . $position . '_' . $faker->randomNumber(8));
    $product->addCategory($category);
    $product->setVisible(rand(1, 10)>7 ? 0 : 1);
    $product->setPosition($position);
    $product->setTitle($faker->text(20));
    $product->setDescription($faker->text(255));

    $product->save();
    $productId = $product->getId();
    $productIdList[] = $productId;

    $stock = new \Thelia\Model\ProductSaleElements();
    $stock->setProduct($product);
    $stock->setQuantity($faker->randomNumber(1,50));
    $stock->setPromo($faker->randomNumber(0,1));
    $stock->setNewness($faker->randomNumber(0,1));
    $stock->setWeight($faker->randomFloat(2, 100,10000));
    $stock->save();

    $productPrice = new \Thelia\Model\ProductPrice();
    $productPrice->setProductSaleElements($stock);
    $productPrice->setCurrency($currency);
    $productPrice->setPrice($faker->randomFloat(2, 20, 250));
    $productPrice->setPromoPrice($faker->randomFloat(2, 20, 250));
    $productPrice->save();

    //add random accessories - or not
    for($i=1; $i<rand(0, 4); $i++) {
        $accessory = new Thelia\Model\Accessory();
        $accessory->setAccessory($productIdList[array_rand($productIdList, 1)]);
        $accessory->setProductId($productId);
        $accessory->setPosition($i);

        $accessory->save();
    }

    return $product;
}

function createCategory($faker, $parent, $position, &$categoryIdList)
{
    $category = new Thelia\Model\Category();
    $category->setParent($parent);
    $category->setVisible(rand(1, 10)>7 ? 0 : 1);
    $category->setPosition($position);
    $category->setTitle($faker->text(20));
    $category->setDescription($faker->text(255));

    $category->save();
    $categoryIdList[] = $category->getId();

    return $category;
}

