<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

use Thelia\Constraint\ConstraintFactory;
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
use Thelia\Constraint\Rule\AvailableForXArticlesManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\CouponRuleCollection;


require __DIR__ . '/../core/bootstrap.php';

$thelia = new Thelia\Core\Thelia("dev", true);
$thelia->boot();

$faker = Faker\Factory::create();
// Intialize URL management
$url = new Thelia\Tools\URL();
$con = \Propel\Runtime\Propel::getConnection(
    Thelia\Model\Map\ProductTableMap::DATABASE_NAME
);
$con->beginTransaction();

try {
    $stmt = $con->prepare("SET foreign_key_checks = 0");
    $stmt->execute();
    clearTables();
    $stmt = $con->prepare("SET foreign_key_checks = 1");
    $stmt->execute();


    $categories = createCategories();
    $color = createColors();
    $brand = createBrand();

    echo "creating templates\n";
    $template = new \Thelia\Model\Template();
    $template
        ->setLocale('fr_FR')
            ->setName('template de démo')
        ->setLocale('en_US')
            ->setName('demo template')
        ->save();

    $at = new Thelia\Model\AttributeTemplate();

    $at
        ->setTemplate($template)
        ->setAttribute($color)
        ->save();

    $ft = new Thelia\Model\FeatureTemplate();

    $ft
        ->setTemplate($template)
        ->setFeature($brand)
        ->save();
    echo "end creating templates\n";

    createProduct($categories, $template);



    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}

function createProduct($categories, $template)
{
    echo "start creating products\n";
    $fileSystem = new \Symfony\Component\Filesystem\Filesystem();
    if (($handle = fopen(THELIA_ROOT . '/install/import/products.csv', "r")) !== FALSE) {
        $row=0;
        while (($data = fgetcsv($handle, 100000, ";")) !== FALSE) {
            $row++;
            if($row == 1) continue;
            $product = new \Thelia\Model\Product();
            $productCategories = explode(';', $data[13]);
            $product
                ->setRef($data[0])
                ->setVisible(1)
                ->setTaxRuleId(1)
                ->setTemplate($template)
            ;
            foreach($productCategories as $productCategory) {

                $productCategory = trim($productCategory);
                if(array_key_exists($productCategory, $categories)) {
                    $product->addCategory($categories[$productCategory]);
                }
            }

            $productCategories = $product->getProductCategories();
            $collection = new \Propel\Runtime\Collection\Collection();
            foreach($productCategories as $i => $productCategory) {
                if($i == 0) {
                    $collection->append($productCategory->setDefaultCategory(1));
                } else {
                    $collection->append($productCategory);
                }

            }

            $product->setProductCategories($collection);

            $product
                ->setLocale('en_US')
                    ->setTitle($data[1])
                    ->setChapo($data[2])
                    ->setDescription($data[4])
                    ->setPostscriptum($data[6])
                ->setLocale('fr_Fr')
                    ->setTitle($data[1])
                    ->setChapo($data[3])
                    ->setDescription($data[5])
                    ->setPostscriptum($data[7])
            ->save();

            $images = explode(';', $data[10]);

            foreach ($images as $image) {
                $image = trim($image);
                if(empty($image)) continue;
                var_dump($image);
                $productImage = new \Thelia\Model\ProductImage();
                $productImage
                    ->setProduct($product)
                    ->setFile($image)
                    ->save();
                $fileSystem->copy(THELIA_ROOT . 'install/import/images/'.$image, THELIA_ROOT . 'local/media/images/product/'.$image, true);
            }
        }
    }

/*    $product = new Thelia\Model\Product();
    $product->setRef($category->getId() . '_' . $position . '_' . $faker->randomNumber(8));
    $product->addCategory($category);
    $product->setVisible(1);
    $productCategories = $product->getProductCategories();
    $collection = new \Propel\Runtime\Collection\Collection();
    $collection->prepend($productCategories[0]->setDefaultCategory(1));
    $product->setProductCategories($collection);
    $product->setVisible(1);
    $product->setPosition($position);
    $product->setTaxRuleId(1);
    $product->setTemplate($template);

    setI18n($faker, $product);

    $product->save();
    $productId = $product->getId();
    $productIdList[] = $productId;

    $image = new \Thelia\Model\ProductImage();
    $image->setProductId($productId);
    generate_image($image, 1, 'product', $productId);

    $document = new \Thelia\Model\ProductDocument();
    $document->setProductId($productId);
    generate_document($document, 1, 'product', $productId);*/
    echo "end creating products\n";
}

