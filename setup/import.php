<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Thelia\Model\ProductAssociatedContent;

if (\PHP_SAPI !== 'cli') {
    throw new Exception('this script can only be launched with cli sapi');
}

$bootstrapToggle = false;
$bootstraped = false;

// Autoload bootstrap

foreach ($argv as $arg) {
    if ('-b' === $arg) {
        $bootstrapToggle = true;

        continue;
    }

    if ($bootstrapToggle) {
        require __DIR__.\DIRECTORY_SEPARATOR.$arg;

        $bootstraped = true;
    }
}

if (!$bootstraped) {
    if (isset($bootstrapFile)) {
        require $bootstrapFile;
    } elseif (is_file($file = __DIR__.'/../vendor/autoload.php')) {
        require $file;
    } elseif (is_file($file = __DIR__.'/../../bootstrap.php')) {
        // Here we are on a thelia/thelia-project
        require $file;
    } else {
        echo 'No autoload file found. Please use the -b argument to include yours';
        exit(1);
    }
}

if (is_file(dirname(__DIR__).'/.env')) {
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
} elseif (is_file($file = __DIR__.'/../../bootstrap.php')) {
    // Here we are on a thelia/thelia-project
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/../.env');
}

$thelia = new App\Kernel($_ENV['APP_ENV'], true);

$thelia->boot();

// Load the translator
$thelia->getContainer()->get('thelia.translator');

// Intialize URL management
$url = new Thelia\Tools\URL();
$con = Propel\Runtime\Propel::getConnection(
    Thelia\Model\Map\ProductTableMap::DATABASE_NAME,
);
$con->beginTransaction();

try {
    $stmt = $con->prepare('SET foreign_key_checks = 0');
    $stmt->execute();
    clearTables($con);
    $stmt = $con->prepare('SET foreign_key_checks = 1');
    $stmt->execute();

    $material = createMaterials($con);

    $color = createColors($con);
    $brands = createBrands($con);

    $folders = createFolders($con);
    $contents = createContents($folders, $con);

    echo "creating templates\n";
    $template = new Thelia\Model\Template();
    $template
        ->setLocale('fr_FR')
        ->setName('template de démo')
        ->setLocale('en_US')
        ->setName('demo template')
        ->save($con);

    $categories = createCategories($template->getId(), $con);

    $at = new Thelia\Model\AttributeTemplate();

    $at
        ->setTemplate($template)
        ->setAttribute($color)
        ->save($con);

    $ft = new Thelia\Model\FeatureTemplate();

    $ft
        ->setTemplate($template)
        ->setFeature($material)
        ->save($con);
    echo "end creating templates\n";

    createProduct($categories, $brands, $contents, $template, $color, $material, $con);

    $sales = createSales($con);

    createCustomer($con);

    // set some config key
    createConfig($folders, $contents, $con);

    $con->commit();
} catch (Exception $e) {
    echo 'error : '.$e->getMessage()."\n";
    $con->rollBack();
}

