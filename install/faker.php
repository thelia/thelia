<?php
use Thelia\Model\ProductImage;
use Thelia\Model\CategoryImage;
use Thelia\Model\FolderImage;
use Thelia\Model\ContentImage;
use Imagine\Image\Color;
use Imagine\Image\Point;

require __DIR__ . '/../core/bootstrap.php';

$thelia = new Thelia\Core\Thelia("dev", true);

$faker = Faker\Factory::create();

$con = \Propel\Runtime\Propel::getConnection(Thelia\Model\Map\ProductTableMap::DATABASE_NAME);
$con->beginTransaction();

$currency = \Thelia\Model\CurrencyQuery::create()->filterByCode('EUR')->findOne();

try {
    $stmt = $con->prepare("SET foreign_key_checks = 0");
    $stmt->execute();
    
    $attributeCategory = Thelia\Model\AttributeCategoryQuery::create()
        ->find();
    $attributeCategory->delete();

    $featureCategory = Thelia\Model\FeatureCategoryQuery::create()
        ->find();
    $featureCategory->delete();

    $featureProduct = Thelia\Model\FeatureProductQuery::create()
        ->find();
    $featureProduct->delete();

    $attributeCombination = Thelia\Model\AttributeCombinationQuery::create()
        ->find();
    $attributeCombination->delete();

    $feature = Thelia\Model\FeatureQuery::create()
        ->find();
    $feature->delete();

    $feature = Thelia\Model\FeatureI18nQuery::create()
        ->find();
    $feature->delete();

    $featureAv = Thelia\Model\FeatureAvQuery::create()
        ->find();
    $featureAv->delete();

    $featureAv = Thelia\Model\FeatureAvI18nQuery::create()
        ->find();
    $featureAv->delete();

    $attribute = Thelia\Model\AttributeQuery::create()
        ->find();
    $attribute->delete();

    $attribute = Thelia\Model\AttributeI18nQuery::create()
        ->find();
    $attribute->delete();

    $attributeAv = Thelia\Model\AttributeAvQuery::create()
        ->find();
    $attributeAv->delete();

    $attributeAv = Thelia\Model\AttributeAvI18nQuery::create()
        ->find();
    $attributeAv->delete();

    $category = Thelia\Model\CategoryQuery::create()
        ->find();
    $category->delete();

    $category = Thelia\Model\CategoryI18nQuery::create()
        ->find();
    $category->delete();

    $product = Thelia\Model\ProductQuery::create()
        ->find();
    $product->delete();

    $product = Thelia\Model\ProductI18nQuery::create()
        ->find();
    $product->delete();

    $customer = Thelia\Model\CustomerQuery::create()
        ->find();
    $customer->delete();

    $folder = Thelia\Model\FolderQuery::create()
        ->find();
    $folder->delete();

    $folder = Thelia\Model\FolderI18nQuery::create()
        ->find();
    $folder->delete();

    $content = Thelia\Model\ContentQuery::create()
        ->find();
    $content->delete();

    $content = Thelia\Model\ContentI18nQuery::create()
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

    $stmt = $con->prepare("SET foreign_key_checks = 1");
    $stmt->execute();

    //customer
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

    //features and features_av
    $featureList = array();
    for($i=0; $i<4; $i++) {
        $feature = new Thelia\Model\Feature();
        $feature->setVisible(rand(1, 10)>7 ? 0 : 1);
        $feature->setPosition($i);
        setI18n($faker, $feature);

        $feature->save();
        $featureId = $feature->getId();
        $featureList[$featureId] = array();

        for($j=0; $j<rand(-2, 5); $j++) { //let a chance for no av
            $featureAv = new Thelia\Model\FeatureAv();
            $featureAv->setFeature($feature);
            $featureAv->setPosition($j);
            setI18n($faker, $featureAv);

            $featureAv->save();
            $featureList[$featureId][] = $featureAv->getId();
        }
    }

    //attributes and attributes_av
    $attributeList = array();
    for($i=0; $i<4; $i++) {
        $attribute = new Thelia\Model\Attribute();
        $attribute->setPosition($i);
        setI18n($faker, $attribute);

        $attribute->save();
        $attributeId = $attribute->getId();
        $attributeList[$attributeId] = array();

        for($j=0; $j<rand(1, 5); $j++) {
            $attributeAv = new Thelia\Model\AttributeAv();
            $attributeAv->setAttribute($attribute);
            $attributeAv->setPosition($j);
            setI18n($faker, $attributeAv);

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
                createProduct($faker, $subcategory, $k, $productIdList);
            }
        }

        for($k=0; $k<rand(1, 5); $k++) {
            createProduct($faker, $category, $k, $productIdList);
        }
    }

    //attribute_category and feature_category (all categories got all features/attributes)
    foreach($categoryIdList as $categoryId) {
        foreach($attributeList as $attributeId => $attributeAvId) {
            $attributeCategory = new Thelia\Model\AttributeCategory();
            $attributeCategory->setCategoryId($categoryId)
                ->setAttributeId($attributeId)
                ->save();
        }
        foreach($featureList as $featureId => $featureAvId) {
            $featureCategory = new Thelia\Model\FeatureCategory();
            $featureCategory->setCategoryId($categoryId)
                ->setFeatureId($featureId)
                ->save();
        }
    }

    foreach($productIdList as $productId) {
        //add random accessories - or not
        $alreadyPicked = array();
        for($i=1; $i<rand(0, 4); $i++) {
            $accessory = new Thelia\Model\Accessory();
            do {
                $pick = array_rand($productIdList, 1);
            } while(in_array($pick, $alreadyPicked));

            $alreadyPicked[] = $pick;

            $accessory->setAccessory($productIdList[$pick])
                ->setProductId($productId)
                ->setPosition($i)
                ->save();
        }

        //associate PSE and stocks to products
        for($i=0; $i<rand(1,7); $i++) {
            $stock = new \Thelia\Model\ProductSaleElements();
            $stock->setProductId($productId);
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

            //associate attributes - or not - to PSE

            $alreadyPicked = array();
            for($i=0; $i<rand(-2,count($attributeList)); $i++) {
                $featureProduct = new Thelia\Model\AttributeCombination();
                do {
                    $pick = array_rand($attributeList, 1);
                } while(in_array($pick, $alreadyPicked));

                $alreadyPicked[] = $pick;

                $featureProduct->setAttributeId($pick)
                    ->setAttributeAvId($attributeList[$pick][array_rand($attributeList[$pick], 1)])
                    ->setProductSaleElements($stock)
                    ->save();
            }
        }


        foreach($attributeList as $attributeId => $attributeAvId) {

        }

        //associate features to products
        foreach($featureList as $featureId => $featureAvId) {
            $featureProduct = new Thelia\Model\FeatureProduct();
            $featureProduct->setProductId($productId)
                ->setFeatureId($featureId);

            if(count($featureAvId) > 0) { //got some av
                $featureProduct->setFeatureAvId(
                    $featureAvId[array_rand($featureAvId, 1)]
                );
            } else { //no av
                $featureProduct->setByDefault($faker->text(10));
            }

            $featureProduct->save();
        }
    }

    //folders and contents
    for($i=0; $i<4; $i++) {
        $folder = new Thelia\Model\Folder();
        $folder->setParent(0);
        $folder->setVisible(rand(1, 10)>7 ? 0 : 1);
        $folder->setPosition($i);
        setI18n($faker, $folder);

        $folder->save();

        $image = new FolderImage();
        $image->setFolderId($folder->getId());
        generate_image($image, 1, 'folder', $folder->getId());

        for($j=1; $j<rand(0, 5); $j++) {
            $subfolder = new Thelia\Model\Folder();
            $subfolder->setParent($folder->getId());
            $subfolder->setVisible(rand(1, 10)>7 ? 0 : 1);
            $subfolder->setPosition($j);
            setI18n($faker, $subfolder);

            $subfolder->save();

            $image = new FolderImage();
            $image->setFolderId($subfolder->getId());
            generate_image($image, 1, 'folder', $subfolder->getId());

            for($k=0; $k<rand(0, 5); $k++) {
                $content = new Thelia\Model\Content();
                $content->addFolder($subfolder);
                $content->setVisible(rand(1, 10)>7 ? 0 : 1);
                $content->setPosition($k);
                setI18n($faker, $content);

                $content->save();

                $image = new ContentImage();
                $image->setContentId($content->getId());
                generate_image($image, 1, 'content', $content->getId());
            }
        }
    }

    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}

