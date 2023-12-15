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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Controller\Admin\BinaryFileController;
use Thelia\Api\Controller\Admin\PostItemFileController;
use Thelia\Model\Map\FolderDocumentTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/folder_documents',
            inputFormats: ['multipart' => ['multipart/form-data']],
            controller: PostItemFileController::class,
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_WRITE, self::GROUP_WRITE_FILE]],
            deserialize: false
        ),
        new GetCollection(
            uriTemplate: '/admin/folder_documents'
        ),
        new Get(
            uriTemplate: '/admin/folder_documents/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/folder_documents/{id}/file',
            controller: BinaryFileController::class,
            openapiContext: [
                'responses' => [
                    '200' => [
                        'description' => 'The binary file'
                    ]
                ]
            ]
        ),
        new Put(
            uriTemplate: '/admin/folder_documents/{id}',
            denormalizationContext: ['groups' => [self::GROUP_WRITE,self::GROUP_WRITE_UPDATE]],
        ),
        new Delete(
            uriTemplate: '/admin/folder_documents/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class FolderDocument extends AbstractTranslatableResource implements ItemFileResourceInterface
{
    public const GROUP_READ = 'folder_document:read';
    public const GROUP_READ_SINGLE = 'folder_document:read:single';
    public const GROUP_WRITE = 'folder_document:write';

    public const GROUP_WRITE_FILE = 'folder_document:write_file';
    public const GROUP_WRITE_UPDATE = 'folder_document:write_update';


    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Folder::class)]
    #[Groups([self::GROUP_WRITE_FILE, self::GROUP_READ])]
    public Folder $folder;

    #[Groups([self::GROUP_WRITE_FILE])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'format' => 'binary'
        ]
    )]
    public UploadedFile $fileToUpload;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE_UPDATE])]
    public ?int $position;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public I18nCollection $i18ns;

    #[Groups([self::GROUP_READ_SINGLE])]
    public string $file;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?string $fileUrl;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): FolderDocument
    {
        $this->id = $id;
        return $this;
    }


    public function getFileToUpload(): UploadedFile
    {
        return $this->fileToUpload;
    }

    public function setFileToUpload(UploadedFile $fileToUpload): FolderDocument
    {
        $this->fileToUpload = $fileToUpload;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): FolderDocument
    {
        $this->visible = $visible;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): FolderDocument
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): FolderDocument
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): FolderDocument
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): FolderDocument
    {
        $this->file = $file;
        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): FolderDocument
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    public function setFolder(Folder $folder): FolderDocument
    {
        $this->folder = $folder;
        return $this;
    }


    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new FolderDocumentTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return FolderDocumentI18n::class;
    }

    public static function getItemType(): string
    {
        return "folder";
    }

    public static function getFileType(): string
    {
        return "document";
    }

    public function getItemId(): string
    {
        return $this->getFolder()->getId();
    }
}
