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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeCombination;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductSaleElements;

final class ProductsImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 80;
    }

    public function description(): string
    {
        return 'Products';
    }

    public function import(DemoImportContext $context): void
    {
        foreach ($this->readCsv($context->dataDir.'products.csv') as $data) {
            $product = (new Product())
                ->setRef($data[0])
                ->setVisible(1)
                ->setTaxRuleId(1)
                ->setTemplate($context->template());

            foreach (explode(';', $data[15]) as $categoryTitle) {
                $categoryTitle = trim($categoryTitle);
                if (\array_key_exists($categoryTitle, $context->categoriesByTitle)) {
                    $product->addCategory($context->categoriesByTitle[$categoryTitle]);
                }
            }

            if (\array_key_exists($data[11], $context->brandsByTitle)) {
                $product->setBrand($context->brandsByTitle[$data[11]]);
            }

            $product
                ->setLocale('en_US')->setTitle($data[1])->setChapo($data[2])->setDescription($data[4])->setPostscriptum($data[6])
                ->setLocale('fr_FR')->setTitle($data[1])->setChapo($data[3])->setDescription($data[5])->setPostscriptum($data[7])
                ->save($context->connection);

            $firstProductCategory = $product->getProductCategories()->getFirst();
            if (null !== $firstProductCategory) {
                $firstProductCategory
                    ->setDefaultCategory(1)
                    ->setPosition($product->getNextPosition())
                    ->save($context->connection);
            }

            if ($context->withImages) {
                $this->importImages($context, $product, $data[10]);
            }

            $this->importSaleElements($context, $product, $data);

            $product->getProductSaleElementss()->getFirst()?->setIsDefault(1)->save($context->connection);

            $this->importAssociatedContents($context, $product, $data[14]);
            $this->importFeatures($context, $product, $data[13]);

            $context->products[] = $product;
        }
    }

    private function importImages(DemoImportContext $context, Product $product, string $imageList): void
    {
        foreach (explode(';', $imageList) as $imageName) {
            $imageName = trim($imageName);
            if ('' === $imageName) {
                continue;
            }

            (new ProductImage())->setProduct($product)->setFile($imageName)->save($context->connection);
            $this->copyImage($context, $imageName, 'product');
        }
    }

    /**
     * @param list<string> $data
     */
    private function importSaleElements(DemoImportContext $context, Product $product, array $data): void
    {
        foreach (explode(';', $data[12]) as $colorValue) {
            if ('' === $colorValue) {
                continue;
            }

            $saleElements = new ProductSaleElements();
            $saleElements->setProduct($product);
            $saleElements->setRef($product->getId().'_'.uniqid('', true));
            $saleElements->setQuantity(random_int(1, 50));
            $saleElements->setPromo('' !== $data[9] ? 1 : 0);
            $saleElements->setNewness(random_int(0, 1));
            $saleElements->setWeight((float) random_int(100, 3000) / 100);
            $saleElements->save($context->connection);

            (new ProductPrice())
                ->setProductSaleElements($saleElements)
                ->setCurrencyId(1)
                ->setPrice($data[8])
                ->setPromoPrice('' !== $data[9] ? $data[9] : '0')
                ->save($context->connection);

            $attributeValueI18n = AttributeAvI18nQuery::create()
                ->filterByLocale('en_US')
                ->filterByTitle($colorValue)
                ->findOne($context->connection);

            if (null === $attributeValueI18n) {
                continue;
            }

            (new AttributeCombination())
                ->setAttributeId((int) $context->colorsAttribute()->getId())
                ->setAttributeAvId((int) $attributeValueI18n->getId())
                ->setProductSaleElements($saleElements)
                ->save($context->connection);
        }
    }

    private function importAssociatedContents(DemoImportContext $context, Product $product, string $contentList): void
    {
        foreach (explode(';', $contentList) as $contentTitle) {
            $contentTitle = trim($contentTitle);
            if (!\array_key_exists($contentTitle, $context->contentsByTitle)) {
                continue;
            }

            (new ProductAssociatedContent())
                ->setProduct($product)
                ->setContent($context->contentsByTitle[$contentTitle])
                ->save($context->connection);
        }
    }

    private function importFeatures(DemoImportContext $context, Product $product, string $featureList): void
    {
        foreach (explode(';', $featureList) as $featureTitle) {
            $featureValueI18n = FeatureAvI18nQuery::create()
                ->filterByLocale('en_US')
                ->filterByTitle($featureTitle)
                ->findOne($context->connection);

            if (null === $featureValueI18n) {
                continue;
            }

            (new FeatureProduct())
                ->setProduct($product)
                ->setFeatureId((int) $context->materialsFeature()->getId())
                ->setFeatureAvId((int) $featureValueI18n->getId())
                ->save($context->connection);
        }
    }
}