function createProduct($faker, $category, $position, &$productIdList)
{
    $product = new Thelia\Model\Product();
    $product->setRef($category->getId() . '_' . $position . '_' . $faker->randomNumber(8));
    $product->addCategory($category);
    $product->setVisible(rand(1, 10)>7 ? 0 : 1);
    $product->setPosition($position);
    setI18n($faker, $product);

    $product->save();
    $productId = $product->getId();
    $productIdList[] = $productId;

    $image = new ProductImage();
    $image->setProductId($productId);
    generate_image($image, 1, 'product', $productId);
    
    return $product;
}

function createCategory($faker, $parent, $position, &$categoryIdList)
{
    $category = new Thelia\Model\Category();
    $category->setParent($parent);
    $category->setVisible(rand(1, 10)>7 ? 0 : 1);
    $category->setPosition($position);
    setI18n($faker, $category);

    $category->save();
    $cateogoryId = $category->getId();
    $categoryIdList[] = $cateogoryId;

    $image = new CategoryImage();
    $image->setCategoryId($cateogoryId);
    generate_image($image, 1, 'category', $cateogoryId);
    
    return $category;
}

function generate_image($image, $position, $typeobj, $id) {

    global $faker;

    $image
        ->setTitle($faker->text(20))
        ->setDescription($faker->text(250))
        ->setChapo($faker->text(40))
        ->setPostscriptum($faker->text(40))
        ->setPosition($position)
        ->setFile(sprintf("sample-image-%s.png", $id))
        ->save()
    ;

    // Generate images
    $imagine = new Imagine\Gd\Imagine();
    $image   = $imagine->create(new Imagine\Image\Box(320,240), new Color('#E9730F'));

    $white = new Color('#FFF');

    $font = $imagine->font(__DIR__.'/faker-assets/FreeSans.ttf', 14, $white);

    $tbox = $font->box("THELIA");
    $image->draw()->text("THELIA", $font, new Point((320 - $tbox->getWidth()) / 2, 30));

    $str = sprintf("%s sample image", ucfirst($typeobj));
    $tbox = $font->box($str);
    $image->draw()->text($str, $font, new Point((320 - $tbox->getWidth()) / 2, 80));

    $font = $imagine->font(__DIR__.'/faker-assets/FreeSans.ttf', 18, $white);

    $str = sprintf("%s ID %d", strtoupper($typeobj), $id);
    $tbox = $font->box($str);
    $image->draw()->text($str, $font, new Point((320 - $tbox->getWidth()) / 2, 180));

    $image->draw()
        ->line(new Point(0, 0), new Point(319, 0), $white)
        ->line(new Point(319, 0), new Point(319, 239), $white)
        ->line(new Point(319, 239), new Point(0,239), $white)
        ->line(new Point(0, 239), new Point(0, 0), $white)
    ;

    $image_file = sprintf("%s/../local/media/images/%s/sample-image-%s.png", __DIR__, $typeobj, $id);

    if (! is_dir(dirname($image_file))) mkdir(dirname($image_file), 0777, true);

    $image->save($image_file);
}

function setI18n($faker, &$object)
{
    $localeList = array('fr_FR', 'en_EN');

    $title = $faker->text(20);
    $description = $faker->text(50);

    foreach($localeList as $locale) {
        $object->setLocale($locale);

        $object->setTitle($locale . ' : ' . $title);
        $object->setDescription($locale . ' : ' . $description);
    }
}

