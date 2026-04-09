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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\RewritingUrlTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/rewriting_url'
        ),
        new GetCollection(
            uriTemplate: '/admin/rewriting_url'
        ),
        new Get(
            uriTemplate: '/admin/rewriting_url/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'url',
        'view',
        'viewId',
        'viewLocale',
        'redirected',
    ]
)]
class RewritingUrl implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:rewriting_url:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:rewriting_url:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:rewriting_url:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public string $url;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $view;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $viewId;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $viewLocale;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $redirected;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function setView(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function getViewId(): string
    {
        return $this->viewId;
    }

    public function setViewId(string $viewId): self
    {
        $this->viewId = $viewId;

        return $this;
    }

    public function getViewLocale(): string
    {
        return $this->viewLocale;
    }

    public function setViewLocale(string $viewLocale): self
    {
        $this->viewLocale = $viewLocale;

        return $this;
    }

    public function getRedirected(): ?string
    {
        return $this->redirected;
    }

    public function setRedirected(?string $redirected): self
    {
        $this->redirected = $redirected;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new RewritingUrlTableMap();
    }
}
