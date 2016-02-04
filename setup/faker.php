<?php
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\Type\RemoveXAmount;
use Thelia\Coupon\Type\RemoveXPercent;
use Thelia\Model\CountryQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderAddress;
use Thelia\Model;

if (php_sapi_name() != 'cli') {
    throw new \Exception('this script can only be launched with cli sapi');
}

// Set this to true to get "real text" instead of random letters in titles, chapo, descriptions, etc.
// WARNING : relaTextMode is much more slower than false text mode, and may cause problems with Travis
// such as  "No output has been received in the last 10 minutes, this potentially indicates a stalled
// build or something wrong with the build itself."
$bootstraped = false;
$realTextMode = true;
$localeList = array('fr_FR', 'en_US', 'es_ES', 'it_IT', 'de_DE');
$numberCategories = 20;
$numberProducts = 20;
$countryStateList = [];

$options = getopt("b:c:p:r:l:h");

if (false === $options || isset($options['h'])) {
    usage();
    exit(0);
}

// Autoload bootstrap
if (isset($options['b'])) {
    require __DIR__ . DIRECTORY_SEPARATOR . $options['b'];
    $bootstraped = true;
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

// real text mode
if (isset($options['r'])) {
    $realTextMode = filter_var($options['r'], FILTER_VALIDATE_BOOLEAN);
}

// locales
if (isset($options['l'])) {
    $localeList = explode(',', str_replace(' ', '', $options['l']));
}

if (isset($options['c'])) {
    if (0 !== intval($options['c'])) {
        $numberCategories = intval($options['c']);
    }
}

if (isset($options['p'])) {
    if (0 !== intval($options['p'])) {
        $numberProducts = intval($options['p']);
    }
}

$thelia = new Thelia\Core\Thelia("dev", false);
$thelia->boot();
$thelia->getContainer()->get('thelia.translator');
// The default faker is en_US
$faker = Faker\Factory::create('en_US');

// Create localized version for content generation
$localizedFaker = [];

foreach ($localeList as $locale) {
    $localizedFaker[$locale] = Faker\Factory::create($locale);
}

$con = \Propel\Runtime\Propel::getConnection(
    Thelia\Model\Map\ProductTableMap::DATABASE_NAME
);
$con->beginTransaction();

// Intialize URL management
$url = new Thelia\Tools\URL();

$currency = \Thelia\Model\CurrencyQuery::create()->filterByCode('EUR')->findOne();

//\Thelia\Log\Tlog::getInstance()->setLevel(\Thelia\Log\Tlog::ERROR);

try {
    $stmt = $con->prepare("SET foreign_key_checks = 0");
    $stmt->execute();

    echo "Clearing tables\n";

    Model\ProductAssociatedContentQuery::create()->deleteAll();
    Model\CategoryAssociatedContentQuery::create()->deleteAll();
    Model\FeatureProductQuery::create()->deleteAll();
    Model\AttributeCombinationQuery::create()->deleteAll();
    Model\FeatureQuery::create()->deleteAll();
    Model\FeatureI18nQuery::create()->deleteAll();
    Model\FeatureAvQuery::create()->deleteAll();
    Model\FeatureAvI18nQuery::create()->deleteAll();
    Model\AttributeQuery::create()->deleteAll();
    Model\AttributeI18nQuery::create()->deleteAll();
    Model\AttributeAvQuery::create()->deleteAll();
    Model\AttributeAvI18nQuery::create()->deleteAll();
    Model\CategoryQuery::create()->deleteAll();
    Model\CategoryI18nQuery::create()->deleteAll();
    Model\ProductQuery::create()->deleteAll();
    Model\ProductI18nQuery::create()->deleteAll();
    Model\CustomerQuery::create()->deleteAll();
    Model\AdminQuery::create()->deleteAll();
    Model\FolderQuery::create()->deleteAll();
    Model\FolderI18nQuery::create()->deleteAll();
    Model\ContentQuery::create()->deleteAll();
    Model\ContentI18nQuery::create()->deleteAll();
    Model\AccessoryQuery::create()->deleteAll();
    Model\ProductSaleElementsQuery::create()->deleteAll();
    Model\ProductPriceQuery::create()->deleteAll();
    Model\BrandQuery::create()->deleteAll();
    Model\BrandI18nQuery::create()->deleteAll();
    Model\ProductImageQuery::create()->deleteAll();
    Model\CategoryImageQuery::create()->deleteAll();
    Model\FolderImageQuery::create()->deleteAll();
    Model\ContentImageQuery::create()->deleteAll();
    Model\BrandImageQuery::create()->deleteAll();
    Model\ProductDocumentQuery::create()->deleteAll();
    Model\CategoryDocumentQuery::create()->deleteAll();
    Model\FolderDocumentQuery::create()->deleteAll();
    Model\ContentDocumentQuery::create()->deleteAll();
    Model\BrandDocumentQuery::create()->deleteAll();
    Model\CouponQuery::create()->deleteAll();
    Model\OrderQuery::create()->deleteAll();
    Model\SaleQuery::create()->deleteAll();
    Model\SaleProductQuery::create()->deleteAll();
    Model\MetaDataQuery::create()->deleteAll();

    $stmt = $con->prepare("SET foreign_key_checks = 1");

    $stmt->execute();

    // default country (France)
    $defaultCountry = [64, null];

    // Store info

    echo "Creating Store information \n";

    Model\ConfigQuery::write('store_name', 'Thelia V2');
    Model\ConfigQuery::write('store_email', 'test@thelia.net');
    Model\ConfigQuery::write('store_notification_emails', 'test@thelia.net');
    Model\ConfigQuery::write('store_address1', "5 rue Rochon");
    Model\ConfigQuery::write('store_zipcode', "63000");
    Model\ConfigQuery::write('store_city', "Clermont-Ferrand");
    Model\ConfigQuery::write('store_country', $defaultCountry[0]);

    $api = new Thelia\Model\Api();

    $api
        ->setProfileId(null)
        ->setApiKey('79E95BD784CADA0C9A578282E')
        ->setLabel("test")
        ->save();

    // API
    echo "Creating API key\n";

    $api = new Thelia\Model\Api();

    $api
        ->setProfileId(null)
        ->setApiKey('79E95BD784CADA0C9A578282E')
        ->setLabel("test")
        ->save();

    // Customer
    echo "Creating customers\n";
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
        $defaultCountry[0],
        "test@thelia.net",
        "azerty",
        null,
        0,
        null,
        0,
        null,
        null,
        false,
        $defaultCountry[1]
    );
    for ($j = 0; $j <= 3; $j++) {
        $address = new Thelia\Model\Address();
        $country = getRandomCountry();
        $address->setLabel(getRealText(20))
            ->setTitleId(rand(1, 3))
            ->setFirstname($faker->firstname)
            ->setLastname($faker->lastname)
            ->setAddress1($faker->streetAddress)
            ->setAddress2($faker->streetAddress)
            ->setAddress3($faker->streetAddress)
            ->setCellphone($faker->phoneNumber)
            ->setPhone($faker->phoneNumber)
            ->setZipcode($faker->postcode)
            ->setCity($faker->city)
            ->setCountryId($country[0])
            ->setStateId($country[1])
            ->setCustomer($customer)
            ->save()
        ;
    }

    $admin = new Thelia\Model\Admin();
    $admin
        ->setFirstname($faker->firstname)
        ->setLastname($faker->lastname)
        ->setLogin('thelia')
        ->setPassword('thelia')
        ->setLocale('en_US')
        ->setEmail('')
        ->save();

    for ($i=0; $i<3; $i++) {
        $admin = new Thelia\Model\Admin();
        $admin
            ->setFirstname($faker->firstname)
            ->setLastname($faker->lastname)
            ->setLogin($faker->firstname)
            ->setPassword('azerty')
            ->setLocale('en_US')
            ->setEmail($faker->email)
            ->save();
    }

    for ($i = 0; $i < 50; $i++) {
        $customer = new Thelia\Model\Customer();
        $country = getRandomCountry();
        $customer->createOrUpdate(
            rand(1, 3),
            $faker->firstname,
            $faker->lastname,
            $faker->streetAddress,
            $faker->streetAddress,
            $faker->streetAddress,
            $faker->phoneNumber,
            $faker->phoneNumber,
            $faker->postcode,
            $faker->city,
            $country[0],
            $faker->email,
            "azerty".$i,
            null,
            0,
            null,
            0,
            null,
            null,
            false,
            $country[1]
        );

        for ($j = 0; $j <= 3; $j++) {
            $address = new Thelia\Model\Address();
            $address->setLabel(getRealText(20))
                ->setTitleId(rand(1, 3))
                ->setFirstname($faker->firstname)
                ->setLastname($faker->lastname)
                ->setAddress1($faker->streetAddress)
                ->setAddress2($faker->streetAddress)
                ->setAddress3($faker->streetAddress)
                ->setCellphone($faker->phoneNumber)
                ->setPhone($faker->phoneNumber)
                ->setZipcode($faker->postcode)
                ->setCity($faker->city)
                ->setCountryId($country[0])
                ->setStateId($country[1])
                ->setCustomer($customer)
                ->save()
            ;

        }
    }

    echo "Creating features\n";

    //features and features_av
    $featureList = array();
    for ($i=0; $i<4; $i++) {
        $feature = new Thelia\Model\Feature();
        $feature->setVisible(1);
        $feature->setPosition($i);
        setI18n($feature);

        $feature->save();
        $featureId = $feature->getId();
        $featureList[$featureId] = array();

        //hardcode chance to have no av
        if ($i === 1 || $i === 3) {
            for ($j = 0; $j < rand(1, 5); $j++) {
                $featureAv = new Thelia\Model\FeatureAv();
                $featureAv->setFeature($feature);
                $featureAv->setPosition($j);
                setI18n($featureAv);

                $featureAv->save();
                $featureList[$featureId][] = $featureAv->getId();
            }
        }
    }

    echo "Creating attributes\n";

    //attributes and attributes_av
    $attributeList = array();
    for ($i=0; $i<4; $i++) {
        $attribute = new Thelia\Model\Attribute();
        $attribute->setPosition($i);
        setI18n($attribute);

        $attribute->save();
        $attributeId = $attribute->getId();
        $attributeList[$attributeId] = array();

        for ($j=0; $j<rand(1, 5); $j++) {
            $attributeAv = new Thelia\Model\AttributeAv();
            $attributeAv->setAttribute($attribute);
            $attributeAv->setPosition($j);
            setI18n($attributeAv);

            $attributeAv->save();
            $attributeList[$attributeId][] = $attributeAv->getId();
        }
    }

    echo "Creating templates\n";

    $template = new Thelia\Model\Template();
    setI18n($template, array("Name" => 20));
    $template->save();

    foreach ($attributeList as $attributeId => $attributeAvId) {
        $at = new Thelia\Model\AttributeTemplate();

        $at
            ->setTemplate($template)
            ->setAttributeId($attributeId)
            ->save();
    }

    foreach ($featureList as $featureId => $featureAvId) {
        $ft = new Thelia\Model\FeatureTemplate();

        $ft
            ->setTemplate($template)
            ->setFeatureId($featureId)
            ->save();
    }

    echo "Creating folders and contents\n";

    //folders and contents
    $contentIdList = array();
    for ($i=0; $i<4; $i++) {
        $folder = new Thelia\Model\Folder();
        $folder->setParent(0);
        $folder->setVisible(1);
        $folder->setPosition($i+1);
        setI18n($folder);

        $folder->save();

        $image = new \Thelia\Model\FolderImage();
        $image->setFolderId($folder->getId());
        generate_image($image, 'folder', $folder->getId());

        $document = new \Thelia\Model\FolderDocument();
        $document->setFolderId($folder->getId());
        generate_document($document, 'folder', $folder->getId());

        for ($j=0; $j<3; $j++) {
            $subfolder = new Thelia\Model\Folder();
            $subfolder->setParent($folder->getId());
            $subfolder->setVisible(1);
            $subfolder->setPosition($j+1);
            setI18n($subfolder);

            $subfolder->save();

            $image = new \Thelia\Model\FolderImage();
            $image->setFolderId($subfolder->getId());
            generate_image($image, 'folder', $subfolder->getId());

            $document = new \Thelia\Model\FolderDocument();
            $document->setFolderId($folder->getId());
            generate_document($document, 'folder', $subfolder->getId());

            for ($k=0; $k<4; $k++) {
                $content = new Thelia\Model\Content();
                $content->addFolder($subfolder);

                $contentFolders = $content->getContentFolders();
                $collection = new \Propel\Runtime\Collection\Collection();
                $collection->prepend($contentFolders[0]->setDefaultFolder(1));
                $content->setContentFolders($collection);

                $content->setVisible(1);
                $content->setPosition($k+1);
                setI18n($content);

                $content->save();
                $contentId = $content->getId();
                $contentIdList[] = $contentId;

                $image = new \Thelia\Model\ContentImage();
                $image->setContentId($contentId);
                generate_image($image, 'content', $contentId);

                $document = new \Thelia\Model\ContentDocument();
                $document->setContentId($contentId);
                generate_document($document, 'content', $contentId);
            }
        }
    }

    echo "Creating brands\n";

    $brandIdList = [];

    for ($k=0; $k<10; $k++) {
        $brand = new Thelia\Model\Brand();

        $brand->setVisible(1);
        $brand->setPosition($k+1);
        setI18n($brand);

        $brand->save();
        $brandId = $brand->getId();
        $brandIdList[] = $brandId;

        $image = new \Thelia\Model\BrandImage();
        $image->setBrandId($brandId);
        generate_image($image, 'brand', $brandId);

        $document = new \Thelia\Model\BrandDocument();
        $document->setBrandId($brandId);
        generate_document($document, 'brand', $brandId);
    }

    echo "Creating categories and products\n";

    //categories and products
    $productIdList = array();
    $virtualProductList = array();
    $categoryIdList = array();
    for ($i=1; $i<$numberCategories; $i++) {
        $category = createCategory($faker, 0, $i, $categoryIdList, $contentIdList);

        for ($j=1; $j<rand(0, $numberCategories); $j++) {
            $subcategory = createCategory($faker, $category->getId(), $j, $categoryIdList, $contentIdList);

            for ($k=0; $k<rand(0, $numberProducts); $k++) {
                createProduct($faker, $subcategory, $k, $template, $brandIdList, $productIdList, $virtualProductList);
            }
        }

        for ($k=1; $k<rand(1, $numberProducts); $k++) {
            createProduct($faker, $category, $k, $template, $brandIdList, $productIdList, $virtualProductList);
        }
    }

    foreach ($productIdList as $productId) {
        //add random accessories - or not
        $alreadyPicked = array();
        for ($i=1; $i<rand(0, 4); $i++) {
            $accessory = new Thelia\Model\Accessory();
            do {
                $pick = array_rand($productIdList, 1);
            } while (in_array($pick, $alreadyPicked));

            $alreadyPicked[] = $pick;

            $accessory->setAccessory($productIdList[$pick])
                ->setProductId($productId)
                ->setPosition($i)
                ->save();
        }

        //add random associated content
        $alreadyPicked = array();
        for ($i=1; $i<rand(0, 3); $i++) {
            $productAssociatedContent = new Thelia\Model\ProductAssociatedContent();
            do {
                $pick = array_rand($contentIdList, 1);
            } while (in_array($pick, $alreadyPicked));

            $alreadyPicked[] = $pick;

            $productAssociatedContent->setContentId($contentIdList[$pick])
                ->setProductId($productId)
                ->setPosition($i)
                ->save();
        }

        //associate PSE and stocks to products
        $pse_count = rand(1, 7);
        for ($pse_idx=0; $pse_idx<$pse_count; $pse_idx++) {
            $stock = new \Thelia\Model\ProductSaleElements();
            $stock->setProductId($productId);
            $stock->setRef($productId . '_' . $pse_idx . '_' . $faker->randomNumber(8));
            $stock->setQuantity($faker->numberBetween(1, 50));
            $stock->setPromo($faker->numberBetween(0, 1));
            $stock->setNewness($faker->numberBetween(0, 1));
            $stock->setWeight($faker->randomFloat(2, 1, 5));
            $stock->setIsDefault($pse_idx == 0 ? true : false);
            $stock->setEanCode(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 13));
            $stock->save();

            // associate document to virtual product
            if (array_key_exists($productId, $virtualProductList)) {
                $virtualDocument = new \Thelia\Model\MetaData();
                $virtualDocument
                    ->setMetaKey('virtual')
                    ->setElementKey(\Thelia\Model\MetaData::PSE_KEY)
                    ->setElementId($stock->getId())
                    ->setValue($virtualProductList[$productId])
                    ->save();
            }

            $price = $faker->randomFloat(2, 20, 250);
            $promoPrice = $price * $faker->randomFloat(2, 0, 1);

            $productPrice = new \Thelia\Model\ProductPrice();
            $productPrice->setProductSaleElements($stock);
            $productPrice->setCurrency($currency);
            $productPrice->setPrice($price);
            $productPrice->setPromoPrice($promoPrice);
            $productPrice->save();

            //associate attributes - or not - to PSE

            $alreadyPicked = array();
            $minAttrCount = $pse_count == 1 ? 0 : 1;

            for ($attrIdx=0; $attrIdx<rand($minAttrCount, count($attributeList)); $attrIdx++) {

                $featureProduct = new Thelia\Model\AttributeCombination();
                do {
                    $pick = array_rand($attributeList, 1);
                } while (in_array($pick, $alreadyPicked));

                $alreadyPicked[] = $pick;

                $featureProduct->setAttributeId($pick)
                    ->setAttributeAvId($attributeList[$pick][array_rand($attributeList[$pick], 1)])
                    ->setProductSaleElements($stock)
                    ->save();
            }
        }

        //associate features to products
        $freeTextCreated = false;
        foreach ($featureList as $featureId => $featureAvId) {
            $featureProduct = new Thelia\Model\FeatureProduct();
            $featureProduct->setProductId($productId)
                ->setFeatureId($featureId);

            if ($freeTextCreated === false && count($featureAvId) === 0) { //set one feature as free text
                $featureAv = new Thelia\Model\FeatureAv();
                $featureAv->setFeatureId($featureId);
                $featureAv->setPosition(1);
                setI18n($featureAv);
                $featureAv->save();

                $featureProduct->setFeatureAvId($featureAv->getId());
                $featureProduct->setFreeTextValue(true);
                $freeTextCreated = true;
            } elseif (count($featureAvId) > 0) { //got some av
                $featureProduct->setFeatureAvId(
                    $featureAvId[array_rand($featureAvId, 1)]
                );
            } else { //no av : no featureProduct
                continue;
            }

            $featureProduct->save();
        }
    }

    echo "Creating orders\n";

    $colissimo_id = ModuleQuery::create()->
    filterByCode("Colissimo")
        ->findOne()
        ->getId();

    $cheque_id = ModuleQuery::create()
        ->filterByCode("Cheque")
        ->findOne()
        ->getId();

    for ($i=0; $i < 50; ++$i) {
        $placedOrder = new \Thelia\Model\Order();
        $country = getRandomCountry();

        $deliveryOrderAddress = new OrderAddress();
        $deliveryOrderAddress
            ->setCustomerTitleId(mt_rand(1, 3))
            ->setCompany(getRealText(15))
            ->setFirstname($faker->firstname)
            ->setLastname($faker->lastname)
            ->setAddress1($faker->streetAddress)
            ->setAddress2($faker->streetAddress)
            ->setAddress3($faker->streetAddress)
            ->setPhone($faker->phoneNumber)
            ->setZipcode($faker->postcode)
            ->setCity($faker->city)
            ->setCountryId($country[0])
            ->setStateId($country[1])
            ->save($con)
        ;

        $invoiceOrderAddress = new OrderAddress();
        $invoiceOrderAddress
            ->setCustomerTitleId(mt_rand(1, 3))
            ->setCompany(getRealText(15))
            ->setFirstname($faker->firstname)
            ->setLastname($faker->lastname)
            ->setAddress1($faker->streetAddress)
            ->setAddress2($faker->streetAddress)
            ->setAddress3($faker->streetAddress)
            ->setPhone($faker->phoneNumber)
            ->setZipcode($faker->postcode)
            ->setCity($faker->city)
            ->setCountryId($country[0])
            ->setStateId($country[1])
            ->save($con)
        ;

        /**
         * Create a cart for the order
         */
        $cart = new \Thelia\Model\Cart();
        $cart->save();

        $currency = \Thelia\Model\CurrencyQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $placedOrder
            ->setDeliveryOrderAddressId($deliveryOrderAddress->getId())
            ->setInvoiceOrderAddressId($invoiceOrderAddress->getId())
            ->setDeliveryModuleId($colissimo_id)
            ->setPaymentModuleId($cheque_id)
            ->setStatusId(mt_rand(1, 5))
            ->setCurrencyRate($currency->getRate())
            ->setCurrencyId($currency->getId())
            ->setCustomer(
                \Thelia\Model\CustomerQuery::create()
                    ->addAscendingOrderByColumn('RAND()')
                    ->findOne()
            )
            ->setDiscount(mt_rand(0, 10))
            ->setLang(
                \Thelia\Model\LangQuery::create()
                    ->addAscendingOrderByColumn('RAND()')
                    ->findOne()
            )
            ->setPostage(mt_rand(1, 50))
            ->setCartId($cart->getId())
        ;

        $placedOrder->save($con);

        for ($j=0; $j < mt_rand(1, 10); ++$j) {
            $pse = \Thelia\Model\ProductSaleElementsQuery::create()
                ->addAscendingOrderByColumn('RAND()')
                ->findOne();

            $product = $pse->getProduct();

            $orderProduct = new \Thelia\Model\OrderProduct();

            $orderProduct
                ->setOrderId($placedOrder->getId())
                ->setProductRef($product->getRef())
                ->setProductSaleElementsRef($pse->getRef())
                ->setProductSaleElementsId($pse->getId())
                ->setTitle($product->getTitle())
                ->setChapo($product->getChapo())
                ->setDescription($product->getDescription())
                ->setPostscriptum($product->getPostscriptum())
                ->setQuantity(mt_rand(1, 10))
                ->setPrice($price=mt_rand(1, 100))
                ->setPromoPrice(mt_rand(1, $price))
                ->setWasNew($pse->getNewness())
                ->setWasInPromo(rand(0, 1) == 1)
                ->setWeight($pse->getWeight())
                ->setTaxRuleTitle(getRealText(20))
                ->setTaxRuleDescription(getRealText(50))
                ->setEanCode($pse->getEanCode())
                ->save($con);
        }

    }

    echo "Generating coupons fixtures\n";

    generateCouponFixtures($thelia);

    echo "Generating sales\n";

    for($idx = 1; $idx <= 5; $idx++) {

        $sale = new \Thelia\Model\Sale();

        $start = new \DateTime();
        $end = new \DateTime();

        $sale
            ->setActive(0)
            ->setStartDate($start->setTimestamp(strtotime("today - 1 month")))
            ->setEndDate($end->setTimestamp(strtotime("today + 1 month")))
            ->setPriceOffsetType(\Thelia\Model\Sale::OFFSET_TYPE_PERCENTAGE)
            ->setDisplayInitialPrice(true)
        ;

        setI18n($sale, [
            'SaleLabel' => 20, 'Title' => 20, 'Chapo' => 30, 'Postscriptum' => 30, 'Description' => 50
        ]);

        $sale->save();

        $currencies = \Thelia\Model\CurrencyQuery::create()->find();

        foreach($currencies as $currency) {
            $saleOffset = new \Thelia\Model\SaleOffsetCurrency();

            $saleOffset
                ->setCurrencyId($currency->getId())
                ->setSaleId($sale->getId())
                ->setPriceOffsetValue($faker->numberBetween(10, 70))
                ->save()
            ;
        }

        $products = \Thelia\Model\ProductQuery::create()->addAscendingOrderByColumn('RAND()')->find();

        $count = $faker->numberBetween(5, 20);

        foreach ($products as $product) {

            if ( --$count < 0) break;

            $saleProduct = new \Thelia\Model\SaleProduct();

            $saleProduct
                ->setSaleId($sale->getId())
                ->setProductId($product->getId())
                ->setAttributeAvId(null)
                ->save();
            ;
        }
    }

    $con->commit();

    echo "Successfully terminated.\n";

} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    if ($e->getPrevious()) echo "Cause: ".$e->getPrevious()->getMessage()."\n";
    echo $e->getTraceAsString();

    $con->rollBack();
}

