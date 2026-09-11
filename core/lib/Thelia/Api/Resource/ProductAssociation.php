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
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Service\API\PublicUrlPreloader;
use Thelia\Api\State\Processor\ProductAssociationProcessor;
use Thelia\Model\Map\AccessoryTableMap;

/**
 * A relation between two products, told apart from the others by its type.
 *
 * `product` is the sheet the relation is read from and `associatedProduct` the one
 * it points at. Both are the `product` table, so the Propel relations are named
 * after their columns rather than after the table, hence the relation aliases:
 * without them the eager loading extension finds no `useProductQuery()` on
 * AccessoryQuery and skips the join in silence.
 *
 * Filtering on `product.id` and `type.code` and ordering on `position` is what
 * lets a theme read one block of a product sheet in a single call.
 *
 * Writes go through ProductAssociationProcessor, which calls the product facade:
 * the events fire, the reciprocal row of a reciprocal type is written, and a
 * product related to itself is refused. Persisting straight to Propel would do
 * none of it.
 *
 * `position` is read-only here. Reordering a block already has its own event and
 * its own back-office screen, and nothing asks the API for it yet.
 *
 * Both products come out whole rather than as a bare IRI, carrying what a card
 * of a block shows: the wording, the rewritten url and the sale elements, the
 * default one told apart by `isDefault`. A theme therefore renders a block out
 * of this one read. Prices are not part of it: they are not serialized on the
 * sale element anywhere in this API, and a front resolves them on its own.
 *
 * The collection is paginated on the defaults the core sets for the whole API
 * (`Config/Resources/packages/api_platform.php`): thirty rows a page, `page` to
 * walk them, and `itemsPerPage` for the caller to ask for another size, capped
 * at a hundred. A block of a product sheet holds far fewer than thirty, so a
 * theme reads one in a single call without naming any of them; an integration
 * walking every relation of a catalogue is the one that pages.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_associations',
            processor: ProductAssociationProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/product_associations',
        ),
        new Get(
            uriTemplate: '/admin/product_associations/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Delete(
            uriTemplate: '/admin/product_associations/{id}',
            processor: ProductAssociationProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/product_associations',
        ),
        new Get(
            uriTemplate: '/front/product_associations/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'product.id',
        'associatedProduct.id',
        'type.code',
    ],
)]
class ProductAssociation implements PropelResourceInterface, CollectionPreloadableInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_association:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:product_association:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:product_association:write';
    public const GROUP_FRONT_READ = 'front:product_association:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:product_association:read:single';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class, relationAlias: 'ProductRelatedByProductId', preload: true)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public Product $product;

    #[Relation(targetResource: Product::class, relationAlias: 'ProductRelatedByAccessory', preload: true)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public Product $associatedProduct;

    #[Relation(targetResource: ProductAssociationType::class, relationAlias: 'ProductAssociationType')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ProductAssociationType $type;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

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

    public function getAssociatedProduct(): Product
    {
        return $this->associatedProduct;
    }

    public function setAssociatedProduct(Product $associatedProduct): self
    {
        $this->associatedProduct = $associatedProduct;

        return $this;
    }

    public function getType(): ProductAssociationType
    {
        return $this->type;
    }

    public function setType(ProductAssociationType $type): self
    {
        $this->type = $type;

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

    /**
     * Resolves the rewritten url of the products of a whole page at once.
     *
     * `publicUrl` is in the read groups of the products this relation carries, so
     * the serializer asks each of them for it, and each answer is a rewriting_url
     * read of its own — one per card of the block. {@see PublicUrlPreloader} does
     * that for the members of a page, but these products are held by the members
     * rather than being them, so nothing covered them.
     */
    public static function preloadCollection(array $resources): void
    {
        $productsByLocale = [];

        foreach ($resources as $resource) {
            if (!$resource instanceof self) {
                continue;
            }

            foreach ([$resource->product ?? null, $resource->associatedProduct ?? null] as $product) {
                if (!$product instanceof Product || null === $product->getId()) {
                    continue;
                }

                $locale = $product->getPropelModel()?->getLocale() ?: $product->getDefaultLocale();
                $productsByLocale[$locale][$product->getId()] = $product;
            }
        }

        $preloader = new PublicUrlPreloader();

        foreach ($productsByLocale as $locale => $products) {
            $preloader->preload($products, (string) $locale);
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new AccessoryTableMap();
    }
}
