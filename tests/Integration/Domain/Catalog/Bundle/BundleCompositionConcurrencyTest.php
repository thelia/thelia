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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\IntegrationTestCase;

/**
 * "No bundle in a bundle" against another writer: a product becoming a bundle while it is added
 * as a component, written on a second connection that commits at the instant the test chooses.
 *
 * The writer under test runs inside a transaction that already read the catalogue, as the update of
 * a product does: a plain read there returns the picture taken before the other commit. The service
 * holds the product row and decides by locking reads, which see that commit.
 *
 * The rows have to be committed for the second connection to see them, so this test runs outside the
 * transaction wrapper and deletes everything it wrote.
 */
#[Group('concurrency')]
final class BundleCompositionConcurrencyTest extends IntegrationTestCase
{
    use BuildsBundles;

    protected bool $useTransaction = false;

    private ?ConnectionInterface $other = null;

    /** @var list<Product> */
    private array $products = [];

    protected function tearDown(): void
    {
        try {
            $connection = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);

            while ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->other?->inTransaction() && $this->other->rollBack();

            if ([] !== $this->products) {
                $in = implode(',', array_map(static fn (Product $product): int => (int) $product->getId(), $this->products));
                $categoryIds = $this->other()->query("SELECT DISTINCT category_id FROM product_category WHERE product_id IN ($in)")->fetchAll(\PDO::FETCH_COLUMN);
                $this->other()->exec("DELETE FROM product WHERE id IN ($in)");

                if ([] !== $categoryIds) {
                    $this->other()->exec('DELETE FROM category WHERE id IN ('.implode(',', array_map('intval', $categoryIds)).')');
                }
            }

            // The products went by a plain DELETE, which the model keeping the state never saw.
            ConfigQuery::resetCache();
            (new BundleCatalogState())->refresh();
        } finally {
            $this->other = null;
            $this->products = [];
            ConfigQuery::resetCache();
        }

        parent::tearDown();
    }

    public function testAProductAddedAsAComponentMeanwhileCannotBecomeABundle(): void
    {
        [$bundle, $product] = $this->aBundleAndAProduct();
        $saleElementsId = (int) $this->saleElementsOf($product)->getId();
        $connection = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        // What the update of a product does first: the row is held before any plain read, so the
        // picture of the transaction is taken after the last commit of another writer.
        $this->service()->lockProduct((int) $product->getId());

        $this->other()->exec(\sprintf(
            'INSERT INTO product_bundle_item (bundle_product_id, product_sale_elements_id, quantity, required, position, created_at, updated_at) VALUES (%d, %d, 1, 1, 0, NOW(), NOW())',
            $bundle->getId(),
            $saleElementsId,
        ));

        try {
            $this->service()->setBundleSettings((int) $product->getId(), true);
            self::fail('The product is a component of a bundle committed before the switch was decided.');
        } catch (BundleDefinitionException $refusal) {
            self::assertStringContainsString('component', $refusal->getMessage());
        } finally {
            $connection->rollBack();
        }
    }

    public function testAProductThatBecameABundleMeanwhileCannotBeAddedAsAComponent(): void
    {
        [$bundle, $product] = $this->aBundleAndAProduct();
        $connection = $this->openATransactionThatAlreadyReadTheCatalogue($product);

        $this->other()->exec(\sprintf('UPDATE product SET is_bundle = 1 WHERE id = %d', $product->getId()));

        try {
            $this->service()->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($product)->getId());
            self::fail('The product became a bundle before the component was decided.');
        } catch (BundleDefinitionException $refusal) {
            self::assertStringContainsString('is a bundle', $refusal->getMessage());
        } finally {
            $connection->rollBack();
        }
    }

    public function testAddingAComponentHoldsItsProductUntilTheCommit(): void
    {
        [$bundle, $product] = $this->aBundleAndAProduct();
        $connection = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->service()->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($product)->getId());

            $this->other()->exec('SET SESSION innodb_lock_wait_timeout = 1');

            try {
                $this->other()->query(\sprintf('SELECT id FROM product WHERE id = %d FOR UPDATE', $product->getId()));
                self::fail('Another writer could take the product while the component is not committed.');
            } catch (\PDOException $waited) {
                self::assertSame(1205, $waited->errorInfo[1] ?? null, $waited->getMessage());
            }
        } finally {
            $connection->rollBack();
        }
    }

    public function testAddingAComponentHoldsThePackUntilTheCommit(): void
    {
        [$bundle, $product] = $this->aBundleAndAProduct();
        $connection = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->service()->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($product)->getId());

            $this->other()->exec('SET SESSION innodb_lock_wait_timeout = 1');

            try {
                $this->other()->query(\sprintf('SELECT id FROM product WHERE id = %d FOR UPDATE', $bundle->getId()));
                self::fail('Another writer could take the pack while an item is not committed.');
            } catch (\PDOException $waited) {
                self::assertSame(1205, $waited->errorInfo[1] ?? null, $waited->getMessage());
            }
        } finally {
            $connection->rollBack();
        }
    }

    public function testASecondSaleElementForABundleIsJudgedUnderTheLockOfItsProduct(): void
    {
        [$bundle] = $this->aBundleAndAProduct();
        $connection = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            try {
                (new ProductSaleElements())->setProductId($bundle->getId())->setRef('SECOND-'.uniqid())->save($connection);
                self::fail('A bundle has one sale element.');
            } catch (BundleDefinitionException) {
                // The refusal leaves the transaction open: the row it was decided under is still held.
            }

            $this->other()->exec('SET SESSION innodb_lock_wait_timeout = 1');

            try {
                $this->other()->query(\sprintf('SELECT id FROM product WHERE id = %d FOR UPDATE', $bundle->getId()));
                self::fail('Another writer could take the product while the sale elements of the bundle were being judged.');
            } catch (\PDOException $waited) {
                self::assertSame(1205, $waited->errorInfo[1] ?? null, $waited->getMessage());
            }
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * @return array{0: Product, 1: Product} the bundle and a product, both committed
     */
    private function aBundleAndAProduct(): array
    {
        $factory = $this->createFixtureFactory();
        $bundle = $this->bundleProduct($factory, $this->service());
        $product = $this->component($factory, '10', 5);
        $this->products = [$bundle, $product];

        return [$bundle, $product];
    }

    private function openATransactionThatAlreadyReadTheCatalogue(Product $product): ConnectionInterface
    {
        $connection = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        self::assertSame(0, (int) ProductQuery::create()->findPk($product->getId(), $connection)?->getIsBundle());

        return $connection;
    }

    private function service(): BundleCompositionService
    {
        return $this->getService(BundleCompositionService::class);
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds.
     */
    private function other(): ConnectionInterface
    {
        if (null !== $this->other) {
            return $this->other;
        }

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(ProductTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $this->other = ConnectionFactory::create($connectionManager->getConfiguration(), $serviceContainer->getAdapter(ProductTableMap::DATABASE_NAME));
    }
}
