<?php
use Thelia\Model\ProductImage;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\CategoryImage;
use Thelia\Model\FolderImage;
use Thelia\Model\ContentImage;
require __DIR__ . '/../core/bootstrap.php';

$thelia = new Thelia\Core\Thelia("dev", true);

$faker = Faker\Factory::create();

$con = \Propel\Runtime\Propel::getConnection(Thelia\Model\Map\ProductTableMap::DATABASE_NAME);
$con->beginTransaction();

$currency = \Thelia\Model\CurrencyQuery::create()->filterByCode('EUR')->findOne();

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
    $image_data = file_get_contents("http://placehold.it/320x200&text=Image+for+$typeobj+ID+".$id);
    $image_file = sprintf("%s/../local/media/images/%s/sample-image-%s.png", __DIR__, $typeobj, $id);

    if (! is_dir(dirname($image_file))) mkdir(dirname($image_file), 0777, true);

    if ($fh = fopen($image_file, "w")) {
        fwrite($fh, $image_data);
        fclose($fh);
    }
}

try {

    $stmt = $con->prepare("SET foreign_key_checks = 0");
    $stmt->execute();

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

    $stmt = $con->prepare("SET foreign_key_checks = 1");
    $stmt->execute();

    //first category
    $sweet = new Thelia\Model\Category();
    $sweet->setParent(0);
    $sweet->setVisible(1);
    $sweet->setPosition(1);
    $sweet->setDescription($faker->text(255));
    $sweet->setTitle($faker->text(20));

    $sweet->save();

    $image = new CategoryImage();
    $image->setCategoryId($sweet->getId());
    generate_image($image, 1, 'category', $sweet->getId());

    //second category
    $jeans = new Thelia\Model\Category();
    $jeans->setParent(0);
    $jeans->setVisible(1);
    $jeans->setPosition(2);
    $jeans->setDescription($faker->text(255));
    $jeans->setTitle($faker->text(20));

    $jeans->save();

    $image = new CategoryImage();
    $image->setCategoryId($jeans->getId());
    generate_image($image, 2, 'category', $jeans->getId());

    //third category
    $other = new Thelia\Model\Category();
    $other->setParent($jeans->getId());
    $other->setVisible(1);
    $other->setPosition(3);
    $other->setDescription($faker->text(255));
    $other->setTitle($faker->text(20));

    $other->save();

    $image = new CategoryImage();
    $image->setCategoryId($other->getId());
    generate_image($image, 3, 'category', $other->getId());

    for ($i=1; $i <= 5; $i++) {
        $product = new \Thelia\Model\Product();
        $product->addCategory($sweet);
        $product->setTitle($faker->text(20));
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

        $image = new ProductImage();
        $image->setProductId($product->getId());
        generate_image($image, $i, 'product', $product->getId());
    }

    for ($i=1; $i <= 5; $i++) {
        $product = new \Thelia\Model\Product();
        $product->addCategory($jeans);
        $product->setTitle($faker->text(20));
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

        $image = new ProductImage();
        $image->setProductId($product->getId());
        generate_image($image, $i, 'product', $product->getId());

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

        $image = new FolderImage();
        $image->setFolderId($folder->getId());
        generate_image($image, $i, 'folder', $folder->getId());

        for($j=0; $j<rand(0, 4); $j++) {
            $subfolder = new Thelia\Model\Folder();
            $subfolder->setParent($folder->getId());
            $subfolder->setVisible(rand(1, 10)>7 ? 0 : 1);
            $subfolder->setPosition($j);
            $subfolder->setTitle($faker->text(20));
            $subfolder->setDescription($faker->text(255));

            $subfolder->save();

            $image = new FolderImage();
            $image->setFolderId($subfolder->getId());
            generate_image($image, $j, 'folder', $subfolder->getId());

            for($k=0; $k<rand(1, 5); $k++) {
                $content = new Thelia\Model\Content();
                $content->addFolder($subfolder);
                $content->setVisible(rand(1, 10)>7 ? 0 : 1);
                $content->setPosition($k);
                $content->setTitle($faker->text(20));
                $content->setDescription($faker->text(255));

                $content->save();

                $image = new ContentImage();
                $image->setContentId($content->getId());
                generate_image($image, $k, 'content', $content->getId());

            }
        }
    }

    //features and features_av
    for($i=0; $i<4; $i++) {
        $feature = new Thelia\Model\Feature();
        $feature->setVisible(rand(1, 10)>7 ? 0 : 1);
        $feature->setPosition($i);
        $feature->setTitle($faker->text(20));
        $feature->setDescription($faker->text(50));

        $feature->save();

        for($j=0; $j<rand(1, 5); $j++) {
            $featureAv = new Thelia\Model\FeatureAv();
            $featureAv->setFeature($feature);
            $featureAv->setPosition($j);
            $featureAv->setTitle($faker->text(20));
            $featureAv->setDescription($faker->text(255));

            $featureAv->save();
        }
    }

    $con->commit();
}
catch (PropelException $pe) {
    echo "Propel error: ".$pe->getMessage()."\n".$pe->getTraceAsString();
    $con->rollBack();
}
catch (Exception $e) {
    echo "error occured : ".$e->getMessage()."\n".$e->getTraceAsString();
    $con->rollBack();
}



