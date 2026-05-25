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
use Thelia\Model\AccessoryQuery;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\Base\CartQuery;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FolderI18nQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ProductTableMap;
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

        new URL();

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
                deterministic: true,
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
