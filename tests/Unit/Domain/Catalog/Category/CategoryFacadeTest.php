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

namespace Thelia\Tests\Unit\Domain\Catalog\Category;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Catalog\Category\CategoryFacade;
use Thelia\Domain\Catalog\Category\DTO\CategoryCreateDTO;
use Thelia\Domain\Catalog\Category\DTO\CategorySeoDTO;
use Thelia\Domain\Catalog\Category\DTO\CategoryUpdateDTO;

class CategoryFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private CategoryFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new CategoryFacade($this->dispatcher);
    }

    public function testCategoryCreateDTOToArray(): void
    {
        $dto = new CategoryCreateDTO(
            title: 'My Category',
            locale: 'en_US',
            parentId: 5,
            visible: true,
        );

        $array = $dto->toArray();

        $this->assertSame('My Category', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(5, $array['parent']);
        $this->assertTrue($array['visible']);
    }

    public function testCategoryCreateDTODefaultValues(): void
    {
        $dto = new CategoryCreateDTO(
            title: 'Test',
            locale: 'fr_FR',
        );

        $this->assertSame(0, $dto->parentId);
        $this->assertTrue($dto->visible);
    }

    public function testCategoryUpdateDTOToArray(): void
    {
        $dto = new CategoryUpdateDTO(
            title: 'Updated Category',
            locale: 'en_US',
            parentId: 3,
            visible: true,
            chapo: 'Short description',
            description: 'Full description',
            postscriptum: 'Footer text',
            defaultTemplateId: 10,
        );

        $array = $dto->toArray();

        $this->assertSame('Updated Category', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(3, $array['parent']);
        $this->assertTrue($array['visible']);
        $this->assertSame('Short description', $array['chapo']);
        $this->assertSame('Full description', $array['description']);
        $this->assertSame('Footer text', $array['postscriptum']);
        $this->assertSame(10, $array['default_template_id']);
    }

    public function testCategoryUpdateDTODefaultValues(): void
    {
        $dto = new CategoryUpdateDTO(
            title: 'Test',
            locale: 'fr_FR',
        );

        $this->assertSame(0, $dto->parentId);
        $this->assertTrue($dto->visible);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
        $this->assertNull($dto->defaultTemplateId);
    }

    public function testCategorySeoDTOToArray(): void
    {
        $dto = new CategorySeoDTO(
            locale: 'en_US',
            url: 'my-category',
            metaTitle: 'My Category - Shop',
            metaDescription: 'Discover our category',
            metaKeywords: 'category, shop, products',
        );

        $array = $dto->toArray();

        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('my-category', $array['url']);
        $this->assertSame('My Category - Shop', $array['meta_title']);
        $this->assertSame('Discover our category', $array['meta_description']);
        $this->assertSame('category, shop, products', $array['meta_keywords']);
    }

    public function testCategorySeoDTODefaultValues(): void
    {
        $dto = new CategorySeoDTO(
            locale: 'fr_FR',
        );

        $this->assertNull($dto->url);
        $this->assertNull($dto->metaTitle);
        $this->assertNull($dto->metaDescription);
        $this->assertNull($dto->metaKeywords);
    }
}
