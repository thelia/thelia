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
use ApiPlatform\Metadata\Link;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\TFiltersProvider;
use Thelia\Model\Map\ChoiceFilterTableMap;

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
class Filter implements PropelResourceInterface
{
    use PropelResourceTrait;

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
    private ?int $position;
    #[Groups([self::GROUP_FRONT_READ])]
    private array $values;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function setInputType(string $inputType): self
    {
        $this->inputType = $inputType;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return ChoiceFilterTableMap::getTableMap();
    }
}
