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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/products'
        ),
        new GetCollection(
            uriTemplate: '/admin/products'
        ),
        new Get(
            uriTemplate: '/admin/products/{id}'
        ),
        new Put(
            uriTemplate: '/admin/products/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/products/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Product extends AbstractPropelResource implements TranslatableResourceInterface
{
    public const GROUP_READ = 'product:read';
    public const GROUP_READ_SINGLE = 'product:read:single';
    public const GROUP_WRITE = 'product:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_WRITE])]
    public bool $virtual;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public array $productCategories;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public array $i18ns;

    public function __construct()
    {
        $this->productCategories = [];
        $this->i18ns = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;

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

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function setVirtual(bool $virtual): self
    {
        $this->virtual = $virtual;

        return $this;
    }

    public function setI18ns(array $i18ns): self
    {
        $this->i18ns = $i18ns;

        return $this;
    }

    /**
     * @return ProductI18n[]
     */
    public function getI18ns(): array
    {
        return $this->i18ns;
    }

    public function addI18n(ProductI18n $i18n): self
    {
        $this->i18ns[] = $i18n;

        return $this;
    }

    public function removeI18n(ProductI18N $i18n): self
    {
        return $this;
    }
//
//    /**
//     * @return ProductCategory[]
//     */
//    public function getProductCategories(): array
//    {
//        return $this->productCategories;
//    }
//
//    public function addProductCategory(ProductCategory $productCategory): self
//    {
//
//        return $this;
//    }
//
//    public function removeProductCategory(ProductCategory $productCategory): self
//    {
//
//        return $this;
//    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Product::class;
    }

    public static function getTranslatableFields(): array
    {
        return [
            'title',
            'chapo',
        ];
    }

    public static function getI18nResourceClass(): string
    {
        return ProductI18n::class;
    }
}
