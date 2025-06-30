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


use ApiPlatform\Metadata\ApiFilter;
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
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\NewsletterTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/news_letters'
        ),
        new GetCollection(
            uriTemplate: '/admin/news_letters'
        ),
        new Get(
            uriTemplate: '/admin/news_letters/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/news_letters/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/news_letters/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/news_letters/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)] // todo custom route for front
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'email',
    ]
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'unsubscribed',
    ]
)]
class NewsLetter implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:news_letters:read';

    public const GROUP_ADMIN_READ_SINGLE = 'admin:news_letters:read:single';

    public const GROUP_ADMIN_WRITE = 'admin:news_letters:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public string $email;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $firstname = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $lastname = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public string $locale;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $unsubscribed;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed;
    }

    public function setUnsubscribed(bool $unsubscribed): self
    {
        $this->unsubscribed = $unsubscribed;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new NewsletterTableMap();
    }
}