function createProduct($categories, $brands, $contents, $template, $attribute, $feature, $con): void
{
    echo "start creating products\n";
    $fileSystem = new Symfony\Component\Filesystem\Filesystem();

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/products.csv', 'r')) !== false) {
        $row = 0;

        while (($data = fgetcsv($handle, 100000, ';')) !== false) {
            ++$row;

            if (1 === $row) {
                continue;
            }
            $product = new Thelia\Model\Product();

            $product
                ->setRef($data[0])
                ->setVisible(1)
                ->setTaxRuleId(1)
                ->setTemplate($template);

            $productCategories = explode(';', $data[15]);

            foreach ($productCategories as $productCategory) {
                $productCategory = trim($productCategory);

                if (array_key_exists($productCategory, $categories)) {
                    $product->addCategory($categories[$productCategory]);
                }
            }

            $brand = $data[11];

            if (array_key_exists($brand, $brands)) {
                $product->setBrand($brands[$brand]);
            }

            $product
                ->setLocale('en_US')
                ->setTitle($data[1])
                ->setChapo($data[2])
                ->setDescription($data[4])
                ->setPostscriptum($data[6])
                ->setLocale('fr_FR')
                ->setTitle($data[1])
                ->setChapo($data[3])
                ->setDescription($data[5])
                ->setPostscriptum($data[7])
                ->save($con);

            $productCategories = $product->getProductCategories()->getFirst();
            $productCategories->setDefaultCategory(true)
                ->save($con);

            // Set the position
            $product->setPosition($product->getNextPosition())->save($con);

            $images = explode(';', $data[10]);

            foreach ($images as $image) {
                $image = trim($image);

                if (empty($image)) {
                    continue;
                }
                $productImage = new Thelia\Model\ProductImage();
                $productImage
                    ->setProduct($product)
                    ->setFile($image)
                    ->save($con);
                $fileSystem->copy(THELIA_SETUP_DIRECTORY.'import/images/'.$image, THELIA_LOCAL_DIR.'media/images/product/'.$image, true);
            }

            $pses = explode(';', $data[12]);

            foreach ($pses as $pse) {
                if (empty($pse)) {
                    continue;
                }
                $stock = new Thelia\Model\ProductSaleElements();
                $stock->setProduct($product);
                $stock->setRef($product->getId().'_'.uniqid('', true));
                $stock->setQuantity(random_int(1, 50));

                if (!empty($data[9])) {
                    $stock->setPromo(1);
                } else {
                    $stock->setPromo(0);
                }

                $stock->setNewness(random_int(0, 1));
                $stock->setWeight((float) random_int(100, 3000) / 100);
                $stock->save($con);

                $productPrice = new Thelia\Model\ProductPrice();
                $productPrice->setProductSaleElements($stock);
                $productPrice->setCurrencyId(1);
                $productPrice->setPrice((float) $data[8]);
                $productPrice->setPromoPrice((float) $data[9]);
                $productPrice->save($con);

                $attributeAv = Thelia\Model\AttributeAvI18nQuery::create()
                    ->filterByLocale('en_US')
                    ->filterByTitle($pse)
                    ->findOne($con);

                $attributeCombination = new Thelia\Model\AttributeCombination();
                $attributeCombination
                    ->setAttributeId($attribute->getId())
                    ->setAttributeAvId($attributeAv->getId())
                    ->setProductSaleElements($stock)
                    ->save($con);
            }

            $productSaleElements = $product->getProductSaleElementss()->getFirst();
            $productSaleElements->setIsDefault(1)->save($con);

            // associated content
            $associatedContents = explode(';', $data[14]);

            foreach ($associatedContents as $associatedContent) {
                $content = new ProductAssociatedContent();

                if (!array_key_exists($associatedContent, $contents)) {
                    continue;
                }

                $content
                    ->setProduct($product)
                    ->setContent($contents[$associatedContent])
                    ->save($con);
            }

            // feature
            $features = explode(';', $data[13]);

            foreach ($features as $aFeature) {
                $featurAv = Thelia\Model\FeatureAvI18nQuery::create()
                    ->filterByLocale('en_US')
                    ->filterByTitle($aFeature)
                    ->findOne($con);

                $featureProduct = new Thelia\Model\FeatureProduct();
                $featureProduct->setProduct($product)
                    ->setFeatureId($feature->getId())
                    ->setFeatureAvId($featurAv->getId())
                    ->save($con);
            }
        }
    }
    echo "end creating products\n";
}

function createConfig($folders, $contents, $con): void
{
    // Store
    Thelia\Model\ConfigQuery::write('store_name', 'Thelia');
    Thelia\Model\ConfigQuery::write('store_description', 'E-commerce solution based on Symfony');
    Thelia\Model\ConfigQuery::write('store_email', 'Thelia');
    Thelia\Model\ConfigQuery::write('store_address1', '5 rue Rochon');
    Thelia\Model\ConfigQuery::write('store_city', 'Clermont-Ferrrand');
    Thelia\Model\ConfigQuery::write('store_phone', '+(33)444053102');
    Thelia\Model\ConfigQuery::write('store_email', 'contact@thelia.net');
    // Contents
    Thelia\Model\ConfigQuery::write('information_folder_id', $folders['Information']->getId());
    Thelia\Model\ConfigQuery::write('terms_conditions_content_id', $contents['Terms and Conditions']->getId());
}