function createProduct($faker, Thelia\Model\Category $category, $position, $template, $brandIdList, &$productIdList, &$virtualProductList)
{
    $product = new Thelia\Model\Product();
    $product->setRef($category->getId() . '_' . $position . '_' . $faker->randomNumber(8));
    $product->addCategory($category);
    $product->setVisible(1);
    $productCategories = $product->getProductCategories();
    $collection = new \Propel\Runtime\Collection\Collection();
    $collection->prepend($productCategories[0]->setDefaultCategory(1));
    $product->setProductCategories($collection);
    $product->setVirtual((mt_rand(1, 5) > 1) ? 0 : 1);
    $product->setVisible(1);
    $product->setPosition($position);
    $product->setTaxRuleId(1);
    $product->setTemplate($template);
    $product->setBrandId($brandIdList[array_rand($brandIdList, 1)]);

    setI18n($product);

    $product->save();
    $productId = $product->getId();
    $productIdList[] = $productId;

    $image = new \Thelia\Model\ProductImage();
    $image->setProductId($productId);
    generate_image($image, 'product', $productId);

    $document = new \Thelia\Model\ProductDocument();
    $document->setProductId($productId);
    generate_document($document, 'product', $productId);

    if ($product->getVirtual() == 1){
        $virtualProductList[$productId] = $document->getId();
    }

    return $product;
}