function createBrand()
{
    echo "start creating brands feature\n";
    if (($handle = fopen(THELIA_ROOT . '/install/import/brand.csv', "r")) !== FALSE) {
        $row=0;
        $feature = new \Thelia\Model\Feature();
        $feature
            ->setPosition(1)
            ->setLocale('fr_FR')
                ->setTitle('Marque')
            ->setLocale('en_US')
                ->setTitle('Brand');
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            $featureAv = new \Thelia\Model\FeatureAv();
            $featureAv
                ->setPosition($row)
                ->setLocale('fr_FR')
                    ->setTitle($data[0])
                ->setLocale('en_US')
                    ->setTitle($data[0]);
            $feature->addFeatureAv($featureAv);

        }
        $feature->save();
        fclose($handle);
    }
    echo "brands feature created successfully\n";

    return $feature;
}

function createCategories()
{
    echo "start creating categories\n";
    $categories = array();
    if (($handle = fopen(THELIA_ROOT . '/install/import/categories.csv', "r")) !== FALSE) {
        $row=0;
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            if($row==1) continue;
            $category = new \Thelia\Model\Category();
            $category
                ->setVisible(1)
                ->setPosition($row-1)
                ->setParent(0)
                ->setLocale('fr_FR')
                    ->setTitle(trim($data[0]))
                ->setLocale('en_US')
                    ->setTitle(trim($data[1]))
                ->save();
            $categories[trim($data[1])] = $category;
        }
        fclose($handle);
    }
    echo "categories created successfully\n";
    return $categories;
}

function createColors()
{
    echo "start creating colors attributes\n";
    if (($handle = fopen(THELIA_ROOT . '/install/import/colors.csv', "r")) !== FALSE) {
        $row=0;
        $attribute = new \Thelia\Model\Attribute();
        $attribute
            ->setPosition(1)
            ->setLocale('fr_FR')
                ->setTitle('Couleur')
            ->setLocale('en_US')
                ->setTitle('Colors');

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            $attributeAv = new \Thelia\Model\AttributeAv();
            $attributeAv
                ->setPosition($row)
                ->setLocale('fr_FR')
                    ->setTitle($data[0])
                ->setLocale('en_US')
                    ->setTitle($data[1]);

            $attribute->addAttributeAv($attributeAv);
        }
        $attribute->save();
        fclose($handle);
    }
    echo "colors attributes created with success\n";
    return $attribute;
}

function clearTables()
{
    $productAssociatedContent = Thelia\Model\ProductAssociatedContentQuery::create()
        ->find();
    $productAssociatedContent->delete();

    $categoryAssociatedContent = Thelia\Model\CategoryAssociatedContentQuery::create()
        ->find();
    $categoryAssociatedContent->delete();

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

    \Thelia\Model\ProductImageQuery::create()->find()->delete();
    \Thelia\Model\CategoryImageQuery::create()->find()->delete();
    \Thelia\Model\FolderImageQuery::create()->find()->delete();
    \Thelia\Model\ContentImageQuery::create()->find()->delete();

    \Thelia\Model\ProductDocumentQuery::create()->find()->delete();
    \Thelia\Model\CategoryDocumentQuery::create()->find()->delete();
    \Thelia\Model\FolderDocumentQuery::create()->find()->delete();
    \Thelia\Model\ContentDocumentQuery::create()->find()->delete();

    \Thelia\Model\CouponQuery::create()->find()->delete();
}