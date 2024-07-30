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
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/products'
        ),
        new GetCollection(
            uriTemplate: '/admin/products'
        ),
        new Get(
            uriTemplate: '/admin/products/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/products/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/products/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/products'
        ),
        new Get(
            uriTemplate: '/front/products/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'ref',
        'productCategories.category.id',
        'title' => 'word_start',
    ]
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
        'virtual',
        'productCategories.defaultCategory',
    ]
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'ref',
        'productCategories.position',
    ]
)]
class Product extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:product:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:product:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:product:write';

    public const GROUP_FRONT_READ = 'front:product:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:product:read:single';

    #[Groups(
        [
            self::GROUP_ADMIN_READ,
            self::GROUP_FRONT_READ,
            ProductCategory::GROUP_ADMIN_READ,
            OrderProduct::GROUP_ADMIN_READ,
            ProductAssociatedContent::GROUP_ADMIN_READ,
            FeatureProduct::GROUP_ADMIN_READ_SINGLE,
            ProductSaleElements::GROUP_ADMIN_READ,
            ProductSaleElements::GROUP_ADMIN_WRITE,
            ProductImage::GROUP_ADMIN_READ_SINGLE,
            ProductDocument::GROUP_ADMIN_READ_SINGLE,
        ]
    )]
    public ?int $id = null;

    #[Relation(targetResource: TaxRule::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    public TaxRule $taxRule;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    public string $ref;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?int $position;

    #[Relation(targetResource: Template::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public ?Template $template;

    #[Relation(targetResource: Brand::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?Brand $brand;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public bool $virtual = false;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Relation(targetResource: ProductCategory::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ])]
    public array $productCategories;

    #[Column(propelFieldName: 'productSaleElementss')]
    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ])]
    public array $productSaleElements;

    #[Relation(targetResource: FeatureProduct::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public array $featureProducts;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public I18nCollection $i18ns;

    public function __construct()
    {
        $this->productCategories = [];
        $this->productSaleElements = [];
        $this->featureProducts = [];
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTaxRule(): ?TaxRule
    {
        return $this->taxRule;
    }

    public function setTaxRule(?TaxRule $taxRule): self
    {
        $this->taxRule = $taxRule;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

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

    public function getProductCategories(): array
    {
        return $this->productCategories;
    }

    public function setProductCategories(array $productCategories): self
    {
        $this->productCategories = $productCategories;

        return $this;
    }

    public function getProductSaleElements(): array
    {
        return $this->productSaleElements;
    }

    public function setProductSaleElements(array $productSaleElements): self
    {
        $this->productSaleElements = $productSaleElements;

        return $this;
    }

    public function getFeatureProducts(): array
    {
        return $this->featureProducts;
    }

    public function setFeatureProducts(array $featureProducts): self
    {
        $this->featureProducts = $featureProducts;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ProductTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ProductI18n::class;
    }

    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function checkDuplicateRef(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();
        $product = ProductQuery::create()->filterByRef($resource->ref)->findOne();

        if ($product && $product->getId() !== $this->getId()) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'A product with reference %ref already exists. Please choose another reference.',
                    ['%ref' => $resource->ref], null, 'en_US'
                )
            );
        }
    }

    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function checkTitleAndLocaleNotBlank(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();
        $titleAndLocaleCount = 0;
        /** @var I18nCollection $i18nData */
        $i18nData = $resource->getI18ns();
        foreach ($i18nData->i18ns as $i18n) {
            if ($i18n->getTitle() !== null && !empty($i18n->getTitle())) {
                ++$titleAndLocaleCount;
            }
        }
        if ($titleAndLocaleCount === 0) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'The title and locale must be defined at least once.',
                    [], null, 'en_US'
                )
            );
        }
    }

    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function checkDefaultCategoryNotBlank(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();
        $defaultCategory = [];
        /** @var ProductCategory $productCategory */
        foreach ($resource->getProductCategories() as $productCategory) {
            $defaultCategory[] = $productCategory->getDefaultCategory();
        }
        if (!\in_array(true, $defaultCategory)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'There is no default category defined.',
                    [], null, 'en_US'
                )
            );
        }
    }
}
