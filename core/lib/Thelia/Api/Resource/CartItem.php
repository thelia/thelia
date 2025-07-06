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


use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\CartItemTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/cart_items'
        ),
        new GetCollection(
            uriTemplate: '/admin/cart_items'
        ),
        new Get(
            uriTemplate: '/admin/cart_items/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/cart_items/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/cart_items/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/cart_items/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/cart_items'
        ),
        new GetCollection(
            uriTemplate: '/front/cart_items'
        ),
        new Get(
            uriTemplate: '/front/cart_items/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/front/cart_items/{id}'
        ),
        new Delete(
            uriTemplate: '/front/cart_items/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]]
)]
class CartItem implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:cart_item:read';

    public const GROUP_ADMIN_READ_SINGLE = 'admin:cart_item:read:single';

    public const GROUP_ADMIN_WRITE = 'admin:cart_item:write';

    public const GROUP_FRONT_READ = 'front:cart_item:read';

    public const GROUP_FRONT_READ_SINGLE = 'front:cart_item:read:single';

    public const GROUP_FRONT_WRITE = 'front:cart_item:write';

    #[Groups([self::GROUP_ADMIN_READ, Cart::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, Cart::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotNull(groups: [Order::GROUP_ADMIN_WRITE])]
    public ?int $quantity = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public Product $product;

    #[Relation(targetResource: Cart::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public Cart $cart;

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotNull(groups: [Order::GROUP_ADMIN_WRITE])]
    public ProductSaleElements $productSaleElements;

    #[Groups([self::GROUP_ADMIN_READ, Cart::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ])]
    public ?float $price = null;

    #[Groups([self::GROUP_ADMIN_READ, Cart::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ])]
    public ?float $promoPrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?DateTime $priceEndOfLife = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $promo = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?DateTime $updatedAt = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedTotalPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedTotalPromoPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedTotalTaxedPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedTotalPromoTaxedPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedRealPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedRealTaxedPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedRealTotalPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $calculatedRealTotalTaxedPrice = null;

    #[Groups([Cart::GROUP_FRONT_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?bool $isPromo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

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

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): self
    {
        $this->cart = $cart;

        return $this;
    }

    public function getProductSaleElements(): ProductSaleElements
    {
        return $this->productSaleElements;
    }

    public function setProductSaleElements(ProductSaleElements $productSaleElements): self
    {
        $this->productSaleElements = $productSaleElements;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPromoPrice(): ?float
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(?float $promo_price): self
    {
        $this->promoPrice = $promo_price;

        return $this;
    }

    public function getPriceEndOfLife(): ?DateTime
    {
        return $this->priceEndOfLife;
    }

    public function setPriceEndOfLife(?DateTime $priceEndOfLife): self
    {
        $this->priceEndOfLife = $priceEndOfLife;

        return $this;
    }

    public function getPromo(): ?int
    {
        return $this->promo;
    }

    public function setPromo(?int $promo): self
    {
        $this->promo = $promo;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCalculatedRealPrice(): ?float
    {
        return $this->calculatedRealPrice;
    }

    public function setCalculatedRealPrice(?float $calculatedRealPrice): self
    {
        $this->calculatedRealPrice = $calculatedRealPrice;

        return $this;
    }

    public function getCalculatedRealTaxedPrice(): ?float
    {
        return $this->calculatedRealTaxedPrice;
    }

    public function setCalculatedRealTaxedPrice(?float $calculatedRealTaxedPrice): self
    {
        $this->calculatedRealTaxedPrice = $calculatedRealTaxedPrice;

        return $this;
    }

    public function getCalculatedRealTotalPrice(): ?float
    {
        return $this->calculatedRealTotalPrice;
    }

    public function setCalculatedRealTotalPrice(?float $calculatedRealTotalPrice): self
    {
        $this->calculatedRealTotalPrice = $calculatedRealTotalPrice;

        return $this;
    }

    public function getCalculatedRealTotalTaxedPrice(): ?float
    {
        return $this->calculatedRealTotalTaxedPrice;
    }

    public function setCalculatedRealTotalTaxedPrice(?float $calculatedRealTotalTaxedPrice): self
    {
        $this->calculatedRealTotalTaxedPrice = $calculatedRealTotalTaxedPrice;

        return $this;
    }

    public function getCalculatedTotalPrice(): ?float
    {
        return $this->calculatedTotalPrice;
    }

    public function setCalculatedTotalPrice(?float $calculatedTotalPrice): self
    {
        $this->calculatedTotalPrice = $calculatedTotalPrice;

        return $this;
    }

    public function getCalculatedTotalPromoPrice(): ?float
    {
        return $this->calculatedTotalPromoPrice;
    }

    public function setCalculatedTotalPromoPrice(?float $calculatedTotalPromoPrice): self
    {
        $this->calculatedTotalPromoPrice = $calculatedTotalPromoPrice;

        return $this;
    }

    public function getCalculatedTotalPromoTaxedPrice(): ?float
    {
        return $this->calculatedTotalPromoTaxedPrice;
    }

    public function setCalculatedTotalPromoTaxedPrice(?float $calculatedTotalPromoTaxedPrice): self
    {
        $this->calculatedTotalPromoTaxedPrice = $calculatedTotalPromoTaxedPrice;

        return $this;
    }

    public function getCalculatedTotalTaxedPrice(): ?float
    {
        return $this->calculatedTotalTaxedPrice;
    }

    public function setCalculatedTotalTaxedPrice(?float $calculatedTotalTaxedPrice): self
    {
        $this->calculatedTotalTaxedPrice = $calculatedTotalTaxedPrice;

        return $this;
    }

    public function getIsPromo(): ?bool
    {
        return $this->isPromo;
    }

    public function setIsPromo(?bool $isPromo): self
    {
        $this->isPromo = $isPromo;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CartItemTableMap();
    }
}