function createCategory($faker, $parent, $position, &$categoryIdList, $contentIdList)
{
    $category = new Thelia\Model\Category();
    $category->setParent($parent);
    $category->setVisible(1);
    $category->setPosition($position);
    setI18n($category);

    $category->save();
    $categoryId = $category->getId();
    $categoryIdList[] = $categoryId;

    //add random associated content
    $alreadyPicked = array();
    for ($i=1; $i<rand(0, 3); $i++) {
        $categoryAssociatedContent = new Thelia\Model\CategoryAssociatedContent();
        do {
            $pick = array_rand($contentIdList, 1);
        } while (in_array($pick, $alreadyPicked));

        $alreadyPicked[] = $pick;

        $categoryAssociatedContent->setContentId($contentIdList[$pick])
            ->setCategoryId($categoryId)
            ->setPosition($i)
            ->save();
    }

    $image = new \Thelia\Model\CategoryImage();
    $image->setCategoryId($categoryId);
    generate_image($image, 'category', $categoryId);

    $document = new \Thelia\Model\CategoryDocument();
    $document->setCategoryId($categoryId);
    generate_document($document, 'category', $categoryId);

    return $category;
}

function generate_image($image, $typeobj, $id)
{
    global $faker;

    $image
        ->setTitle(getRealText(20))
        ->setDescription(getRealText(250))
        ->setChapo(getRealText(40))
        ->setPostscriptum(getRealText(40))
        ->setFile(sprintf("sample-image-%s.png", $id))
        ->save()
    ;

    $palette = new \Imagine\Image\Palette\RGB();

    // Generate images
    $imagine = new Imagine\Gd\Imagine();
    $image   = $imagine->create(new Imagine\Image\Box(320, 240), $palette->color('#E9730F'));

    $white = $palette->color('#FFF');

    $font = $imagine->font(__DIR__.'/faker-assets/FreeSans.ttf', 14, $white);

    $tbox = $font->box("THELIA");
    $image->draw()->text("THELIA", $font, new Imagine\Image\Point((320 - $tbox->getWidth()) / 2, 30));

    $str = sprintf("%s sample image", ucfirst($typeobj));
    $tbox = $font->box($str);
    $image->draw()->text($str, $font, new Imagine\Image\Point((320 - $tbox->getWidth()) / 2, 80));

    $font = $imagine->font(__DIR__.'/faker-assets/FreeSans.ttf', 18, $white);

    $str = sprintf("%s ID %d", strtoupper($typeobj), $id);
    $tbox = $font->box($str);
    $image->draw()->text($str, $font, new Imagine\Image\Point((320 - $tbox->getWidth()) / 2, 180));

    $image->draw()
        ->line(new Imagine\Image\Point(0, 0), new Imagine\Image\Point(319, 0), $white)
        ->line(new Imagine\Image\Point(319, 0), new Imagine\Image\Point(319, 239), $white)
        ->line(new Imagine\Image\Point(319, 239), new Imagine\Image\Point(0,239), $white)
        ->line(new Imagine\Image\Point(0, 239), new Imagine\Image\Point(0, 0), $white)
    ;

    $image_file = sprintf("%smedia/images/%s/sample-image-%s.png", THELIA_LOCAL_DIR, $typeobj, $id);

    if (! is_dir(dirname($image_file))) mkdir(dirname($image_file), 0777, true);

    $image->save($image_file);
}

