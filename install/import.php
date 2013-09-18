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
    createColors();

    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}

function createCategories()
{
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
                    ->setTitle($data[0])
                ->setLocale('en_US')
                    ->setTitle($data[1])
                ->save();
            $categories[$data[1]] = $category->getId();
        }
        fclose($handle);
    }

    return $categories;
}

function createColors()
{
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