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

namespace Thelia\Tests\Unit\Domain\Media;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\File\FileManager;
use Thelia\Domain\Media\DTO\DocumentUploadDTO;
use Thelia\Domain\Media\DTO\ImageProcessDTO;
use Thelia\Domain\Media\DTO\ImageUpdateDTO;
use Thelia\Domain\Media\DTO\ImageUploadDTO;
use Thelia\Domain\Media\MediaFacade;

class MediaFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private MockObject&FileManager $fileManager;
    private MediaFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->fileManager = $this->createMock(FileManager::class);
        $this->facade = new MediaFacade($this->dispatcher, $this->fileManager);
    }

    public function testImageUploadDTOToArray(): void
    {
        $uploadedFile = $this->createUploadedFileMock('test.jpg');

        $dto = new ImageUploadDTO(
            parentId: 1,
            parentType: 'product',
            uploadedFile: $uploadedFile,
            locale: 'fr_FR',
            title: 'Product Image',
            chapo: 'Short desc',
            description: 'Full description',
            postscriptum: 'Footer',
            visible: true,
        );

        $array = $dto->toArray();

        $this->assertSame(1, $array['parent_id']);
        $this->assertSame('product', $array['parent_type']);
        $this->assertSame('test.jpg', $array['file']);
        $this->assertSame('fr_FR', $array['locale']);
        $this->assertSame('Product Image', $array['title']);
        $this->assertSame('Short desc', $array['chapo']);
        $this->assertSame('Full description', $array['description']);
        $this->assertSame('Footer', $array['postscriptum']);
        $this->assertTrue($array['visible']);
    }

    public function testImageUploadDTODefaultValues(): void
    {
        $uploadedFile = $this->createUploadedFileMock('test.jpg');

        $dto = new ImageUploadDTO(
            parentId: 1,
            parentType: 'category',
            uploadedFile: $uploadedFile,
        );

        $this->assertSame('en_US', $dto->locale);
        $this->assertNull($dto->title);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
        $this->assertTrue($dto->visible);
    }

    public function testImageUpdateDTOToArray(): void
    {
        $dto = new ImageUpdateDTO(
            locale: 'en_US',
            title: 'Updated Title',
            chapo: 'Updated chapo',
            description: 'Updated description',
            postscriptum: 'Updated postscriptum',
            visible: false,
        );

        $array = $dto->toArray();

        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('Updated Title', $array['title']);
        $this->assertSame('Updated chapo', $array['chapo']);
        $this->assertSame('Updated description', $array['description']);
        $this->assertSame('Updated postscriptum', $array['postscriptum']);
        $this->assertFalse($array['visible']);
    }

    public function testImageUpdateDTODefaultValues(): void
    {
        $dto = new ImageUpdateDTO(
            locale: 'fr_FR',
        );

        $this->assertNull($dto->title);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
        $this->assertNull($dto->visible);
    }

    public function testImageProcessDTOToArray(): void
    {
        $dto = new ImageProcessDTO(
            sourceFilepath: '/path/to/image.jpg',
            cacheSubdirectory: 'product',
            width: 800,
            height: 600,
            resizeMode: 'crop',
            backgroundColor: '#ffffff',
            effects: ['grayscale', 'blur'],
            rotation: 90,
            quality: 85,
            allowZoom: true,
            format: 'webp',
        );

        $array = $dto->toArray();

        $this->assertSame('/path/to/image.jpg', $array['source_filepath']);
        $this->assertSame('product', $array['cache_subdirectory']);
        $this->assertSame(800, $array['width']);
        $this->assertSame(600, $array['height']);
        $this->assertSame('crop', $array['resize_mode']);
        $this->assertSame('#ffffff', $array['background_color']);
        $this->assertSame(['grayscale', 'blur'], $array['effects']);
        $this->assertSame(90, $array['rotation']);
        $this->assertSame(85, $array['quality']);
        $this->assertTrue($array['allow_zoom']);
        $this->assertSame('webp', $array['format']);
    }

    public function testImageProcessDTODefaultValues(): void
    {
        $dto = new ImageProcessDTO(
            sourceFilepath: '/path/to/image.jpg',
            cacheSubdirectory: 'category',
        );

        $this->assertNull($dto->width);
        $this->assertNull($dto->height);
        $this->assertNull($dto->resizeMode);
        $this->assertNull($dto->backgroundColor);
        $this->assertSame([], $dto->effects);
        $this->assertNull($dto->rotation);
        $this->assertNull($dto->quality);
        $this->assertFalse($dto->allowZoom);
        $this->assertNull($dto->format);
    }

    public function testDocumentUploadDTOToArray(): void
    {
        $uploadedFile = $this->createUploadedFileMock('manual.pdf');

        $dto = new DocumentUploadDTO(
            parentId: 5,
            parentType: 'product',
            uploadedFile: $uploadedFile,
            locale: 'de_DE',
            title: 'Product Manual',
            chapo: 'PDF Manual',
            description: 'Full manual description',
            postscriptum: 'Note',
            visible: true,
        );

        $array = $dto->toArray();

        $this->assertSame(5, $array['parent_id']);
        $this->assertSame('product', $array['parent_type']);
        $this->assertSame('manual.pdf', $array['file']);
        $this->assertSame('de_DE', $array['locale']);
        $this->assertSame('Product Manual', $array['title']);
        $this->assertSame('PDF Manual', $array['chapo']);
        $this->assertSame('Full manual description', $array['description']);
        $this->assertSame('Note', $array['postscriptum']);
        $this->assertTrue($array['visible']);
    }

    public function testDocumentUploadDTODefaultValues(): void
    {
        $uploadedFile = $this->createUploadedFileMock('doc.pdf');

        $dto = new DocumentUploadDTO(
            parentId: 1,
            parentType: 'content',
            uploadedFile: $uploadedFile,
        );

        $this->assertSame('en_US', $dto->locale);
        $this->assertNull($dto->title);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
        $this->assertTrue($dto->visible);
    }

    private function createUploadedFileMock(string $originalName): UploadedFile&MockObject
    {
        $mock = $this->createMock(UploadedFile::class);
        $mock->method('getClientOriginalName')->willReturn($originalName);

        return $mock;
    }
}
