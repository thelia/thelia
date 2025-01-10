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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\ProductQuery;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_products'
        ),
        new Get(
            uriTemplate: '/admin/order_products/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_products/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/order_products/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/order_products/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/order_products',
            extraProperties: ['usesForCustomer' => ['order']],
        ),
        new Get(
            uriTemplate: '/front/account/order_products/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'object.order.customer.getId() == user.getId()'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'productSaleElementsId' => 'exact',
        'productSaleElementsRef' => 'exact',
        'productRef' => 'exact',
        'order.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'order_product.order_id',
        ],
    ]
)]
class OrderProduct implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_product:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_product:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_product:write';

    public const GROUP_FRONT_READ = 'front:order_product:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:order_product:read:single';

    #[Groups([self::GROUP_ADMIN_READ,
        Order::GROUP_ADMIN_READ,
        Order::GROUP_FRONT_READ_SINGLE,
        self::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Relation(targetResource: Order::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ])]
    public Order $order;
    private $orderId;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
    ])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public string $productRef;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public string $productSaleElementsRef;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $productSaleElementsId;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $title;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $chapo;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $description;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $postscriptum;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
    ])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public int $quantity;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
    ])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public float $price;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
    ])]
    public ?float $promoPrice;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ])]
    public ?float $unitTaxedPrice;

    #[Groups([self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    #[Type(type: 'bool', groups: [Order::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [Order::GROUP_ADMIN_WRITE])]
    public bool $wasNew;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    #[Type(type: 'bool', groups: [Order::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [Order::GROUP_ADMIN_WRITE])]
    public bool $wasInPromo;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $weight;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $eanCode;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $taxRuleTitle;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Order::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $taxRuleDescription;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Order::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?int $parent;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Order::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    #[Type(type: 'bool', groups: [Order::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [Order::GROUP_ADMIN_WRITE])]
    public bool $virtual;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?bool $virtualDocument;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt;

    #[Relation(targetResource: OrderProductTax::class)]
    #[Groups([
        self::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
    ])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public array $orderProductTaxes;

    public function __construct()
    {
        $this->orderProductTaxes = [];
    }

    #[Groups([
        Order::GROUP_FRONT_READ_SINGLE,
        self::GROUP_FRONT_READ,
    ])]
    public function getProductId(): ?int
    {
        if(null === $this->getProductRef()) {
            return null;
        }
        return ProductQuery::create()->findOneByRef($this->getProductRef())?->getId();
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

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getProductRef(): string
    {
        return $this->productRef;
    }

    public function setProductRef(string $productRef): self
    {
        $this->productRef = $productRef;

        return $this;
    }

    public function getProductSaleElementsRef(): string
    {
        return $this->productSaleElementsRef;
    }

    public function setProductSaleElementsRef(string $productSaleElementsRef): self
    {
        $this->productSaleElementsRef = $productSaleElementsRef;

        return $this;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function setProductSaleElementsId(?int $productSaleElementsId): self
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): self
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): self
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPrice(): float
    {
        return round($this->price, 2);
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPromoPrice(): ?float
    {
        return round($this->promoPrice, 2);
    }

    public function setPromoPrice(?float $promoPrice): self
    {
        $this->promoPrice = $promoPrice;

        return $this;
    }

    public function getUnitTaxedPrice(): ?float
    {
        return round($this->unitTaxedPrice, 2);
    }

    public function isWasNew(): bool
    {
        return $this->wasNew;
    }

    public function setWasNew(bool $wasNew): self
    {
        $this->wasNew = $wasNew;

        return $this;
    }

    public function isWasInPromo(): bool
    {
        return $this->wasInPromo;
    }

    public function setWasInPromo(bool $wasInPromo): self
    {
        $this->wasInPromo = $wasInPromo;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getEanCode(): ?string
    {
        return $this->eanCode;
    }

    public function setEanCode(?string $eanCode): self
    {
        $this->eanCode = $eanCode;

        return $this;
    }

    public function getTaxRuleTitle(): ?string
    {
        return $this->taxRuleTitle;
    }

    public function setTaxRuleTitle(?string $taxRuleTitle): self
    {
        $this->taxRuleTitle = $taxRuleTitle;

        return $this;
    }

    public function getTaxRuleDescription(): ?string
    {
        return $this->taxRuleDescription;
    }

    public function setTaxRuleDescription(?string $taxRuleDescription): self
    {
        $this->taxRuleDescription = $taxRuleDescription;

        return $this;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(?int $parent): self
    {
        $this->parent = $parent;

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

    public function getVirtualDocument(): ?bool
    {
        return $this->virtualDocument;
    }

    public function setVirtualDocument(?bool $virtualDocument): self
    {
        $this->virtualDocument = $virtualDocument;

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

    public function getOrderProductTaxes(): array
    {
        return $this->orderProductTaxes;
    }

    public function setOrderProductTaxes(array $orderProductTaxes): self
    {
        $this->orderProductTaxes = $orderProductTaxes;

        return $this;
    }

    public function afterModelToResource(array $context): void
    {
        if (isset($context['operation'])) {
            if ($context['operation'] instanceof Get || $context['operation'] instanceof GetCollection) {
                // unitTaxedPrice
                $totalTax = 0;
                $totalPromoTax = 0;
                if (!empty($this->orderProductTaxes)) {
                    /** @var OrderProductTax $orderProductTax */
                    foreach ($this->orderProductTaxes as $orderProductTax) {
                        /** @var \Thelia\Model\OrderProductTax $orderProductTax */
                        $propelOrderProductTax = $orderProductTax->getPropelModel();
                        if (!$this->getPropelModel()->getWasInPromo()) {
                            $totalTax += (float) $propelOrderProductTax->getAmount();
                        }
                        if ($this->getPropelModel()->getWasInPromo()) {
                            $totalPromoTax += (float) $propelOrderProductTax->getPromoAmount();
                        }
                    }
                    if (!$this->getPropelModel()->getWasInPromo()) {
                        $this->unitTaxedPrice = $this->getPropelModel()->getPrice() + $totalTax;
                    }
                    if ($this->getPropelModel()->getWasInPromo()) {
                        $this->unitTaxedPrice = $this->getPropelModel()->getPrice() + $totalPromoTax;
                    }
                }
            }
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderProductTableMap();
    }
}
