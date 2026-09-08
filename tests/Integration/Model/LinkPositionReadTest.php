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

namespace Thelia\Tests\Integration\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Category;
use Thelia\Model\Content;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\Folder;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Numbering a link after the last one is what creating a link means, and the
 * model does it when the link is associated to its category or its folder.
 * Propel associates a link every time it hydrates one, which is far more
 * often: reading a category with its products ran one `max(position)` query
 * per row and replaced the position each row carries with a number that was
 * never written back.
 */
final class LinkPositionReadTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private const POSITIONS = [1, 2, 3];

    public function testReadingProductLinksKeepsThePositionsTheyCarry(): void
    {
        $categoryId = $this->categoryWithNumberedProducts();

        $links = ProductCategoryQuery::create()
            ->filterByCategoryId($categoryId)
            ->orderByPosition(Criteria::ASC)
            ->joinWithCategory()
            ->joinWithProduct()
            ->find();

        self::assertSame(
            self::POSITIONS,
            array_map(static fn (ProductCategory $link): ?int => $link->getPosition(), iterator_to_array($links)),
        );
    }

    public function testReadingProductLinksCostsNothingBeyondTheReadItself(): void
    {
        $categoryId = $this->categoryWithNumberedProducts();

        $statements = $this->recordSqlQueries(static function () use ($categoryId): void {
            ProductCategoryQuery::create()
                ->filterByCategoryId($categoryId)
                ->joinWithCategory()
                ->joinWithProduct()
                ->find();
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'product_category'),
            'Reading the links is one query, not one query plus one per row.',
        );
    }

    public function testReadingContentLinksKeepsThePositionsTheyCarry(): void
    {
        $folderId = $this->folderWithNumberedContents();

        $links = ContentFolderQuery::create()
            ->filterByFolderId($folderId)
            ->orderByPosition(Criteria::ASC)
            ->joinWithFolder()
            ->joinWithContent()
            ->find();

        self::assertSame(
            self::POSITIONS,
            array_map(static fn (ContentFolder $link): ?int => $link->getPosition(), iterator_to_array($links)),
        );
    }

    public function testReadingContentLinksCostsNothingBeyondTheReadItself(): void
    {
        $folderId = $this->folderWithNumberedContents();

        $statements = $this->recordSqlQueries(static function () use ($folderId): void {
            ContentFolderQuery::create()
                ->filterByFolderId($folderId)
                ->joinWithFolder()
                ->joinWithContent()
                ->find();
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'content_folder'),
            'Reading the links is one query, not one query plus one per row.',
        );
    }

    /**
     * The numbering itself is the point of the hook, and a link being created
     * still gets it.
     */
    public function testANewProductLinkIsNumberedAfterTheLastOne(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $this->categoryWithNumberedProducts(returnModel: true);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $link = new ProductCategory();
        $link->setProduct($product);
        $link->setCategory($category);

        self::assertSame(max(self::POSITIONS) + 1, $link->getPosition());
    }

    public function testANewContentLinkIsNumberedAfterTheLastOne(): void
    {
        $connection = $this->getPropelConnection();
        $folder = $this->folderWithNumberedContents(returnModel: true);

        $content = new Content();
        $content->setVisible(1)->setLocale('en_US')->setTitle('Extra content')->save($connection);

        $link = new ContentFolder();
        $link->setContent($content);
        $link->setFolder($folder);

        self::assertSame(max(self::POSITIONS) + 1, $link->getPosition());
    }

    private function categoryWithNumberedProducts(bool $returnModel = false): Category|int
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        foreach (self::POSITIONS as $position) {
            $product = $factory->product($factory->category(), $taxRule, $currency);

            $link = new ProductCategory();
            $link->setProduct($product);
            $link->setCategory($category);
            $link->setDefaultCategory(false);

            // Written after the association, so the row carries the layout this
            // test reads back rather than the one the numbering picked.
            $link->setPosition($position);
            $link->save($connection);
        }

        $this->forgetLoadedModels();

        return $returnModel ? $category : (int) $category->getId();
    }

    private function folderWithNumberedContents(bool $returnModel = false): Folder|int
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $folder = $factory->folder();

        foreach (self::POSITIONS as $position) {
            $content = new Content();
            $content->setVisible(1)->setLocale('en_US')->setTitle('Content '.$position)->save($connection);

            $link = new ContentFolder();
            $link->setContent($content);
            $link->setFolder($folder);
            $link->setDefaultFolder(false);
            $link->setPosition($position);
            $link->save($connection);
        }

        $this->forgetLoadedModels();

        return $returnModel ? $folder : (int) $folder->getId();
    }

    /**
     * Propel hands a pooled instance back instead of hydrating a new one, and a
     * pooled row was never associated: emptying the pools is what makes the read
     * under test an actual read.
     */
    private function forgetLoadedModels(): void
    {
        foreach (Propel::getServiceContainer()->getDatabaseMap('thelia')->getTables() as $tableMap) {
            $tableMap->clearInstancePool();
        }
    }
}
