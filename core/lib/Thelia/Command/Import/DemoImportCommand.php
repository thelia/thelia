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

namespace Thelia\Command\Import;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeCombination;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\AttributeTemplate;
use Thelia\Model\Base\CartQuery;
use Thelia\Model\Brand;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\BrandImage;
use Thelia\Model\BrandQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\ContentImage;
use Thelia\Model\ContentQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\FeatureProduct;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplate;
use Thelia\Model\Folder;
use Thelia\Model\FolderI18nQuery;
use Thelia\Model\FolderImage;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleOffsetCurrency;
use Thelia\Model\SaleProduct;
use Thelia\Model\SaleProductQuery;
use Thelia\Model\SaleQuery;
use Thelia\Model\Template;
use Thelia\Tools\URL;

#[AsCommand(
    name: 'thelia:demo:import',
    description: 'Import demo data (csv + images) and initialize the store'
)]
class DemoImportCommand extends Command
{
    private const DATA_DIR = THELIA_LIB.'Command/Import/data/';
    private const IMAGES_DIR = self::DATA_DIR.'images/';

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $env,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Empty the affected tables before importing')
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Does not import or copy images')
            ->addOption('quiet-errors', null, InputOption::VALUE_NONE, 'Displays errors concisely');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (\PHP_SAPI !== 'cli') {
            throw new \RuntimeException('This command must be run in CLI');
        }
        if ($this->env !== 'dev') {
            throw new \RuntimeException('This command is only available in dev environment');
        }

        new URL();

        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->setForeignKeyChecks($connection, false);

            if ($input->getOption('reset')) {
                $this->clearTables($connection, $output);
            }

            $this->setForeignKeyChecks($connection, true);

            $materialsFeature = $this->createMaterials($connection, $output);
            $colorsAttribute = $this->createColors($connection, $output);
            $brands = $this->createBrands($connection, $output, !$input->getOption('skip-images'));
            $folders = $this->createFolders($connection, $output, !$input->getOption('skip-images'));
            $contents = $this->createContents($folders, $connection, $output, !$input->getOption('skip-images'));

            $output->writeln('Templates creation');
            $template = (new Template())
                ->setLocale('fr_FR')->setName('template de démo')
                ->setLocale('en_US')->setName('demo template');
            $template->save($connection);

            $categories = $this->createCategories((int) $template->getId(), $connection, $output, !$input->getOption('skip-images'));

            $attributeTemplate = new AttributeTemplate();
            $attributeTemplate->setTemplate($template)->setAttribute($colorsAttribute)->save($connection);

            $featureTemplate = new FeatureTemplate();
            $featureTemplate->setTemplate($template)->setFeature($materialsFeature)->save($connection);
            $output->writeln('Templates created');

            $this->createProducts(
                $categories,
                $brands,
                $contents,
                $template,
                $colorsAttribute,
                $materialsFeature,
                $connection,
                $output,
                !$input->getOption('skip-images')
            );

            $this->createSales($connection, $output);
            $this->createCustomer($connection, $output);
            $this->createConfig($folders, $contents);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            if ($input->getOption('quiet-errors')) {
                $output->writeln('<error>Error during import.</error>');
            } else {
                $output->writeln('<error>Error: '.$exception->getMessage().'</error>');
                $output->writeln('<error>Trace: '.$exception->getTraceAsString().'</error>');
            }

