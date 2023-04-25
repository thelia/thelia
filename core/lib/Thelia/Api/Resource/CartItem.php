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
use Thelia\Api\Bridge\Propel\Attribute\Relation;

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
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/cart_items/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/cart_items/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class CartItem extends AbstractPropelResource
{
    public const GROUP_READ = 'cart_item:read';
    public const GROUP_READ_SINGLE = 'cart_item:read:single';
    public const GROUP_WRITE = 'cart_item:write';

    #[Groups([self::GROUP_READ, Cart::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, Cart::GROUP_READ])]
    public ?int $quantity;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_READ])]
    public Product $product;

    #[Relation(targetResource: Cart::class)]
    #[Groups([self::GROUP_READ])]
    public Cart $cart;

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_READ])]
    public ProductSaleElements $productSaleElements;

    #[Groups([self::GROUP_READ, Cart::GROUP_READ])]
    public ?float $price;

    #[Groups([self::GROUP_READ, Cart::GROUP_READ])]
    public ?float $promo_price;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $priceEndOfLife;

    #[Groups([self::GROUP_READ])]
    public ?int $promo;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public I18nCollection $i18ns;

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
        return $this->promo_price;
    }

    public function setPromoPrice(?float $promo_price): self
    {
        $this->promo_price = $promo_price;

        return $this;
    }

    public function getPriceEndOfLife(): ?\DateTime
    {
        return $this->priceEndOfLife;
    }

    public function setPriceEndOfLife(?\DateTime $priceEndOfLife): self
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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\CartItem::class;
    }
}