function generate_document($document, $typeobj, $id)
{
    global $faker;

    $document
        ->setTitle(getRealText(20))
        ->setDescription(getRealText(250))
        ->setChapo(getRealText(40))
        ->setPostscriptum(getRealText(40))
        ->setFile(sprintf("sample-document-%s.txt", $id))
        ->save()
    ;

    $document_file = sprintf("%smedia/documents/%s/sample-document-%s.txt", THELIA_LOCAL_DIR, $typeobj, $id);

    if (! is_dir(dirname($document_file))) mkdir(dirname($document_file), 0777, true);

    file_put_contents($document_file, getRealText(256));
}

function getRealText($length, $locale = 'en_US') {
    global $localizedFaker, $realTextMode;

    if ($realTextMode) {
        $text = $localizedFaker[$locale]->realText($length);

        // Below 20 chars, generate a simple text, without ponctuation nor newlines.
        if ($length <= 20)
            $text = ucfirst(strtolower(preg_replace("/[^\pL\pM\pN\ ]/", '', $text)));
    } else {
        $text = $localizedFaker[$locale]->text($length);
    }

    // echo "Generated $locale text ($length) : $locale:$text\n";

    return "$locale:$text";
}

function setI18n(&$object, $fields = array('Title' => 20, 'Chapo' => 30, 'Postscriptum' => 30, 'Description' => 50) )
{
    global $localeList, $localizedFaker;

    foreach ($localeList as $locale) {

        $object->setLocale($locale);

        foreach ($fields as $name => $length) {
            $func = "set".ucfirst(strtolower($name));

            $object->$func(getRealText($length, $locale));
        }
    }
}
/**
 * Generate Coupon fixtures
 */
