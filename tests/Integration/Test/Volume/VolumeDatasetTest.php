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

namespace Thelia\Tests\Integration\Test\Volume;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Volume\Command\GenerateVolumeDatasetCommand;
use Thelia\Test\Volume\VolumeDatasetFingerprint;
use Thelia\Test\Volume\VolumeDatasetGenerator;
use Thelia\Test\Volume\VolumeDatasetManifest;
use Thelia\Test\Volume\VolumeDatasetOptions;
use Thelia\Test\Volume\VolumeDatasetPurger;
use Thelia\Test\Volume\VolumeDatasetTables;

final class VolumeDatasetTest extends IntegrationTestCase
{
    private const LOCALES = ['en_US', 'fr_FR'];

    private const CURRENCIES = ['EUR', 'USD'];

    private string $mediaDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaDirectory = sys_get_temp_dir().'/volume-dataset-'.bin2hex(random_bytes(4)).'/';
        (new Filesystem())->mkdir($this->mediaDirectory.'images/product');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->mediaDirectory);

        parent::tearDown();
    }

    public function testEveryGeneratedProductHasAPriceInEveryCurrencyAnImageACategoryAndATitleInEveryLocale(): void
    {
        $this->generate();
        $connection = $this->getPropelConnection();
        $generated = "p.ref LIKE 'VOL-%'";

        $checks = [
            'without a default combination' => "SELECT COUNT(*) FROM product p WHERE {$generated} AND NOT EXISTS (SELECT 1 FROM product_sale_elements e WHERE e.product_id = p.id AND e.is_default = 1)",
            'with a combination missing a currency' => "SELECT COUNT(*) FROM product p JOIN product_sale_elements e ON e.product_id = p.id WHERE {$generated} AND (SELECT COUNT(*) FROM product_price pp WHERE pp.product_sale_elements_id = e.id) <> 2",
            'without an image' => "SELECT COUNT(*) FROM product p WHERE {$generated} AND NOT EXISTS (SELECT 1 FROM product_image i WHERE i.product_id = p.id)",
            'without a default category' => "SELECT COUNT(*) FROM product p WHERE {$generated} AND NOT EXISTS (SELECT 1 FROM product_category c WHERE c.product_id = p.id AND c.default_category = 1)",
            'without a title in a locale' => "SELECT COUNT(*) FROM product p WHERE {$generated} AND (SELECT COUNT(*) FROM product_i18n t WHERE t.id = p.id AND t.title <> '') <> 2",
            'without a URL in a locale' => "SELECT COUNT(*) FROM product p WHERE {$generated} AND (SELECT COUNT(*) FROM rewriting_url u WHERE u.view = 'product' AND u.view_id = p.id) <> 2",
            'with an image file in a locale missing' => "SELECT COUNT(*) FROM product p JOIN product_image i ON i.product_id = p.id WHERE {$generated} AND (SELECT COUNT(*) FROM product_image_i18n t WHERE t.id = i.id AND t.file LIKE 'volume-dataset-%') <> 2",
        ];

        self::assertSame(40, $this->scalar("SELECT COUNT(*) FROM product p WHERE {$generated}"));
        foreach ($checks as $label => $sql) {
            self::assertSame(0, $this->scalar($sql), 'Generated products '.$label);
        }

        // A generated product reads back through the model as any product of the shop.
        $product = ProductQuery::create()->filterByRef('VOL-P0000001')->findOne($connection);
        self::assertNotNull($product);
        self::assertNotSame('', (string) $product->setLocale('fr_FR')->getTitle());
        self::assertSame(1, ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->count($connection));
        self::assertGreaterThan(0, $product->getDefaultCategoryId());
        self::assertFileExists($this->mediaDirectory.'images/product/volume-dataset-1.jpg');

        self::assertSame(15, $this->scalar("SELECT COUNT(*) FROM `order` WHERE ref LIKE 'VOL-%'"));
        self::assertSame(0, $this->scalar("SELECT COUNT(*) FROM `order` o WHERE o.ref LIKE 'VOL-%' AND NOT EXISTS (SELECT 1 FROM order_product l WHERE l.order_id = o.id)"));
    }

    public function testTheSameOptionsWriteTheSameDatasetAndAnotherSeedAnotherOne(): void
    {
        $this->generate();
        $first = VolumeDatasetFingerprint::of($this->getPropelConnection());
        $this->purger()->purge();

        $this->generate();
        $second = VolumeDatasetFingerprint::of($this->getPropelConnection());
        $this->purger()->purge();

        $this->generate(seed: 7);
        $other = VolumeDatasetFingerprint::of($this->getPropelConnection());

        self::assertSame($first->rows, $second->rows);
        self::assertSame($first->digest, $second->digest);
        self::assertNotSame($first->digest, $other->digest);
    }

    public function testThePurgeLeavesTheShopAsItWasAndKeepsItsOwnRows(): void
    {
        $factory = $this->createFixtureFactory();
        $shopProduct = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['ref' => 'VOL-OF-THE-SHOP']);
        $before = $this->tableCounts();

        $this->generate();
        self::assertNotSame($before, $this->tableCounts());

        $counted = $this->purger()->count();
        $deleted = $this->purger()->purge();

        self::assertSame($counted, $deleted);
        self::assertSame($before, $this->tableCounts());
        self::assertNull(VolumeDatasetManifest::load($this->getPropelConnection()));
        self::assertFileDoesNotExist($this->mediaDirectory.'images/product/volume-dataset-1.jpg');
        // Its reference carries the prefix, but the dataset did not write it.
        self::assertNotNull(ProductQuery::create()->findPk($shopProduct->getId(), $this->getPropelConnection()));
    }

    public function testASecondDatasetIsRefusedUntilTheFirstIsPurged(): void
    {
        $this->generate();

        $this->expectExceptionMessage('purge it before generating another one');
        $this->generate();
    }

    public function testTheCommandRefusesADatabaseHoldingOrdersItDidNotWrite(): void
    {
        $this->createFixtureFactory()->order();

        $tester = new CommandTester(new GenerateVolumeDatasetCommand('dev'));
        $status = $tester->execute(['--products' => '1', '--orders' => '0', '--customers' => '0']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('--force', $this->display($tester));
        self::assertNull(VolumeDatasetManifest::load($this->getPropelConnection()));
    }

    public function testTheCommandRefusesTheProductionEnvironment(): void
    {
        $tester = new CommandTester(new GenerateVolumeDatasetCommand('prod'));
        $status = $tester->execute(['--products' => '1', '--force' => true]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('refuses the production environment', $this->display($tester));
        self::assertNull(VolumeDatasetManifest::load($this->getPropelConnection()));
    }

    private function generate(int $seed = 306): void
    {
        (new VolumeDatasetGenerator($this->getPropelConnection(), $this->mediaDirectory, GenerateVolumeDatasetCommand::IMAGE_SOURCE_DIRECTORY, 7))
            ->generate(new VolumeDatasetOptions(
                products: 40,
                orders: 15,
                customers: 6,
                categoryDepth: 2,
                categoriesPerLevel: 2,
                locales: self::LOCALES,
                currencyCodes: self::CURRENCIES,
                seed: $seed,
                images: 3,
            ));
    }

    private function display(CommandTester $tester): string
    {
        // The console wraps its messages at the width of the terminal.
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private function purger(): VolumeDatasetPurger
    {
        return new VolumeDatasetPurger($this->getPropelConnection(), $this->mediaDirectory);
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $counts = [];
        foreach (array_keys(VolumeDatasetTables::TABLES) as $table) {
            $counts[$table] = $this->scalar(\sprintf('SELECT COUNT(*) FROM `%s`', $table));
        }
        $counts['meta_data'] = $this->scalar('SELECT COUNT(*) FROM meta_data');

        return $counts;
    }

    private function scalar(string $sql): int
    {
        $statement = $this->getPropelConnection()->prepare($sql);
        $statement->execute();

        return (int) $statement->fetch(\PDO::FETCH_NUM)[0];
    }
}
