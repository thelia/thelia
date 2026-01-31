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

namespace Thelia\Tests\Unit\Domain\Catalog\Brand;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Brand\BrandFacade;
use Thelia\Domain\Brand\DTO\BrandCreateDTO;
use Thelia\Domain\Brand\DTO\BrandSeoDTO;
use Thelia\Domain\Brand\DTO\BrandUpdateDTO;
use Thelia\Model\Brand;

class BrandFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private BrandFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new BrandFacade($this->dispatcher);
    }

    public function testCreate(): void
    {
        $dto = new BrandCreateDTO(
            title: 'My Brand',
            locale: 'en_US',
            visible: true,
        );

        $brand = $this->createBrandMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (BrandCreateEvent $event) use ($brand) {
                    self::assertSame('My Brand', $event->getTitle());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertTrue($event->getVisible());

                    $event->setBrand($brand);

                    return true;
                }),
                TheliaEvents::BRAND_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($brand, $result);
    }

    public function testCreateMinimal(): void
    {
        $dto = new BrandCreateDTO(
            title: 'Minimal Brand',
            locale: 'fr_FR',
        );

        $brand = $this->createBrandMock(11);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (BrandCreateEvent $event) use ($brand) {
                    self::assertSame('Minimal Brand', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertTrue($event->getVisible());

                    $event->setBrand($brand);

                    return true;
                }),
                TheliaEvents::BRAND_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($brand, $result);
    }

    public function testUpdate(): void
    {
        $dto = new BrandUpdateDTO(
            title: 'Updated Brand',
            locale: 'en_US',
            visible: false,
            chapo: 'Short description',
            description: 'Full description',
            postscriptum: 'Footer text',
            logoImageId: 42,
        );

        $brand = $this->createBrandMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (BrandUpdateEvent $event) use ($brand) {
                    self::assertSame(10, $event->getBrandId());
                    self::assertSame('Updated Brand', $event->getTitle());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertFalse($event->getVisible());
                    self::assertSame('Short description', $event->getChapo());
                    self::assertSame('Full description', $event->getDescription());
                    self::assertSame('Footer text', $event->getPostscriptum());
                    self::assertSame(42, $event->getLogoImageId());

                    $event->setBrand($brand);

                    return true;
                }),
                TheliaEvents::BRAND_UPDATE
            );

        $result = $this->facade->update(10, $dto);

        $this->assertSame($brand, $result);
    }

    public function testBrandCreateDTOToArray(): void
    {
        $dto = new BrandCreateDTO(
            title: 'My Brand',
            locale: 'en_US',
            visible: true,
        );

        $array = $dto->toArray();

        $this->assertSame('My Brand', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertTrue($array['visible']);
    }

    public function testBrandCreateDTODefaultValues(): void
    {
        $dto = new BrandCreateDTO(
            title: 'Test',
            locale: 'fr_FR',
        );

        $this->assertTrue($dto->visible);
    }

    public function testBrandUpdateDTOToArray(): void
    {
        $dto = new BrandUpdateDTO(
            title: 'Updated Brand',
            locale: 'en_US',
            visible: true,
            chapo: 'Short description',
            description: 'Full description',
            postscriptum: 'Footer text',
            logoImageId: 42,
        );

        $array = $dto->toArray();

        $this->assertSame('Updated Brand', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertTrue($array['visible']);
        $this->assertSame('Short description', $array['chapo']);
        $this->assertSame('Full description', $array['description']);
        $this->assertSame('Footer text', $array['postscriptum']);
        $this->assertSame(42, $array['logo_image_id']);
    }

    public function testBrandUpdateDTODefaultValues(): void
    {
        $dto = new BrandUpdateDTO(
            title: 'Test',
            locale: 'fr_FR',
        );

        $this->assertTrue($dto->visible);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
        $this->assertNull($dto->logoImageId);
    }

    public function testBrandSeoDTOToArray(): void
    {
        $dto = new BrandSeoDTO(
            locale: 'en_US',
            url: 'my-brand',
            metaTitle: 'My Brand - Shop',
            metaDescription: 'Discover our brand',
            metaKeywords: 'brand, quality, products',
        );

        $array = $dto->toArray();

        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('my-brand', $array['url']);
        $this->assertSame('My Brand - Shop', $array['meta_title']);
        $this->assertSame('Discover our brand', $array['meta_description']);
        $this->assertSame('brand, quality, products', $array['meta_keywords']);
    }

    public function testBrandSeoDTODefaultValues(): void
    {
        $dto = new BrandSeoDTO(
            locale: 'fr_FR',
        );

        $this->assertNull($dto->url);
        $this->assertNull($dto->metaTitle);
        $this->assertNull($dto->metaDescription);
        $this->assertNull($dto->metaKeywords);
    }

    private function createBrandMock(int $id): MockObject&Brand
    {
        $brand = $this->createMock(Brand::class);
        $brand->method('getId')->willReturn($id);

        return $brand;
    }
}