function generateCouponFixtures(\Thelia\Core\Thelia $thelia)
{
    /** @var $container ContainerInterface Service Container */
    $container = $thelia->getContainer();
    /** @var FacadeInterface $adapter */
    $adapter = $container->get('thelia.facade');

    // Coupons
    $coupon1 = new Thelia\Model\Coupon();
    $coupon1->setCode('XMAS');
    $coupon1->setType('thelia.coupon.type.remove_x_amount');
    $coupon1->setTitle('Christmas coupon');
    $coupon1->setShortDescription('Coupon for Christmas removing 10€ if your total checkout is more than 40€');
    $coupon1->setDescription('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras at luctus tellus. Integer turpis mauris, aliquet vitae risus tristique, pellentesque vestibulum urna. Vestibulum sodales laoreet lectus dictum suscipit. Praesent vulputate, sem id varius condimentum, quam magna tempor elit, quis venenatis ligula nulla eget libero. Cras egestas euismod tellus, id pharetra leo suscipit quis. Donec lacinia ac lacus et ultricies. Nunc in porttitor neque. Proin at quam congue, consectetur orci sed, congue nulla. Nulla eleifend nunc ligula, nec pharetra elit tempus quis. Vivamus vel mauris sed est dictum blandit. Maecenas blandit dapibus velit ut sollicitudin. In in euismod mauris, consequat viverra magna. Cras velit velit, sollicitudin commodo tortor gravida, tempus varius nulla.

Donec rhoncus leo mauris, id porttitor ante luctus tempus. Curabitur quis augue feugiat, ullamcorper mauris ac, interdum mi. Quisque aliquam lorem vitae felis lobortis, id interdum turpis mattis. Vestibulum diam massa, ornare congue blandit quis, facilisis at nisl. In tortor metus, venenatis non arcu nec, sollicitudin ornare nisl. Nunc erat risus, varius nec urna at, iaculis lacinia elit. Aenean ut felis tempus, tincidunt odio non, sagittis nisl. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec vitae hendrerit elit. Nunc sit amet gravida risus, euismod lobortis massa. Nam a erat mauris. Nam a malesuada lorem. Nulla id accumsan dolor, sed rhoncus tellus. Quisque dictum felis sed leo auctor, at volutpat lectus viverra. Morbi rutrum, est ac aliquam imperdiet, nibh sem sagittis justo, ac mattis magna lacus eu nulla.

Duis interdum lectus nulla, nec pellentesque sapien condimentum at. Suspendisse potenti. Sed eu purus tellus. Nunc quis rhoncus metus. Fusce vitae tellus enim. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam tempor porttitor erat vitae iaculis. Sed est elit, consequat non ornare vitae, vehicula eget lectus. Etiam consequat sapien mauris, eget consectetur magna imperdiet eget. Nunc sollicitudin luctus velit, in commodo nulla adipiscing fermentum. Fusce nisi sapien, posuere vitae metus sit amet, facilisis sollicitudin dui. Fusce ultricies auctor enim sit amet iaculis. Morbi at vestibulum enim, eget adipiscing eros.

Praesent ligula lorem, faucibus ut metus quis, fermentum iaculis erat. Pellentesque elit erat, lacinia sed semper ac, sagittis vel elit. Nam eu convallis est. Curabitur rhoncus odio vitae consectetur pellentesque. Nam vitae arcu nec ante scelerisque dignissim vel nec neque. Suspendisse augue nulla, mollis eget dui et, tempor facilisis erat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi ac diam ipsum. Donec convallis dui ultricies velit auctor, non lobortis nulla ultrices. Morbi vitae dignissim ante, sit amet lobortis tortor. Nunc dapibus condimentum augue, in molestie neque congue non.

Sed facilisis pellentesque nisl, eu tincidunt erat scelerisque a. Nullam malesuada tortor vel erat volutpat tincidunt. In vehicula diam est, a convallis eros scelerisque ut. Donec aliquet venenatis iaculis. Ut a arcu gravida, placerat dui eu, iaculis nisl. Quisque adipiscing orci sit amet dui dignissim lacinia. Sed vulputate lorem non dolor adipiscing ornare. Morbi ornare id nisl id aliquam. Ut fringilla elit ante, nec lacinia enim fermentum sit amet. Aenean rutrum lorem eu convallis pharetra. Cras malesuada varius metus, vitae gravida velit. Nam a varius ipsum, ac commodo dolor. Phasellus nec elementum elit. Etiam vel adipiscing leo.');
    $coupon1->setEffects(array(
        RemoveXAmount::AMOUNT_FIELD_NAME => 10.00,
    ));
    $coupon1->setIsUsed(true);
    $coupon1->setIsEnabled(true);
    $date = new \DateTime();
    $coupon1->setExpirationDate($date->setTimestamp(strtotime("today + 3 months")));

    $condition1 = new MatchForTotalAmount($adapter);
    $operators = array(
        MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
        MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
    );
    $values = array(
        MatchForTotalAmount::CART_TOTAL => 40.00,
        MatchForTotalAmount::CART_CURRENCY => 'EUR'
    );
    $condition1->setValidatorsFromForm($operators, $values);

    $condition2 = new MatchForTotalAmount($adapter);
    $operators = array(
        MatchForTotalAmount::CART_TOTAL => Operators::INFERIOR,
        MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
    );
    $values = array(
        MatchForTotalAmount::CART_TOTAL => 400.00,
        MatchForTotalAmount::CART_CURRENCY => 'EUR'
    );
    $condition2->setValidatorsFromForm($operators, $values);

    $conditions = new ConditionCollection();
    $conditions[] = $condition1;
    $conditions[] = $condition2;
    /** @var ConditionFactory $conditionFactory */
    $conditionFactory = $container->get('thelia.condition.factory');

    $serializedConditions = $conditionFactory->serializeConditionCollection($conditions);
    $coupon1->setSerializedConditions($serializedConditions);
    $coupon1->setMaxUsage(40);
    $coupon1->setIsCumulative(true);
    $coupon1->setIsRemovingPostage(false);
    $coupon1->setIsAvailableOnSpecialOffers(true);
    $coupon1->setPerCustomerUsageCount(false);
    $coupon1->save();

    // Coupons
    $coupon2 = new Thelia\Model\Coupon();
    $coupon2->setCode('SPRINGBREAK');
    $coupon2->setType('thelia.coupon.type.remove_x_percent');
    $coupon2->setTitle('Springbreak coupon');
    $coupon2->setShortDescription('Coupon for Springbreak removing 10% if you have more than 4 articles in your cart');
    $coupon2->setDescription('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras at luctus tellus. Integer turpis mauris, aliquet vitae risus tristique, pellentesque vestibulum urna. Vestibulum sodales laoreet lectus dictum suscipit. Praesent vulputate, sem id varius condimentum, quam magna tempor elit, quis venenatis ligula nulla eget libero. Cras egestas euismod tellus, id pharetra leo suscipit quis. Donec lacinia ac lacus et ultricies. Nunc in porttitor neque. Proin at quam congue, consectetur orci sed, congue nulla. Nulla eleifend nunc ligula, nec pharetra elit tempus quis. Vivamus vel mauris sed est dictum blandit. Maecenas blandit dapibus velit ut sollicitudin. In in euismod mauris, consequat viverra magna. Cras velit velit, sollicitudin commodo tortor gravida, tempus varius nulla.

Donec rhoncus leo mauris, id porttitor ante luctus tempus. Curabitur quis augue feugiat, ullamcorper mauris ac, interdum mi. Quisque aliquam lorem vitae felis lobortis, id interdum turpis mattis. Vestibulum diam massa, ornare congue blandit quis, facilisis at nisl. In tortor metus, venenatis non arcu nec, sollicitudin ornare nisl. Nunc erat risus, varius nec urna at, iaculis lacinia elit. Aenean ut felis tempus, tincidunt odio non, sagittis nisl. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec vitae hendrerit elit. Nunc sit amet gravida risus, euismod lobortis massa. Nam a erat mauris. Nam a malesuada lorem. Nulla id accumsan dolor, sed rhoncus tellus. Quisque dictum felis sed leo auctor, at volutpat lectus viverra. Morbi rutrum, est ac aliquam imperdiet, nibh sem sagittis justo, ac mattis magna lacus eu nulla.

Duis interdum lectus nulla, nec pellentesque sapien condimentum at. Suspendisse potenti. Sed eu purus tellus. Nunc quis rhoncus metus. Fusce vitae tellus enim. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam tempor porttitor erat vitae iaculis. Sed est elit, consequat non ornare vitae, vehicula eget lectus. Etiam consequat sapien mauris, eget consectetur magna imperdiet eget. Nunc sollicitudin luctus velit, in commodo nulla adipiscing fermentum. Fusce nisi sapien, posuere vitae metus sit amet, facilisis sollicitudin dui. Fusce ultricies auctor enim sit amet iaculis. Morbi at vestibulum enim, eget adipiscing eros.

Praesent ligula lorem, faucibus ut metus quis, fermentum iaculis erat. Pellentesque elit erat, lacinia sed semper ac, sagittis vel elit. Nam eu convallis est. Curabitur rhoncus odio vitae consectetur pellentesque. Nam vitae arcu nec ante scelerisque dignissim vel nec neque. Suspendisse augue nulla, mollis eget dui et, tempor facilisis erat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi ac diam ipsum. Donec convallis dui ultricies velit auctor, non lobortis nulla ultrices. Morbi vitae dignissim ante, sit amet lobortis tortor. Nunc dapibus condimentum augue, in molestie neque congue non.

Sed facilisis pellentesque nisl, eu tincidunt erat scelerisque a. Nullam malesuada tortor vel erat volutpat tincidunt. In vehicula diam est, a convallis eros scelerisque ut. Donec aliquet venenatis iaculis. Ut a arcu gravida, placerat dui eu, iaculis nisl. Quisque adipiscing orci sit amet dui dignissim lacinia. Sed vulputate lorem non dolor adipiscing ornare. Morbi ornare id nisl id aliquam. Ut fringilla elit ante, nec lacinia enim fermentum sit amet. Aenean rutrum lorem eu convallis pharetra. Cras malesuada varius metus, vitae gravida velit. Nam a varius ipsum, ac commodo dolor. Phasellus nec elementum elit. Etiam vel adipiscing leo.');
    $coupon2->setEffects(array(
        RemoveXPercent::INPUT_PERCENTAGE_NAME => 10.00
    ));
    $coupon2->setIsUsed(true);
    $coupon2->setIsEnabled(true);
    $date = new \DateTime();
    $coupon2->setExpirationDate($date->setTimestamp(strtotime("today + 1 months")));

    $condition1 = new MatchForXArticles($adapter);
    $operators = array(
        MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR,
    );
    $values = array(
        MatchForXArticles::CART_QUANTITY => 4,
    );
    $condition1->setValidatorsFromForm($operators, $values);
    $conditions = new ConditionCollection();
    $conditions[] = $condition1;

    /** @var ConditionFactory $conditionFactory */
    $conditionFactory = $container->get('thelia.condition.factory');

    $serializedConditions = $conditionFactory->serializeConditionCollection($conditions);
    $coupon2->setSerializedConditions($serializedConditions);
    $coupon2->setMaxUsage(-1);
    $coupon2->setIsCumulative(false);
    $coupon2->setIsRemovingPostage(true);
    $coupon2->setIsAvailableOnSpecialOffers(true);
    $coupon2->setPerCustomerUsageCount(false);
    $coupon2->save();

    // Coupons
    $coupon3 = new Thelia\Model\Coupon();
    $coupon3->setCode('OLD');
    $coupon3->setType('thelia.coupon.type.remove_x_percent');
    $coupon3->setTitle('Old coupon');
    $coupon3->setShortDescription('Coupon for Springbreak removing 10% if you have more than 4 articles in your cart');
    $coupon3->setDescription('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras at luctus tellus. Integer turpis mauris, aliquet vitae risus tristique, pellentesque vestibulum urna. Vestibulum sodales laoreet lectus dictum suscipit. Praesent vulputate, sem id varius condimentum, quam magna tempor elit, quis venenatis ligula nulla eget libero. Cras egestas euismod tellus, id pharetra leo suscipit quis. Donec lacinia ac lacus et ultricies. Nunc in porttitor neque. Proin at quam congue, consectetur orci sed, congue nulla. Nulla eleifend nunc ligula, nec pharetra elit tempus quis. Vivamus vel mauris sed est dictum blandit. Maecenas blandit dapibus velit ut sollicitudin. In in euismod mauris, consequat viverra magna. Cras velit velit, sollicitudin commodo tortor gravida, tempus varius nulla.

Donec rhoncus leo mauris, id porttitor ante luctus tempus. Curabitur quis augue feugiat, ullamcorper mauris ac, interdum mi. Quisque aliquam lorem vitae felis lobortis, id interdum turpis mattis. Vestibulum diam massa, ornare congue blandit quis, facilisis at nisl. In tortor metus, venenatis non arcu nec, sollicitudin ornare nisl. Nunc erat risus, varius nec urna at, iaculis lacinia elit. Aenean ut felis tempus, tincidunt odio non, sagittis nisl. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec vitae hendrerit elit. Nunc sit amet gravida risus, euismod lobortis massa. Nam a erat mauris. Nam a malesuada lorem. Nulla id accumsan dolor, sed rhoncus tellus. Quisque dictum felis sed leo auctor, at volutpat lectus viverra. Morbi rutrum, est ac aliquam imperdiet, nibh sem sagittis justo, ac mattis magna lacus eu nulla.

Duis interdum lectus nulla, nec pellentesque sapien condimentum at. Suspendisse potenti. Sed eu purus tellus. Nunc quis rhoncus metus. Fusce vitae tellus enim. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam tempor porttitor erat vitae iaculis. Sed est elit, consequat non ornare vitae, vehicula eget lectus. Etiam consequat sapien mauris, eget consectetur magna imperdiet eget. Nunc sollicitudin luctus velit, in commodo nulla adipiscing fermentum. Fusce nisi sapien, posuere vitae metus sit amet, facilisis sollicitudin dui. Fusce ultricies auctor enim sit amet iaculis. Morbi at vestibulum enim, eget adipiscing eros.

Praesent ligula lorem, faucibus ut metus quis, fermentum iaculis erat. Pellentesque elit erat, lacinia sed semper ac, sagittis vel elit. Nam eu convallis est. Curabitur rhoncus odio vitae consectetur pellentesque. Nam vitae arcu nec ante scelerisque dignissim vel nec neque. Suspendisse augue nulla, mollis eget dui et, tempor facilisis erat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi ac diam ipsum. Donec convallis dui ultricies velit auctor, non lobortis nulla ultrices. Morbi vitae dignissim ante, sit amet lobortis tortor. Nunc dapibus condimentum augue, in molestie neque congue non.

Sed facilisis pellentesque nisl, eu tincidunt erat scelerisque a. Nullam malesuada tortor vel erat volutpat tincidunt. In vehicula diam est, a convallis eros scelerisque ut. Donec aliquet venenatis iaculis. Ut a arcu gravida, placerat dui eu, iaculis nisl. Quisque adipiscing orci sit amet dui dignissim lacinia. Sed vulputate lorem non dolor adipiscing ornare. Morbi ornare id nisl id aliquam. Ut fringilla elit ante, nec lacinia enim fermentum sit amet. Aenean rutrum lorem eu convallis pharetra. Cras malesuada varius metus, vitae gravida velit. Nam a varius ipsum, ac commodo dolor. Phasellus nec elementum elit. Etiam vel adipiscing leo.');
    $coupon3->setEffects(array(
        RemoveXPercent::INPUT_PERCENTAGE_NAME => 10.00,
    ));
    $coupon3->setIsUsed(false);
    $coupon3->setIsEnabled(false);
    $date = new \DateTime();
    $coupon3->setExpirationDate($date->setTimestamp(strtotime("today + 2 months")));

    $condition1 = new MatchForEveryone($adapter);
    $operators = array();
    $values = array();
    $condition1->setValidatorsFromForm($operators, $values);
    $conditions = new ConditionCollection();
    $conditions[] = $condition1;

    /** @var ConditionFactory $constraintCondition */
    $constraintCondition = $container->get('thelia.condition.factory');

    $serializedConditions = $constraintCondition->serializeConditionCollection($conditions);
    $coupon3->setSerializedConditions($serializedConditions);
    $coupon3->setMaxUsage(-1);
    $coupon3->setIsCumulative(true);
    $coupon3->setIsRemovingPostage(false);
    $coupon3->setIsAvailableOnSpecialOffers(false);
    $coupon3->setPerCustomerUsageCount(false);
    $coupon3->save();
}

/**
 * get a random country and state
 *
 * @return array first row is the country id, second row is the state id or null
 */
function getRandomCountry()
{
    global $countryStateList;

    if (count($countryStateList) === 0) {
        $countryStateList = CountryQuery::create()
            ->joinState('State', \Propel\Runtime\ActiveQuery\Criteria::LEFT_JOIN)
            ->select(['Id', 'State.Id'])
            ->find()
            ->toArray()
        ;
        $countryStateList = array_map(
            function ($item) {
                return [$item['Id'], $item['State.Id']];
            },
            $countryStateList
        );
    }

    return $countryStateList[mt_rand(0, count($countryStateList) - 1)];
}

function usage()
{
    $usage = <<<USAGE
Generate fake data for your Thelia website

Usage:

    php faker.php <OPTIONS>

Options:

    -h
        Display this message and exit

    -b <bootstrap file>
        Use this bootstrap file

    -c <number of categories>
        Maximum number of categories and sub categories to create (default: 20)

    -p <number of products>
        Maximum number of products to create in a category (default: 20)

    -l <locale list>
        The list of locales (separated with a ,) for which to generate content (default: fr_FR, en_US, es_ES, it_IT, de_DE)

    -r <real text>
        Use real text or not. real text mode is much more slower than false text mode.
        0 : false text mode
        1 : real text mode (default)

Examples:

    Generate content in english and french with false text for 5 categories and 10 products

    php faker.php -r 0 -c 5 -p 10 -l 'fr_FR, en_US'

USAGE;

    echo $usage;
}