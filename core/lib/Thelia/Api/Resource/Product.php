<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    collectionOperations: [
        'get',
        'post'
    ],
    itemOperations: [
        'adminGet' => [
            'method' => 'GET',
            'path' => '/admin/products/{id}'
        ]
    ],
    denormalizationContext: ['groups' => ['product:write']],
    normalizationContext: ['groups' => ['product:read']]
)]
class Product implements PropelResourceInterface, TranslatableResourceInterface
{
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[Groups(['product:read', 'product:write'])]
    private string $ref;

    #[Groups(['product:read', 'product:write'])]
    private bool $visible;

    #[Groups(['product:write'])]
    private bool $virtual;

    #[Groups(['product:read', 'product:write'])]
    private array $productCategories;

    #[Groups(['product:read', 'product:write'])]
    private array $i18ns;

    public function __construct()
    {
        $this->productCategories = [];
        $this->i18ns = [];
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Product
     */
    public function setId(int $id): Product
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getRef(): string
    {
        return $this->ref;
    }

    /**
     * @param string $ref
     * @return Product
     */
    public function setRef(string $ref): Product
    {
        $this->ref = $ref;
        return $this;
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @param bool $visible
     * @return Product
     */
    public function setVisible(bool $visible): Product
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return bool
     */
    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    /**
     * @param bool $virtual
     * @return Product
     */
    public function setVirtual(bool $virtual): Product
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
            'chapo'
        ];
    }

    public static function getI18nResourceClass(): string
    {
        return ProductI18n::class;
    }
}