            return Command::FAILURE;
        }

        $output->writeln('<info>Import finished</info>');

        return Command::SUCCESS;
    }

    private function setForeignKeyChecks(ConnectionInterface $connection, bool $enabled): void
    {
        $statement = $connection->prepare('SET foreign_key_checks = '.($enabled ? '1' : '0'));
        $statement->execute();
    }

    private function createProducts(
        array $categoriesByTitle,
        array $brandsByTitle,
        array $contentsByTitle,
        Template $template,
        Attribute $colorsAttribute,
        Feature $materialsFeature,
        ConnectionInterface $connection,
        OutputInterface $output,
        bool $withImages,
    ): void {
        $output->writeln('Starting product creation');
        $filesystem = new Filesystem();

        $filePath = self::DATA_DIR.'products.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: products.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open products.csv');
        }

        $rowNumber = 0;

        while (($data = fgetcsv($handle, 100000, ';')) !== false) {
            ++$rowNumber;
            if (1 === $rowNumber) {
                continue;
            }

            $product = (new Product())
                ->setRef($data[0])
                ->setVisible(1)
                ->setTaxRuleId(1)
                ->setTemplate($template);

            $productCategoryTitles = explode(';', $data[15]);
            foreach ($productCategoryTitles as $productCategoryTitle) {
                $productCategoryTitle = trim($productCategoryTitle);
                if (\array_key_exists($productCategoryTitle, $categoriesByTitle)) {
                    $product->addCategory($categoriesByTitle[$productCategoryTitle]);
                }
            }

            $brandTitle = $data[11];
            if (\array_key_exists($brandTitle, $brandsByTitle)) {
                $product->setBrand($brandsByTitle[$brandTitle]);
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
                ->save($connection);

            $firstProductCategory = $product->getProductCategories()->getFirst();
            if (null !== $firstProductCategory) {
                $firstProductCategory->setDefaultCategory(true)->save($connection);
            }

            $firstProductCategory->setPosition($product->getNextPosition())->save($connection);

            if ($withImages) {
                $imageNames = explode(';', $data[10]);
                foreach ($imageNames as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    $productImage = new \Thelia\Model\ProductImage();
                    $productImage
                        ->setProduct($product)
                        ->setFile($imageName)
                        ->save($connection);

                    $source = self::IMAGES_DIR.$imageName;
                    $target = THELIA_LOCAL_DIR.'media/images/product/'.$imageName;
                    if (is_file($source)) {
                        $filesystem->copy($source, $target, true);
                    }
                }
            }

            $saleElementsValues = explode(';', $data[12]);
            foreach ($saleElementsValues as $saleElementValue) {
                if ('' === $saleElementValue) {
                    continue;
                }

                $saleElements = new ProductSaleElements();
                $saleElements->setProduct($product);
                $saleElements->setRef($product->getId().'_'.uniqid('', true));
                $saleElements->setQuantity(random_int(1, 50));
                $saleElements->setPromo('' !== (string) $data[9] ? 1 : 0);
                $saleElements->setNewness(random_int(0, 1));
                $saleElements->setWeight((float) random_int(100, 3000) / 100);
                $saleElements->save($connection);

                $productPrice = new ProductPrice();
                $productPrice
                    ->setProductSaleElements($saleElements)
                    ->setCurrencyId(1)
                    ->setPrice((float) $data[8])
                    ->setPromoPrice((float) $data[9])
                    ->save($connection);

                $attributeValueI18n = AttributeAvI18nQuery::create()
                    ->filterByLocale('en_US')
                    ->filterByTitle($saleElementValue)
                    ->findOne($connection);

                if (null === $attributeValueI18n) {
                    continue;
                }

                $attributeCombination = new AttributeCombination();
                $attributeCombination
                    ->setAttributeId((int) $colorsAttribute->getId())
                    ->setAttributeAvId((int) $attributeValueI18n->getId())
                    ->setProductSaleElements($saleElements)
                    ->save($connection);
            }

            $defaultSaleElements = $product->getProductSaleElementss()->getFirst();
            $defaultSaleElements?->setIsDefault(1)->save($connection);

            $associatedContentTitles = explode(';', $data[14]);
            foreach ($associatedContentTitles as $associatedContentTitle) {
                $associatedContentTitle = trim($associatedContentTitle);
                if (!\array_key_exists($associatedContentTitle, $contentsByTitle)) {
                    continue;
                }

                $associated = new ProductAssociatedContent();
                $associated->setProduct($product)
                    ->setContent($contentsByTitle[$associatedContentTitle])
                    ->save($connection);
            }

            $featureTitles = explode(';', $data[13]);
            foreach ($featureTitles as $featureTitle) {
                $featureValueI18n = FeatureAvI18nQuery::create()
                    ->filterByLocale('en_US')
                    ->filterByTitle($featureTitle)
                    ->findOne($connection);

                if (null === $featureValueI18n) {
                    continue;
                }

                $featureProduct = new FeatureProduct();
                $featureProduct
                    ->setProduct($product)
                    ->setFeatureId((int) $materialsFeature->getId())
                    ->setFeatureAvId((int) $featureValueI18n->getId())
                    ->save($connection);
            }
        }

        fclose($handle);
        $output->writeln('Finished creating products');
    }

    private function createConfig(array $foldersByTitle, array $contentsByTitle): void
    {
        ConfigQuery::write('store_name', 'Thelia');
        ConfigQuery::write('store_description', 'E-commerce solution based on Symfony');
        ConfigQuery::write('store_email', 'Thelia');
        ConfigQuery::write('store_address1', '5 rue Rochon');
        ConfigQuery::write('store_city', 'Clermont-Ferrrand');
        ConfigQuery::write('store_phone', '+(33)444053102');
        ConfigQuery::write('store_email', 'contact@thelia.net');
        ConfigQuery::write('information_folder_id', $foldersByTitle['Information']->getId());
        ConfigQuery::write('terms_conditions_content_id', $contentsByTitle['Terms and Conditions']->getId());
    }

    private function createCustomer(ConnectionInterface $connection, OutputInterface $output): void
    {
        $output->writeln('Creating customer');

        $customer = new Customer();
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
            'thelia'
        );

        $address = new \Thelia\Model\Address();
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
            ->save($connection);

        $address = new \Thelia\Model\Address();
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
            ->save($connection);

        $output->writeln('Customer created');
    }

    private function createMaterials(ConnectionInterface $connection, OutputInterface $output): Feature
    {
        $output->writeln('Starting materials feature creation');

        $filePath = self::DATA_DIR.'materials.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: materials.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open materials.csv');
        }

        $feature = (new Feature())
            ->setPosition(1)
            ->setLocale('fr_FR')->setTitle('Matière')
            ->setLocale('en_US')->setTitle('Material');

        $rowNumber = 0;
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            $featureValue = (new FeatureAv())
                ->setPosition($rowNumber)
                ->setLocale('fr_FR')->setTitle($data[0])
                ->setLocale('en_US')->setTitle($data[1]);

            $feature->addFeatureAv($featureValue);
        }

        $feature->save($connection);
        fclose($handle);

        $output->writeln('Materials feature created');

        return $feature;
    }

    private function createBrands(ConnectionInterface $connection, OutputInterface $output, bool $withImages): array
    {
        $output->writeln('Starting brand creation');
        $filesystem = new Filesystem();

        $filePath = self::DATA_DIR.'brand.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: brand.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open brand.csv');
        }

        $brandsByTitle = [];
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            if (1 === $rowNumber) {
                continue;
            }

            $brandTitle = trim($data[0]);
            $brand = (new Brand())
                ->setVisible(1)
                ->setPosition($rowNumber - 1)
                ->setLocale('fr_FR')->setTitle($brandTitle)->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle($brandTitle)->setChapo('Eos perspiciatis.')->setDescription('Eos velit enim autem eum nihil sunt ut. Porro ipsa deleniti dolore molestiae aut omnis autem.')
            ;
            $brand->save($connection);

            $brandsByTitle[$brandTitle] = $brand;

            if ($withImages) {
                $imageNames = explode(';', $data[1]);
                $logoId = null;

                foreach ($imageNames as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    $brandImage = new BrandImage();
                    $brandImage->setBrandId($brand->getId())->setFile($imageName)->save($connection);

                    if (null === $logoId) {
                        $logoId = $brandImage->getId();
                    }

                    $source = self::IMAGES_DIR.$imageName;
                    $target = THELIA_LOCAL_DIR.'media/images/brand/'.$imageName;
                    if (is_file($source)) {
                        $filesystem->copy($source, $target, true);
                    }
                }

                if (null !== $logoId) {
                    $brand->setLogoImageId($logoId);
                    $brand->save($connection);
                }
            }
        }

        fclose($handle);
        $output->writeln('Brands created');

        return $brandsByTitle;
    }

    private function createCategories(int $templateId, ConnectionInterface $connection, OutputInterface $output, bool $withImages): array
    {
        $output->writeln('Starting category creation');
        $filesystem = new Filesystem();

        $filePath = self::DATA_DIR.'categories.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: categories.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open categories.csv');
        }

        $categoriesByTitle = [];
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            if (1 === $rowNumber) {
                continue;
            }

            $title = trim($data[1]);

            $category = (new Category())
                ->setDefaultTemplateId($templateId)
                ->setVisible(1)
                ->setPosition($rowNumber - 1)
                ->setParent(0)
                ->setLocale('fr_FR')->setTitle($title)->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle($title)->setChapo('Eos perspiciatis.')->setDescription('Eos velit enim autem eum nihil sunt ut. Porro ipsa deleniti dolore molestiae aut omnis autem.')
            ;
            $category->save($connection);

            $categoriesByTitle[$title] = $category;

            if ($withImages) {
                $imageNames = explode(';', $data[6]);
                foreach ($imageNames as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    $categoryImage = new \Thelia\Model\CategoryImage();
                    $categoryImage->setCategory($category)->setFile($imageName)->save($connection);

                    $source = self::IMAGES_DIR.$imageName;
                    $target = THELIA_LOCAL_DIR.'media/images/category/'.$imageName;
                    if (is_file($source)) {
                        $filesystem->copy($source, $target, true);
                    }
                }
            }
        }

        fclose($handle);
        $output->writeln('Categories created');

        return $categoriesByTitle;
    }

    private function createSales(ConnectionInterface $connection, OutputInterface $output): array
    {
        $output->writeln('Starting sales creation');

        $filePath = self::DATA_DIR.'sales.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: sales.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open sales.csv');
        }

        $salesByTitle = [];
        $rowNumber = 0;

        $start = new \DateTime();
        $end = new \DateTime();
        $currencies = CurrencyQuery::create()->find();
        $products = ProductQuery::create()->find();

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            if (1 === $rowNumber) {
                continue;
            }

            $sale = (new Sale())
                ->setActive(0)
                ->setStartDate($start->setTimestamp(strtotime('today - 1 month')))
                ->setEndDate($end->setTimestamp(strtotime('today + 1 month')))
                ->setPriceOffsetType($data[2])
                ->setDisplayInitialPrice(true)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle(trim($data[1]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
            ;
            $sale->save($connection);

            foreach ($currencies as $currency) {
                $saleOffset = new SaleOffsetCurrency();
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
                $saleProduct = new SaleProduct();
                $saleProduct
                    ->setSaleId($sale->getId())
                    ->setProductId($product->getId())
                    ->setAttributeAvId(null)
                    ->save();
            }

            $salesByTitle[trim($data[1])] = $sale;
        }

        fclose($handle);
        $output->writeln('Sales created');

        return $salesByTitle;
    }

    private function createFolders(ConnectionInterface $connection, OutputInterface $output, bool $withImages): array
    {
        $output->writeln('Starting folder creation');
        $filesystem = new Filesystem();

        $filePath = self::DATA_DIR.'folders.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: folders.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open folders.csv');
        }

        $foldersByTitle = [];
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            if (1 === $rowNumber) {
                continue;
            }

            $folder = (new Folder())
                ->setVisible(1)
                ->setPosition($rowNumber - 1)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle(trim($data[1]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
            ;
            $folder->save($connection);

            $foldersByTitle[trim($data[1])] = $folder;

            if ($withImages) {
                $imageNames = explode(';', $data[6]);
                for ($i = 0; $i < \count($imageNames); ++$i) {
                    $imageName = trim($imageNames[$i]);
                    if ('' === $imageName) {
                        continue;
                    }

                    $folderImage = new FolderImage();
                    $folderImage->setFolderId($folder->getId())->setFile($imageName)->save($connection);

                    $source = self::IMAGES_DIR.$imageName;
                    $target = THELIA_LOCAL_DIR.'media/images/folder/'.$imageName;
                    if (is_file($source)) {
                        $filesystem->copy($source, $target, true);
                    }
                }
            }
        }

        fclose($handle);
        $output->writeln('Folders created');

        return $foldersByTitle;
    }

    private function createContents(array $foldersByTitle, ConnectionInterface $connection, OutputInterface $output, bool $withImages): array
    {
        $output->writeln('Starting content creation');
        $filesystem = new Filesystem();

        $filePath = self::DATA_DIR.'contents.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: contents.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open contents.csv');
        }

        $contentsByTitle = [];
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            if (1 === $rowNumber) {
                continue;
            }

            $content = (new Content())
                ->setVisible(1)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle(trim($data[1]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
            ;

            $contentFolderTitles = explode(';', $data[7]);
            $defaultFolderSet = false;
            foreach ($contentFolderTitles as $contentFolderTitle) {
                $contentFolderTitle = trim($contentFolderTitle);
                if (\array_key_exists($contentFolderTitle, $foldersByTitle)) {
                    $content->addFolder($foldersByTitle[$contentFolderTitle]);
                }
            }

            $content->getContentFolders()->getFirst()?->setDefaultFolder(true)->save($connection);
            $content->save($connection);

            if ($withImages) {
                $imageNames = explode(';', $data[6]);
                foreach ($imageNames as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    $contentImage = new ContentImage();
                    $contentImage->setContentId($content->getId())->setFile($imageName)->save($connection);

                    $source = self::IMAGES_DIR.$imageName;
                    $target = THELIA_LOCAL_DIR.'media/images/content/'.$imageName;
                    if (is_file($source)) {
                        $filesystem->copy($source, $target, true);
                    }
                }
            }

            $contentsByTitle[trim($data[1])] = $content;
        }

        fclose($handle);
        $output->writeln('Contents created');

        return $contentsByTitle;
    }

    private function createColors(ConnectionInterface $connection, OutputInterface $output): Attribute
    {
        $output->writeln('Starting color attributes creation');

        $filePath = self::DATA_DIR.'colors.csv';
        if (!is_file($filePath)) {
            throw new \RuntimeException('Missing file: colors.csv');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open colors.csv');
        }

        $attribute = (new Attribute())
            ->setPosition(1)
            ->setLocale('fr_FR')->setTitle('Couleur')
            ->setLocale('en_US')->setTitle('Colors');

        $rowNumber = 0;
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            ++$rowNumber;
            $attributeValue = (new AttributeAv())
                ->setPosition($rowNumber)
                ->setLocale('fr_FR')->setTitle($data[0])
                ->setLocale('en_US')->setTitle($data[1]);

            $attribute->addAttributeAv($attributeValue);
        }

        $attribute->save($connection);
        fclose($handle);

        $output->writeln('Color attributes created');

        return $attribute;
    }

    private function clearTables(ConnectionInterface $connection, OutputInterface $output): void
    {
        $output->writeln('Cleaning tables');

        ProductAssociatedContentQuery::create()->find($connection)->delete($connection);
        CategoryAssociatedContentQuery::create()->find($connection)->delete($connection);
        AttributeCombinationQuery::create()->find($connection)->delete($connection);
        FeatureProductQuery::create()->find($connection)->delete($connection);

        FeatureQuery::create()->find($connection)->delete($connection);
        FeatureI18nQuery::create()->find($connection)->delete($connection);
        FeatureAvQuery::create()->find($connection)->delete($connection);
        FeatureAvI18nQuery::create()->find($connection)->delete($connection);

        AttributeQuery::create()->find($connection)->delete($connection);
        AttributeI18nQuery::create()->find($connection)->delete($connection);
        AttributeAvQuery::create()->find($connection)->delete($connection);
        AttributeAvI18nQuery::create()->find($connection)->delete($connection);

        BrandQuery::create()->find($connection)->delete($connection);
        BrandI18nQuery::create()->find($connection)->delete($connection);

        CategoryQuery::create()->find($connection)->delete($connection);
        CategoryI18nQuery::create()->find($connection)->delete($connection);

        ProductQuery::create()->find($connection)->delete($connection);
        ProductI18nQuery::create()->find($connection)->delete($connection);

        FolderQuery::create()->find($connection)->delete($connection);
        FolderI18nQuery::create()->find($connection)->delete($connection);

        ContentQuery::create()->find($connection)->delete($connection);
        ContentI18nQuery::create()->find($connection)->delete($connection);

        AccessoryQuery::create()->find($connection)->delete($connection);

        ProductSaleElementsQuery::create()->find($connection)->delete($connection);
        ProductPriceQuery::create()->find($connection)->delete($connection);
        ProductImageQuery::create()->find($connection)->delete($connection);

        CustomerQuery::create()->find($connection)->delete($connection);

        SaleQuery::create()->find($connection)->delete($connection);
        SaleProductQuery::create()->find($connection)->delete($connection);

        OrderQuery::create()->find($connection)->delete($connection);
        CartQuery::create()->find($connection)->delete($connection);

        $output->writeln('Tables cleaned');
    }
}
