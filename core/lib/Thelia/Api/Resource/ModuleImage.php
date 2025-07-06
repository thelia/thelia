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
use Symfony\Component\Validator\Constraints as Assert;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Controller\Admin\BinaryFileController;
use Thelia\Api\Controller\Admin\PostItemFileController;
use Thelia\Model\Map\ModuleImageTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/module_images',
            inputFormats: ['multipart' => ['multipart/form-data']],
            controller: PostItemFileController::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_FILE]],
            deserialize: false
        ),
        new GetCollection(
            uriTemplate: '/admin/module_images'
        ),
        new Get(
            uriTemplate: '/admin/module_images/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/module_images/{id}/file',
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
            uriTemplate: '/admin/module_images/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]],
        ),
        new Patch(
            uriTemplate: '/admin/module_images/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]]
        ),
        new Delete(
            uriTemplate: '/admin/module_images/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/module_images'
        ),
        new Get(
            uriTemplate: '/front/module_images/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/front/module_images/{id}/file',
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
        'id',
        'module.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'module_image.module_id',
        ],
    ]
)]
class ModuleImage extends AbstractTranslatableResource implements ItemFileResourceInterface
{
    public const GROUP_ADMIN_READ = 'admin:module_image:read';

    public const GROUP_ADMIN_READ_SINGLE = 'admin:module_image:read:single';

    public const GROUP_ADMIN_WRITE = 'admin:module_image:write';

    public const GROUP_ADMIN_WRITE_FILE = 'admin:module_image:write_file';

    public const GROUP_ADMIN_WRITE_UPDATE = 'admin:module_image:write_update';

    public const GROUP_FRONT_READ = 'front:module_image:read';

    public const GROUP_FRONT_READ_SINGLE = 'front:module_image:read:single';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_ADMIN_WRITE_FILE, self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public Module $module;

    #[Groups([self::GROUP_ADMIN_WRITE_FILE])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'format' => 'binary',
        ]
    )]
    #[Assert\Image(
        mimeTypes: [
            'image/bmp',
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/vnd.wap.wbmp',
            'image/webp',
            'image/xbm',
        ],
    )]
    public UploadedFile $fileToUpload;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public I18nCollection $i18ns;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public string $file;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $fileUrl = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): self
    {
        $this->module = $module;

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
        return new ModuleImageTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleImageI18n::class;
    }

    public static function getItemType(): string
    {
        return 'module';
    }

    public static function getFileType(): string
    {
        return 'image';
    }

    public function getItemId(): string
    {
        return $this->getModule()->getId();
    }
}
