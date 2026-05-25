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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\AddressQuery;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\Base\CartQuery;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\CouponI18nQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FolderI18nQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleProductQuery;
use Thelia\Model\SaleQuery;
use Thelia\Tools\URL;

#[AsCommand(
    name: 'thelia:demo:import',
    description: 'Import demo data (csv + images) and initialize the store'
)]
class DemoImportCommand extends Command
{
    private const DATA_DIR = THELIA_LIB.'Command/Import/data/';
    private const IMAGES_DIR = self::DATA_DIR.'images/';

    /** @var list<DemoImporterInterface> */
    private readonly array $importers;

    /**
     * @param iterable<DemoImporterInterface> $importers
     */
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $env,
        private readonly RouterInterface $router,
        #[AutowireIterator('thelia.demo_importer')]
        iterable $importers,
    ) {
        parent::__construct();

        $sorted = iterator_to_array($importers, false);
        usort($sorted, static fn (DemoImporterInterface $a, DemoImporterInterface $b): int => $a->priority() <=> $b->priority());
        $this->importers = $sorted;
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
        if (!\in_array($this->env, ['dev', 'test'], true)) {
            throw new \RuntimeException('This command is only available in dev environment');
        }

        new URL($this->router);

        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->setForeignKeyChecks($connection, false);

            if ($input->getOption('reset')) {
                $this->clearTables($connection, $output);
            }

            $this->setForeignKeyChecks($connection, true);

            $context = new DemoImportContext(
                connection: $connection,
                output: $output,
                withImages: !$input->getOption('skip-images'),
                dataDir: self::DATA_DIR,
                imagesDir: self::IMAGES_DIR,
            );

            foreach ($this->importers as $importer) {
                $output->writeln($importer->description());
                $importer->import($context);
            }

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
        $connection->prepare('SET foreign_key_checks = '.($enabled ? '1' : '0'))->execute();
    }

    private function clearTables(ConnectionInterface $connection, OutputInterface $output): void
    {
        $output->writeln('Cleaning tables');

        ProductAssociatedContentQuery::create()->deleteAll($connection);
        CategoryAssociatedContentQuery::create()->deleteAll($connection);
        AttributeCombinationQuery::create()->deleteAll($connection);
        FeatureProductQuery::create()->deleteAll($connection);

        FeatureQuery::create()->deleteAll($connection);
        FeatureI18nQuery::create()->deleteAll($connection);
        FeatureAvQuery::create()->deleteAll($connection);
        FeatureAvI18nQuery::create()->deleteAll($connection);

        AttributeQuery::create()->deleteAll($connection);
        AttributeI18nQuery::create()->deleteAll($connection);
        AttributeAvQuery::create()->deleteAll($connection);
        AttributeAvI18nQuery::create()->deleteAll($connection);

        BrandQuery::create()->deleteAll($connection);
        BrandI18nQuery::create()->deleteAll($connection);

        CategoryQuery::create()->deleteAll($connection);
        CategoryI18nQuery::create()->deleteAll($connection);

        ProductQuery::create()->deleteAll($connection);
        ProductI18nQuery::create()->deleteAll($connection);

        FolderQuery::create()->deleteAll($connection);
        FolderI18nQuery::create()->deleteAll($connection);

        ContentQuery::create()->deleteAll($connection);
        ContentI18nQuery::create()->deleteAll($connection);

        AccessoryQuery::create()->deleteAll($connection);

        ProductSaleElementsQuery::create()->deleteAll($connection);
        ProductPriceQuery::create()->deleteAll($connection);
        ProductImageQuery::create()->deleteAll($connection);

        AddressQuery::create()->deleteAll($connection);
        CustomerQuery::create()->deleteAll($connection);
        NewsletterQuery::create()->deleteAll($connection);

        CouponQuery::create()->deleteAll($connection);
        CouponI18nQuery::create()->deleteAll($connection);

        SaleQuery::create()->deleteAll($connection);
        SaleProductQuery::create()->deleteAll($connection);

        OrderProductTaxQuery::create()->deleteAll($connection);
        OrderProductQuery::create()->deleteAll($connection);
        OrderAddressQuery::create()->deleteAll($connection);
        OrderQuery::create()->deleteAll($connection);

        CartItemQuery::create()->deleteAll($connection);
        CartQuery::create()->deleteAll($connection);

        $output->writeln('Tables cleaned');
    }
}
