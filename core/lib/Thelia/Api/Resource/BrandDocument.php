<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Controller\Admin\BinaryFileController;
use Thelia\Api\Controller\Admin\PostItemFileController;
use Thelia\Model\Map\BrandDocumentTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/brand_documents',
            inputFormats: ['multipart' => ['multipart/form-data']],
            controller: PostItemFileController::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_FILE]],
            deserialize: false
        ),
        new GetCollection(
            uriTemplate: '/admin/brand_documents'
        ),
        new Get(
            uriTemplate: '/admin/brand_documents/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/brand_documents/{id}/file',
            controller: BinaryFileController::class,
            openapi: new Operation(
                responses: [
                    '200' => [
                        'description' => 'The binary file',
                    ],
                ]
            )
        ),
        new Put(
            uriTemplate: '/admin/brand_documents/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]],
        ),
        new Patch(
            uriTemplate: '/admin/brand_documents/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]]
        ),
        new Delete(
            uriTemplate: '/admin/brand_documents/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/brand_documents'
        ),
        new Get(
            uriTemplate: '/admin/brand_documents/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/brand_documents/{id}/file',
            controller: BinaryFileController::class,
            openapi: new Operation(
                responses: [
                    '200' => [
                        'description' => 'The binary file',
                    ],
                ]
            )
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ]
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
    ]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'brand.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'brand_document.brand_id',
        ],
    ]
)]
class BrandDocument extends AbstractTranslatableResource implements ItemFileResourceInterface
{
    public const GROUP_ADMIN_READ = 'admin:brand_document:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:brand_document:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:brand_document:write';
    public const GROUP_ADMIN_WRITE_FILE = 'admin:brand_document:write_file';
    public const GROUP_ADMIN_WRITE_UPDATE = 'admin:brand_document:write_update';

    public const GROUP_FRONT_READ = 'front:brand_document:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:brand_document:read:single';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Brand::class)]
    #[Groups([self::GROUP_ADMIN_WRITE_FILE, self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public Brand $brand;

    #[Groups([self::GROUP_ADMIN_WRITE_FILE])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'format' => 'binary',
        ]
    )]
    public UploadedFile $fileToUpload;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?int $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public I18nCollection $i18ns;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public string $file;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $fileUrl;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getFileToUpload(): UploadedFile
    {
        return $this->fileToUpload;
    }

    public function setFileToUpload(UploadedFile $fileToUpload): self
    {
        $this->fileToUpload = $fileToUpload;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): self
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new BrandDocumentTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return BrandDocumentI18n::class;
    }

    public static function getItemType(): string
    {
        return 'brand';
    }

    public static function getFileType(): string
    {
        return 'document';
    }

    public function getItemId(): string
    {
        return $this->getBrand()->getId();
    }
}
