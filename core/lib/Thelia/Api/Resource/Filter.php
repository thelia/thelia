<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\TFiltersProvider;

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
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
class Filter
{
    public const GROUP_FRONT_READ = 'front:filter:read';
    #[Groups([self::GROUP_FRONT_READ])]
    private ?int $id;
    #[Groups([self::GROUP_FRONT_READ])]
    private string $title;
    #[Groups([self::GROUP_FRONT_READ])]
    private string $type;
    #[Groups([self::GROUP_FRONT_READ])]
    private string $inputType;
    #[Groups([self::GROUP_FRONT_READ])]
    private bool $visible;

    #[Groups([self::GROUP_FRONT_READ])]
    private ?int $position;
    #[Groups([self::GROUP_FRONT_READ])]
    private array $values;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Filter
    {
        $this->id = $id;
        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function setInputType(string $inputType): Filter
    {
        $this->inputType = $inputType;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Filter
    {
        $this->title = $title;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): Filter
    {
        $this->type = $type;
        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): Filter
    {
        $this->values = $values;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): Filter
    {
        $this->visible = $visible;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Filter
    {
        $this->position = $position;
        return $this;
    }
}
