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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Domain\Media\DTO\ImageUploadDTO;

/**
 * What an image upload keeps of the merchant's input, accessibility fields included: a
 * merchant who gives no alternative text and does not flag the image as decorative
 * must not see it silently turned into a decorative one.
 */
final class ImageUploadDTOTest extends TestCase
{
    public function testAnImageIsNotDecorativeAndHasNoAlternativeTextByDefault(): void
    {
        $dto = new ImageUploadDTO(parentId: 7, parentType: 'product', uploadedFile: $this->uploadedFile('front.jpg'));

        self::assertFalse($dto->decorative);
        self::assertNull($dto->alt);
    }

    public function testTheEventPayloadCarriesEveryFieldTheMerchantFilled(): void
    {
        $dto = new ImageUploadDTO(
            parentId: 7,
            parentType: 'product',
            uploadedFile: $this->uploadedFile('front.jpg'),
            locale: 'fr_FR',
            title: 'Face avant',
            chapo: 'Chapo',
            description: 'Description',
            postscriptum: 'Post-scriptum',
            visible: false,
            alt: 'Un sac en cuir vu de face',
            decorative: false,
        );

        self::assertSame([
            'parent_id' => 7,
            'parent_type' => 'product',
            'file' => 'front.jpg',
            'locale' => 'fr_FR',
            'title' => 'Face avant',
            'chapo' => 'Chapo',
            'description' => 'Description',
            'postscriptum' => 'Post-scriptum',
            'visible' => false,
            'alt' => 'Un sac en cuir vu de face',
            'decorative' => false,
        ], $dto->toArray());
    }

    public function testADecorativeImageIsReportedAsSuch(): void
    {
        $dto = new ImageUploadDTO(parentId: 7, parentType: 'product', uploadedFile: $this->uploadedFile('swirl.png'), decorative: true);

        self::assertTrue($dto->toArray()['decorative']);
        self::assertNull($dto->toArray()['alt']);
    }

    private function uploadedFile(string $originalName): UploadedFile
    {
        $file = $this->createStub(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($originalName);

        return $file;
    }
}