function createCustomer($con): void
{
    echo "Creating customer\n";

    $customer = new Thelia\Model\Customer();
    $customer->createOrUpdate(
        1,
        'thelia',
        'thelia',
        '5 rue rochon',
        '',
        '',
        '0102030405',
        '0601020304',
        '63000',
        'Clermont-Ferrand',
        64,
        'test@thelia.net',
        'thelia',
    );

    $address = new Thelia\Model\Address();
    $address->setLabel('Address n°2')
        ->setTitleId(random_int(1, 3))
        ->setFirstname('thelia')
        ->setLastname('thelia')
        ->setAddress1('4 rue du Pensionnat Notre Dame de France')
        ->setAddress2('')
        ->setAddress3('')
        ->setCellphone('')
        ->setPhone('')
        ->setZipcode('43000')
        ->setCity('Le Puy-en-velay')
        ->setCountryId(64)
        ->setCustomer($customer)
        ->save($con);

    $address = new Thelia\Model\Address();
    $address->setLabel('Address n°3')
        ->setTitleId(random_int(1, 3))
        ->setFirstname('thelia')
        ->setLastname('thelia')
        ->setAddress1("43 rue d'Alsace-Lorrainee")
        ->setAddress2('')
        ->setAddress3('')
        ->setCellphone('')
        ->setPhone('')
        ->setZipcode('31000')
        ->setCity('Toulouse')
        ->setCountryId(64)
        ->setCustomer($customer)
        ->save($con);

    echo "End creating customer\n";
}

function createMaterials($con)
{
    echo "start creating materials feature\n";

    $feature = null;
    $features = [];

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/materials.csv', 'r')) !== false) {
        $row = 0;
        $feature = new Thelia\Model\Feature();
        $feature
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Matière')
            ->setLocale('en_US')
            ->setTitle('Material');

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;
            $featureAv = new Thelia\Model\FeatureAv();
            $featureAv
                ->setPosition($row)
                ->setLocale('fr_FR')
                ->setTitle($data[0])
                ->setLocale('en_US')
                ->setTitle($data[1]);

            $feature->addFeatureAv($featureAv);
        }

        $feature->save($con);

        fclose($handle);
    }
    echo "materials feature created successfully\n";

    return $feature;
}

function createBrands($con)
{
    echo "start creating brands\n";

    $fileSystem = new Symfony\Component\Filesystem\Filesystem();

    $brands = [];

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/brand.csv', 'r')) !== false) {
        $row = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;

            if (1 === $row) {
                continue;
            }
            $brand = new Thelia\Model\Brand();

            $brand
                ->setVisible(1)
                ->setPosition($row - 1)
                ->setLocale('fr_FR')
                ->setTitle(trim($data[0]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')
                ->setTitle(trim($data[0]))
                ->setChapo('Eos perspiciatis.')
                ->setDescription('Eos velit enim autem eum nihil sunt ut. Porro ipsa deleniti dolore molestiae aut omnis autem.')
                ->save($con);

            $brands[trim($data[0])] = $brand;

            $images = explode(';', $data[1]);
            $logoId = null;

            foreach ($images as $image) {
                $image = trim($image);

                if (empty($image)) {
                    continue;
                }
                $brandImage = new Thelia\Model\BrandImage();
                $brandImage
                    ->setBrandId($brand->getId())
                    ->setFile($image)
                    ->save($con);

                if (null === $logoId) {
                    $logoId = $brandImage->getId();
                }
                $fileSystem->copy(THELIA_SETUP_DIRECTORY.'import/images/'.$image, THELIA_LOCAL_DIR.'media/images/brand/'.$image, true);
            }

            if (null !== $logoId) {
                $brand->setLogoImageId($logoId);
                $brand->save($con);
            }
        }
        fclose($handle);
    }
    echo "brands created successfully\n";

    return $brands;
}

function createCategories($templateId, $con)
{
    echo "start creating categories\n";
    $fileSystem = new Symfony\Component\Filesystem\Filesystem();

    $categories = [];

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/categories.csv', 'r')) !== false) {
        $row = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;

            if (1 === $row) {
                continue;
            }
            $category = new Thelia\Model\Category();
            $category
                ->setDefaultTemplateId($templateId)
                ->setVisible(1)
                ->setPosition($row - 1)
                ->setParent(0)
                ->setLocale('fr_FR')
                ->setTitle(trim($data[1]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')
                ->setTitle(trim($data[1]))
                ->setChapo('Eos perspiciatis.')
                ->setDescription('Eos velit enim autem eum nihil sunt ut. Porro ipsa deleniti dolore molestiae aut omnis autem.')
                ->save($con);
            $categories[trim($data[1])] = $category;

            $images = explode(';', $data[6]);

            foreach ($images as $image) {
                $image = trim($image);

                if (empty($image)) {
                    continue;
                }
                $categoryImage = new Thelia\Model\CategoryImage();
                $categoryImage
                    ->setCategory($category)
                    ->setFile($image)
                    ->save($con);
                $fileSystem->copy(THELIA_SETUP_DIRECTORY.'import/images/'.$image, THELIA_LOCAL_DIR.'media/images/category/'.$image, true);
            }
        }
        fclose($handle);
    }
    echo "categories created successfully\n";

    return $categories;
}

