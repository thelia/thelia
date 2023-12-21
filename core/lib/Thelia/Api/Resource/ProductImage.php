<?php

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
use Thelia\Model\Map\ProductImageTableMap;


#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_images',
            inputFormats: ['multipart' => ['multipart/form-data']],
            controller: PostItemFileController::class,
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_WRITE, self::GROUP_WRITE_FILE]],
            deserialize: false
        ),
        new GetCollection(
            uriTemplate: '/admin/product_images'
        ),
        new Get(
            uriTemplate: '/admin/product_images/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/product_images/{id}/file',
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
            uriTemplate: '/admin/product_images/{id}',
            denormalizationContext: ['groups' => [self::GROUP_WRITE,self::GROUP_WRITE_UPDATE]],
        ),
        new Delete(
            uriTemplate: '/admin/product_images/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class ProductImage extends AbstractTranslatableResource implements ItemFileResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_READ = 'product_image:read';
    public const GROUP_READ_SINGLE = 'product_image:read:single';
    public const GROUP_WRITE = 'product_image:write';

    public const GROUP_WRITE_FILE = 'product_image:write_file';
    public const GROUP_WRITE_UPDATE = 'product_image:write_update';


    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_WRITE_FILE,self::GROUP_READ])]
    public Product $product;

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

    public function setId(?int $id): ProductImage
    {
        $this->id = $id;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): ProductImage
    {
        $this->product = $product;
        return $this;
    }

    public function getFileToUpload(): UploadedFile
    {
        return $this->fileToUpload;
    }

    public function setFileToUpload(UploadedFile $fileToUpload): ProductImage
    {
        $this->fileToUpload = $fileToUpload;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): ProductImage
    {
        $this->visible = $visible;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): ProductImage
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): ProductImage
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): ProductImage
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): ProductImage
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }



    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): ProductImage
    {
        $this->file = $file;
        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ProductImageTableMap();
    }

    public static function getItemType(): string
    {
        return "product";
    }

    public static function getFileType(): string
    {
        return "image";
    }

    public function getItemId(): string
    {
        return $this->getProduct()->getId();
    }

    public static function getI18nResourceClass(): string
    {
        return ProductImageI18n::class;
    }
}