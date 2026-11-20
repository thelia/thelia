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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Security\CompanyVoter;
use Thelia\Api\State\Processor\CompanyRoleDeleteProcessor;
use Thelia\Api\State\Processor\CompanyRoleWriteProcessor;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Model\Map\CompanyRoleTableMap;

/**
 * A named list of permissions a buyer holds within a company: a role every company may give
 * (no company), or one that belongs to a company.
 *
 * Administration edits the roles under the right `admin.company`, the only place a permission list
 * is written: the code of a role and the company it belongs to are fixed when it is created, a
 * permission must be one the shop knows, and a role that buyers hold is not deleted (409). A buyer
 * who manages the buyers reads the roles they may hand out, the shared ones and those of their own
 * company, to pick one.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/company_roles',
            processor: CompanyRoleWriteProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/company_roles',
        ),
        new Get(
            uriTemplate: '/admin/company_roles/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/company_roles/{id}',
            processor: CompanyRoleWriteProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/company_roles/{id}',
            processor: CompanyRoleDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/company/roles',
            security: 'is_granted("'.CompanyVoter::PERMISSION.'", "'.CompanyPermission::BUYER_MANAGE.'")',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'code' => 'exact',
        'companyId' => 'exact',
    ],
)]
class CompanyRole extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:company_role:read';
    public const GROUP_ADMIN_WRITE = 'admin:company_role:write';
    public const GROUP_FRONT_READ = 'front:company_role:read';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    /** Fixed once the role exists: lowercase letters, digits, dots, dashes and underscores. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Length(max: 100, groups: [self::GROUP_ADMIN_WRITE])]
    #[Regex(pattern: '/^[a-z0-9_.-]+$/', groups: [self::GROUP_ADMIN_WRITE])]
    public ?string $code = null;

    /** The company the role belongs to, null for a role every company may give. Fixed once the role exists. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $companyId = null;

    /**
     * @var list<string>
     */
    #[Column(propelFieldName: 'permissionList')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public array $permissions = [];

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param list<string> $permissions
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;

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

    public static function getI18nResourceClass(): string
    {
        return CompanyRoleI18n::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CompanyRoleTableMap();
    }
}
