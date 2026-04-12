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

namespace Thelia\Tests\Integration\Domain;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Catalog\Brand\BrandFacade;
use Thelia\Domain\Catalog\Brand\DTO\BrandSeoDTO;
use Thelia\Domain\Catalog\Brand\Exception\BrandNotFoundException;
use Thelia\Model\BrandQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Integration tests for BrandFacade methods that were deferred from
 * the unit layer because they call BrandQuery::create()->findPk().
 *
 * @see session-handover.md §4.7
 */
final class BrandFacadeTest extends IntegrationTestCase
{
    private BrandFacade $facade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facade = new BrandFacade(
            $this->getService(EventDispatcherInterface::class),
        );
    }

    public function testToggleVisibilityFlipsBrandVisibleFlag(): void
    {
        $factory = $this->createFixtureFactory();
        $brand = $factory->brand(['visible' => 1]);

        $result = $this->facade->toggleVisibility($brand->getId());

        self::assertSame(0, (int) BrandQuery::create()->findPk($brand->getId())->getVisible());
        self::assertNotNull($result);
    }

    public function testToggleVisibilityThrowsForNonExistentBrand(): void
    {
        $this->expectException(BrandNotFoundException::class);
        $this->facade->toggleVisibility(999999);
    }

    public function testUpdateSeoUpdatesSeoFields(): void
    {
        $factory = $this->createFixtureFactory();
        $brand = $factory->brand(['title' => 'SEO Brand']);

        $dto = new BrandSeoDTO(
            locale: 'en_US',
            metaTitle: 'Meta Title',
            metaDescription: 'Meta Description',
            metaKeywords: 'brand,test',
            url: null,
        );

        $result = $this->facade->updateSeo($brand->getId(), $dto);

        self::assertNotNull($result);
        $reloaded = BrandQuery::create()->findPk($brand->getId());
        $reloaded->setLocale('en_US');
        self::assertSame('Meta Title', $reloaded->getMetaTitle());
        self::assertSame('Meta Description', $reloaded->getMetaDescription());
        self::assertSame('brand,test', $reloaded->getMetaKeywords());
    }

    public function testGetByIdReturnsExistingBrand(): void
    {
        $factory = $this->createFixtureFactory();
        $brand = $factory->brand();

        $result = $this->facade->getById($brand->getId());
        self::assertNotNull($result);
        self::assertSame($brand->getId(), $result->getId());
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        self::assertNull($this->facade->getById(999999));
    }

    public function testGetAllReturnsBrands(): void
    {
        $factory = $this->createFixtureFactory();
        $factory->brand(['visible' => 1]);
        $factory->brand(['visible' => 0]);

        $all = $this->facade->getAll();
        self::assertGreaterThanOrEqual(2, \count($all));

        $visibleOnly = $this->facade->getAll(true);
        self::assertLessThanOrEqual(\count($all), \count($visibleOnly));
    }
}
