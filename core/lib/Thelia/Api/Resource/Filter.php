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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\TFiltersProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/tfilters/{resource}',
            uriVariables: [
                'resource' => new Link(fromProperty: 'resource', identifiers: ['string']),
            ],
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'resource',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
            ],
            provider: TFiltersProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
class Filter
{
    public const GROUP_FRONT_READ = 'front:filter:read';

    #[Groups([self::GROUP_FRONT_READ])]
    private ?int $id = null;

    #[Groups([self::GROUP_FRONT_READ])]
    private string $title;

    #[Groups([self::GROUP_FRONT_READ])]
    private string $type;

    #[Groups([self::GROUP_FRONT_READ])]
    private string $fieldType;

    #[Groups([self::GROUP_FRONT_READ])]
    private ?int $position = null;

    #[Groups([self::GROUP_FRONT_READ])]
    private array $values;

    private bool $visible;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getFieldType(): string
    {
        return $this->fieldType;
    }

    public function setFieldType(string $fieldType): self
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): self
    {
        $this->values = $values;

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

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }
}
