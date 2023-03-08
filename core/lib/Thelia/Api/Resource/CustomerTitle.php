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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/customer_titles'
        ),
        new GetCollection(
            uriTemplate: '/admin/customer_titles'
        ),
        new Get(
            uriTemplate: '/admin/customer_titles/{id}',
            normalizationContext:  ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/customer_titles/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/customer_titles/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class CustomerTitle extends AbstractTranslatableResource
{
    public const GROUP_READ = 'customer_title:read';
    public const GROUP_READ_SINGLE = 'customer_title:read:single';
    public const GROUP_WRITE = 'customer_title:write';

    #[Groups([self::GROUP_READ, Customer::GROUP_READ, Address::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $position;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): CustomerTitle
    {
        $this->position = $position;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\CustomerTitle::class;
    }

    public static function getI18nResourceClass(): string
    {
        return CustomerTitleI18n::class;
    }
}
