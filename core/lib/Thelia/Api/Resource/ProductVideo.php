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
use ApiPlatform\OpenApi\Model\Response;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\NotInFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Controller\Admin\BinaryFileController;
use Thelia\Api\Controller\Admin\PostItemFileController;
use Thelia\Api\State\Processor\ProductVideoProcessor;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Domain\Media\Video\VideoProviderResolver;
use Thelia\Model\Map\ProductVideoTableMap;

/**
 * A video on a product sheet.
 *
 * A merchant attaches one by pasting the address of a platform page, or by
 * uploading a file the shop stores itself. The address is a write-only field:
 * what the shop keeps of it is the platform and the identifier, and `embedUrl`
 * is built back from those two. A crafted address therefore never reaches the
 * frame the front office renders.
 *
 * `fileUrl` addresses the stored file of a hosted video, and stays null for a
 * platform video; `embedUrl` is the other way round. Both are served in the
 * collection as well as on the single read, because a gallery reads the
 * collection and would otherwise ask for every video one by one.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_videos',
            processor: ProductVideoProcessor::class,
        ),
        new Post(
            uriTemplate: '/admin/product_videos/upload',
            inputFormats: ['multipart' => ['multipart/form-data']],
            controller: PostItemFileController::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE], 'skip_null_values' => false],
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_FILE]],
            deserialize: false,
        ),
        new GetCollection(
            uriTemplate: '/admin/product_videos',
        ),
        new Get(
            uriTemplate: '/admin/product_videos/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE], 'skip_null_values' => false],
        ),
        new Get(
            uriTemplate: '/admin/product_videos/{id}/file',
            controller: BinaryFileController::class,
            openapi: new Operation(
                responses: [
                    '200' => new Response(description: 'The binary file'),
                ],
            ),
        ),
        new Put(
            uriTemplate: '/admin/product_videos/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]],
            processor: ProductVideoProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/product_videos/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]],
            processor: ProductVideoProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/product_videos/{id}',
            processor: ProductVideoProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ], 'skip_null_values' => false],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/product_videos',
        ),
        new Get(
            uriTemplate: '/front/product_videos/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE], 'skip_null_values' => false],
        ),
        new Get(
            uriTemplate: '/front/product_videos/{id}/file',
            controller: BinaryFileController::class,
            openapi: new Operation(
                responses: [
                    '200' => new Response(description: 'The binary file'),
                ],
            ),
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ], 'skip_null_values' => false],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
    ],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'provider',
        'product.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'product_video.product_id',
        ],
    ],
)]
#[ApiFilter(
    filterClass: NotInFilter::class,
    properties: [
        'id',
    ],
)]
class ProductVideo extends AbstractTranslatableResource implements ItemFileResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_video:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:product_video:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:product_video:write';
    public const GROUP_ADMIN_WRITE_FILE = 'admin:product_video:write_file';
    public const GROUP_ADMIN_WRITE_UPDATE = 'admin:product_video:write_update';
    public const GROUP_FRONT_READ = 'front:product_video:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:product_video:read:single';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_FILE])]
    public Product $product;

    /**
     * The address of the platform page, as a merchant pasted it.
     *
     * Write only, and never stored: the resolver turns it into the platform and
     * the identifier below, and refuses anything else with a 422 naming the
     * platforms this shop accepts.
     */
    #[Groups([self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?string $url = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $provider = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $externalId = null;

    #[Relation(targetResource: ProductImage::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?ProductImage $thumbnailImage = null;

    #[Groups([self::GROUP_ADMIN_WRITE_FILE])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'format' => 'binary',
        ],
    )]
    public UploadedFile $fileToUpload;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE])]
    public bool $visible = true;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    #[Assert\Valid]
    public I18nCollection $i18ns;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $file = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
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

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getThumbnailImage(): ?ProductImage
    {
        return $this->thumbnailImage;
    }

    public function setThumbnailImage(?ProductImage $thumbnailImage): self
    {
        $this->thumbnailImage = $thumbnailImage;

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

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): self
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    /**
     * A platform video has no stored file, and the file interface can only say so
     * with an empty name.
     */
    public function getFile(): string
    {
        return (string) $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }

    /**
     * The address of the player frame, rebuilt from the platform and the
     * identifier. Null for a video the shop hosts itself, and null for a video
     * whose platform the shop has since switched off: a gallery that reads null
     * shows nothing, and a shop that dropped a platform from its Content Security
     * Policy is not asked to frame it anyway.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getEmbedUrl(): ?string
    {
        if (null === $this->externalId || '' === $this->externalId) {
            return null;
        }

        $provider = VideoProvider::tryFrom((string) $this->provider);
        if (null === $provider || !\in_array($provider, (new VideoProviderResolver())->enabledProviders(), true)) {
            return null;
        }

        return $provider->embedUrl($this->externalId);
    }

    /**
     * What this shop accepts on a video upload, so that a client can build its
     * form without restating the lists.
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'properties' => [
                'allowedMimeTypes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'allowedExtensions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'forbiddenExtensions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ],
    )]
    public function getUploadConstraints(): array
    {
        return FileUploadConstraints::forFileType(self::getFileType());
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ProductVideoTableMap();
    }

    public static function getItemType(): string
    {
        return 'product';
    }

    public static function getFileType(): string
    {
        return 'video';
    }

    public function getItemId(): string
    {
        return $this->getProduct()->getId();
    }

    public static function getI18nResourceClass(): string
    {
        return ProductVideoI18n::class;
    }
}
