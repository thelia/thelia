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
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use function PHPUnit\Framework\isInstanceOf;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_products'
        ),
        new Get(
            uriTemplate: '/admin/order_products/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_products/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_products/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class OrderProduct extends AbstractPropelResource
{
    public const GROUP_READ = 'order_product:read';
    public const GROUP_READ_SINGLE = 'order_product:read:single';
    public const GROUP_WRITE = 'order_product:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Order::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public Order $order;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public string $productRef;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public string $productSaleElementsRef;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public ?int $productSaleElementsId;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public ?string $title;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $chapo;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $description;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $postscriptum;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public int $quantity;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public float $price;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public ?float $promoPrice;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public ?float $unitTaxedPrice;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public bool $wasNew;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public bool $wasInPromo;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public ?float $weight;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public ?string $eanCode;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $taxRuleTitle;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $taxRuleDescription;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $parent;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $virtual;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $virtualDocument;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Relation(targetResource: OrderProductTax::class)]
    #[Groups([self::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public Collection $orderProductTaxes;

    public function __construct()
    {
        $this->orderProductTaxes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): OrderProduct
    {
        $this->id = $id;
        return $this;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): OrderProduct
    {
        $this->order = $order;
        return $this;
    }

    public function getProductRef(): string
    {
        return $this->productRef;
    }

    public function setProductRef(string $productRef): OrderProduct
    {
        $this->productRef = $productRef;
        return $this;
    }

    public function getProductSaleElementsRef(): string
    {
        return $this->productSaleElementsRef;
    }

    public function setProductSaleElementsRef(string $productSaleElementsRef): OrderProduct
    {
        $this->productSaleElementsRef = $productSaleElementsRef;
        return $this;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function setProductSaleElementsId(?int $productSaleElementsId): OrderProduct
    {
        $this->productSaleElementsId = $productSaleElementsId;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): OrderProduct
    {
        $this->title = $title;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): OrderProduct
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): OrderProduct
    {
        $this->description = $description;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): OrderProduct
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): OrderProduct
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): OrderProduct
    {
        $this->price = $price;
        return $this;
    }

    public function getPromoPrice(): ?float
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(?float $promoPrice): OrderProduct
    {
        $this->promoPrice = $promoPrice;
        return $this;
    }

    public function getUnitTaxedPrice(): ?float
    {
        return $this->unitTaxedPrice;
    }

    public function isWasNew(): bool
    {
        return $this->wasNew;
    }

    public function setWasNew(bool $wasNew): OrderProduct
    {
        $this->wasNew = $wasNew;
        return $this;
    }

    public function isWasInPromo(): bool
    {
        return $this->wasInPromo;
    }

    public function setWasInPromo(bool $wasInPromo): OrderProduct
    {
        $this->wasInPromo = $wasInPromo;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): OrderProduct
    {
        $this->weight = $weight;
        return $this;
    }

    public function getEanCode(): ?string
    {
        return $this->eanCode;
    }

    public function setEanCode(?string $eanCode): OrderProduct
    {
        $this->eanCode = $eanCode;
        return $this;
    }

    public function getTaxRuleTitle(): ?string
    {
        return $this->taxRuleTitle;
    }

    public function setTaxRuleTitle(?string $taxRuleTitle): OrderProduct
    {
        $this->taxRuleTitle = $taxRuleTitle;
        return $this;
    }

    public function getTaxRuleDescription(): ?string
    {
        return $this->taxRuleDescription;
    }

    public function setTaxRuleDescription(?string $taxRuleDescription): OrderProduct
    {
        $this->taxRuleDescription = $taxRuleDescription;
        return $this;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(?int $parent): OrderProduct
    {
        $this->parent = $parent;
        return $this;
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function setVirtual(bool $virtual): OrderProduct
    {
        $this->virtual = $virtual;
        return $this;
    }

    public function getVirtualDocument(): ?bool
    {
        return $this->virtualDocument;
    }

    public function setVirtualDocument(?bool $virtualDocument): OrderProduct
    {
        $this->virtualDocument = $virtualDocument;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): OrderProduct
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): OrderProduct
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getOrderProductTaxes(): Collection
    {
        return $this->orderProductTaxes;
    }

    public function setOrderProductTaxes(Collection $orderProductTaxes): OrderProduct
    {
        $this->orderProductTaxes = $orderProductTaxes;
        return $this;
    }

    public function afterModelToResource(array $context): void
    {
        if ($context['operation'] instanceof \ApiPlatform\Metadata\Get || $context['operation'] instanceof \ApiPlatform\Metadata\GetCollection){
            //unitTaxedPrice
            $totalTax = 0;
            $totalPromoTax = 0;
            if(!empty($this->orderProductTaxes->getData())){
                /** @var OrderProductTax $orderProductTax */
                foreach ($this->orderProductTaxes->getData() as $orderProductTax) {

                    /** @var \Thelia\Model\OrderProductTax $orderProductTax */
                    $propelOrderProductTax = $orderProductTax->getPropelModel();
                    if (!$this->getPropelModel()->getWasInPromo()) {
                        $totalTax += (float)$propelOrderProductTax->getAmount();
                    }
                    if ($this->getPropelModel()->getWasInPromo()) {
                        $totalPromoTax += (float)$propelOrderProductTax->getPromoAmount();
                    }
                }
                if (!$this->getPropelModel()->getWasInPromo()) {
                    $this->unitTaxedPrice = $this->getPropelModel()->getPrice() + $totalTax;
                }
                if ($this->getPropelModel()->getWasInPromo()) {
                    $this->unitTaxedPrice = $this->getPropelModel()->getPrice()  + $totalPromoTax;
                }
            }
        }
    }


    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderProduct::class;
    }
}
