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
            uriTemplate: '/admin/order_product'
        ),
        new GetCollection(
            uriTemplate: '/admin/order_product'
        ),
        new Get(
            uriTemplate: '/admin/order_product/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_product/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_product/{id}'
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

    public function getUnitTaxedPrice(): ?float
    {
        return $this->unitTaxedPrice;
    }

    public function setUnitTaxedPrice(?float $unitTaxedPrice): void
    {
        $this->unitTaxedPrice = $unitTaxedPrice;
    }

    public function getOrderProductTaxes(): Collection
    {
        return $this->orderProductTaxes;
    }

    public function setOrderProductTaxes(Collection $orderProductTaxes): void
    {
        $this->orderProductTaxes = $orderProductTaxes;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): void
    {
        $this->chapo = $chapo;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): void
    {
        $this->postscriptum = $postscriptum;
    }

    public function getWasNew(): bool
    {
        return $this->wasNew;
    }

    public function setWasNew(bool $wasNew): void
    {
        $this->wasNew = $wasNew;
    }

    public function getWasInPromo(): bool
    {
        return $this->wasInPromo;
    }

    public function setWasInPromo(bool $wasInPromo): void
    {
        $this->wasInPromo = $wasInPromo;
    }

    public function getEanCode(): ?string
    {
        return $this->eanCode;
    }

    public function setEanCode(?string $eanCode): void
    {
        $this->eanCode = $eanCode;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(?int $parent): void
    {
        $this->parent = $parent;
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function setVirtual(bool $virtual): void
    {
        $this->virtual = $virtual;
    }

    public function getVirtualDocument(): ?bool
    {
        return $this->virtualDocument;
    }

    public function setVirtualDocument(?bool $virtualDocument): void
    {
        $this->virtualDocument = $virtualDocument;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function setProductSaleElementsId(?int $productSaleElementsId): void
    {
        $this->productSaleElementsId = $productSaleElementsId;
    }

    public function getTaxRuleTitle(): ?string
    {
        return $this->taxRuleTitle;
    }


    public function setTaxRuleTitle(?string $taxRuleTitle): void
    {
        $this->taxRuleTitle = $taxRuleTitle;
    }

    public function getTaxRuleDescription(): ?string
    {
        return $this->taxRuleDescription;
    }


    public function setTaxRuleDescription(?string $taxRuleDescription): void
    {
        $this->taxRuleDescription = $taxRuleDescription;
    }

    public function getProductRef(): string
    {
        return $this->productRef;
    }

    public function setProductRef(string $productRef): void
    {
        $this->productRef = $productRef;
    }

    public function getProductSaleElementsRef(): string
    {
        return $this->productSaleElementsRef;
    }

    public function setProductSaleElementsRef(string $productSaleElementsRef): void
    {
        $this->productSaleElementsRef = $productSaleElementsRef;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getPromoPrice(): ?int
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(?int $promoPrice): void
    {
        $this->promoPrice = $promoPrice;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
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
                    $this->setUnitTaxedPrice($this->getPropelModel()->getPrice() + $totalTax);
                }
                if ($this->getPropelModel()->getWasInPromo()) {
                    $this->setUnitTaxedPrice($this->getPropelModel()->getPrice()  + $totalPromoTax);
                }
            }
            //
        }
    }


    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderProduct::class;
    }
}