function createSales($con)
{
    echo "start creating sales\n";
    $sales = [];

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/sales.csv', 'r')) !== false) {
        $row = 0;
        $start = new DateTime();
        $end = new DateTime();
        $currencies = Thelia\Model\CurrencyQuery::create()->find();

        $products = Thelia\Model\ProductQuery::create()->find();

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;

            if (1 === $row) {
                continue;
            }
            $sale = new Thelia\Model\Sale();
            $sale
                ->setActive(0)
                ->setStartDate($start->setTimestamp(strtotime('today - 1 month')))
                ->setEndDate($end->setTimestamp(strtotime('today + 1 month')))
                ->setPriceOffsetType($data[2])
                ->setDisplayInitialPrice(true)
                ->setLocale('fr_FR')
                ->setTitle(trim($data[0]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')
                ->setTitle(trim($data[1]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->save($con);

            foreach ($currencies as $currency) {
                $saleOffset = new Thelia\Model\SaleOffsetCurrency();

                $saleOffset
                    ->setCurrencyId($currency->getId())
                    ->setSaleId($sale->getId())
                    ->setPriceOffsetValue($data[3])
                    ->save();
            }

            $count = 5;

            foreach ($products as $product) {
                if (--$count < 0) {
                    break;
                }
                $saleProduct = new Thelia\Model\SaleProduct();

                $saleProduct
                    ->setSaleId($sale->getId())
                    ->setProductId($product->getId())
                    ->setAttributeAvId(null)
                    ->save();
            }

            $sales[trim($data[1])] = $sale;
        }
        fclose($handle);
    }
    echo "sales created successfully\n";

    return $sales;
}

function createFolders($con)
{
    echo "start creating folders\n";

    $fileSystem = new Symfony\Component\Filesystem\Filesystem();

    $folders = [];

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/folders.csv', 'r')) !== false) {
        $row = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;

            if (1 === $row) {
                continue;
            }
            $folder = new Thelia\Model\Folder();

            $folder
                ->setVisible(1)
                ->setPosition($row - 1)
                ->setLocale('fr_FR')
                ->setTitle(trim($data[0]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')
                ->setTitle(trim($data[1]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->save($con);

            $folders[trim($data[1])] = $folder;

            $images = explode(';', $data[6]);

            foreach ($images as $image) {
                $image = trim($image);

                if (empty($image)) {
                    continue;
                }
                $folderImage = new Thelia\Model\FolderImage();
                $folderImage
                    ->setFolderId($folder->getId())
                    ->setFile($image)
                    ->save($con);
                $fileSystem->copy(THELIA_SETUP_DIRECTORY.'import/images/'.$image, THELIA_LOCAL_DIR.'media/images/folder/'.$image, true);
            }
        }
        fclose($handle);
    }
    echo "Folders created successfully\n";

    return $folders;
}

function createContents($folders, $con)
{
    echo "start creating contents\n";

    $fileSystem = new Symfony\Component\Filesystem\Filesystem();

    $contents = [];

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/contents.csv', 'r')) !== false) {
        $row = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;

            if (1 === $row) {
                continue;
            }
            $content = new Thelia\Model\Content();

            $content
                ->setVisible(1)
                ->setLocale('fr_FR')
                ->setTitle(trim($data[0]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')
                ->setTitle(trim($data[1]))
                ->setChapo('Aut voluptas.')
                ->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.');

            // folder
            $contentFolders = explode(';', $data[7]);
            $defaultFolder = null;

            foreach ($contentFolders as $contentFolder) {
                $contentFolder = trim($contentFolder);

                if (array_key_exists($contentFolder, $folders)) {
                    $content->addFolder($folders[$contentFolder]);

                    if (null === $defaultFolder) {
                        $defaultFolder = $folders[$contentFolder]->getId();
                    }
                }
            }

            $content
                ->getContentFolders()
                ->getFirst()
                ->setDefaultFolder(true)
                ->save($con);

            $content->save($con);

            $images = explode(';', $data[6]);

            foreach ($images as $image) {
                $image = trim($image);

                if (empty($image)) {
                    continue;
                }
                $contentImage = new Thelia\Model\ContentImage();
                $contentImage
                    ->setContentId($content->getId())
                    ->setFile($image)
                    ->save($con);
                $fileSystem->copy(THELIA_SETUP_DIRECTORY.'import/images/'.$image, THELIA_LOCAL_DIR.'media/images/content/'.$image, true);
            }

            $contents[trim($data[1])] = $content;
        }
        fclose($handle);
    }
    echo "Contents created successfully\n";

    return $contents;
}

function createColors($con)
{
    echo "start creating colors attributes\n";

    if (($handle = fopen(THELIA_SETUP_DIRECTORY.'import/colors.csv', 'r')) !== false) {
        $row = 0;
        $attribute = new Thelia\Model\Attribute();
        $attribute
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Couleur')
            ->setLocale('en_US')
            ->setTitle('Colors');

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$row;
            $attributeAv = new Thelia\Model\AttributeAv();
            $attributeAv
                ->setPosition($row)
                ->setLocale('fr_FR')
                ->setTitle($data[0])
                ->setLocale('en_US')
                ->setTitle($data[1]);

            $attribute->addAttributeAv($attributeAv);
        }
        $attribute->save($con);
        fclose($handle);
    }
    echo "colors attributes created with success\n";

    return $attribute;
}

function clearTables($con): void
{
    echo "Clearing tables\n";

    $productAssociatedContent = Thelia\Model\ProductAssociatedContentQuery::create()
        ->find($con);
    $productAssociatedContent->delete($con);

    $categoryAssociatedContent = Thelia\Model\CategoryAssociatedContentQuery::create()
        ->find($con);
    $categoryAssociatedContent->delete($con);

    $featureProduct = Thelia\Model\FeatureProductQuery::create()
        ->find($con);
    $featureProduct->delete($con);

    $attributeCombination = Thelia\Model\AttributeCombinationQuery::create()
        ->find($con);
    $attributeCombination->delete($con);

    $feature = Thelia\Model\FeatureQuery::create()
        ->find($con);
    $feature->delete($con);

    $feature = Thelia\Model\FeatureI18nQuery::create()
        ->find($con);
    $feature->delete($con);

    $featureAv = Thelia\Model\FeatureAvQuery::create()
        ->find($con);
    $featureAv->delete($con);

    $featureAv = Thelia\Model\FeatureAvI18nQuery::create()
        ->find($con);
    $featureAv->delete($con);

    $attribute = Thelia\Model\AttributeQuery::create()
        ->find($con);
    $attribute->delete($con);

    $attribute = Thelia\Model\AttributeI18nQuery::create()
        ->find($con);
    $attribute->delete($con);

    $attributeAv = Thelia\Model\AttributeAvQuery::create()
        ->find($con);
    $attributeAv->delete($con);

    $attributeAv = Thelia\Model\AttributeAvI18nQuery::create()
        ->find($con);
    $attributeAv->delete($con);

    $brand = Thelia\Model\BrandQuery::create()
        ->find($con);
    $brand->delete($con);

    $brand = Thelia\Model\BrandI18nQuery::create()
        ->find($con);
    $brand->delete($con);

    $category = Thelia\Model\CategoryQuery::create()
        ->find($con);
    $category->delete($con);

    $category = Thelia\Model\CategoryI18nQuery::create()
        ->find($con);
    $category->delete($con);

    $product = Thelia\Model\ProductQuery::create()
        ->find($con);
    $product->delete($con);

    $product = Thelia\Model\ProductI18nQuery::create()
        ->find($con);
    $product->delete($con);

    $folder = Thelia\Model\FolderQuery::create()
        ->find($con);
    $folder->delete($con);

    $folder = Thelia\Model\FolderI18nQuery::create()
        ->find($con);
    $folder->delete($con);

    $content = Thelia\Model\ContentQuery::create()
        ->find($con);
    $content->delete($con);

    $content = Thelia\Model\ContentI18nQuery::create()
        ->find($con);
    $content->delete($con);

    $accessory = Thelia\Model\AccessoryQuery::create()
        ->find($con);
    $accessory->delete($con);

    $stock = Thelia\Model\ProductSaleElementsQuery::create()
        ->find($con);
    $stock->delete($con);

    $productPrice = Thelia\Model\ProductPriceQuery::create()
        ->find($con);
    $productPrice->delete($con);

    Thelia\Model\ProductImageQuery::create()->find($con)->delete($con);

    $customer = Thelia\Model\CustomerQuery::create()
        ->find($con);
    $customer->delete($con);

    $sale = Thelia\Model\SaleQuery::create()->find($con);
    $sale->delete($con);

    $saleProduct = Thelia\Model\SaleProductQuery::create()->find($con);
    $saleProduct->delete($con);

    echo "Tables cleared with success\n";
}
