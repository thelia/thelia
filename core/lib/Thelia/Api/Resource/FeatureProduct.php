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
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/feature_products/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/feature_products/{product}/features/{feature}/feature_avs/{featureAv}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class FeatureProduct extends AbstractPropelResource
{
    public const GROUP_READ = 'feature_product:read';
    public const GROUP_READ_SINGLE = 'feature_product:read:single';
    public const GROUP_WRITE = 'feature_product:write';

    #[Groups([self::GROUP_READ, Product::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public Product $product;

    #[Relation(targetResource: Feature::class)]
    #[Groups([self::GROUP_READ_SINGLE, Product::GROUP_READ_SINGLE])]
    public Feature $feature;

    #[Relation(targetResource: FeatureAv::class)]
    #[Groups([self::GROUP_READ_SINGLE, Product::GROUP_READ_SINGLE])]
    public FeatureAv $featureAv;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    public string $free_text_value;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    public ?bool $is_free_text = false;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

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

    public function getFeature(): Feature
    {
        return $this->feature;
    }

    public function setFeature(Feature $feature): self
    {
        $this->feature = $feature;

        return $this;
    }

    public function getFeatureAv(): FeatureAv
    {
        return $this->featureAv;
    }

    public function setFeatureAv(FeatureAv $featureAv): self
    {
        $this->featureAv = $featureAv;

        return $this;
    }

    public function getFreeTextValue(): string
    {
        return $this->free_text_value;
    }

    public function setFreeTextValue(string $free_text_value): self
    {
        $this->free_text_value = $free_text_value;

        return $this;
    }

    public function getIsFreeText(): ?bool
    {
        return $this->is_free_text;
    }

    public function setIsFreeText(?bool $is_free_text): self
    {
        $this->is_free_text = $is_free_text;

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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\FeatureProduct::class;
    }
}
