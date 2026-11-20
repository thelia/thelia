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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Security\CompanyVoter;
use Thelia\Api\State\Processor\CompanyAdminDeleteProcessor;
use Thelia\Api\State\Processor\CompanyAdminWriteProcessor;
use Thelia\Api\State\Processor\CompanyFrontUpdateProcessor;
use Thelia\Api\State\Provider\CurrentCompanyProvider;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Model\Map\CompanyTableMap;

/**
 * A company the buyers belong to: its details, and whether it may place orders.
 *
 * Administration reads and writes every company under the right `admin.company`; every operation
 * is declared, since a resource left bare would expose a complete CRUD to anyone. A write goes
 * through {@see \Thelia\Domain\Company\CompanyFacade}, which copies the identifiers onto the
 * billing address, refuses to delete a company that has orders (409) and suspends or reactivates
 * it when `active` changes.
 *
 * A buyer reads the company they belong to under `/front/account/company`, and, with the right
 * to edit it, changes its name, its identifiers and the address that receives a copy of the order
 * e-mails. The note kept for the shop administrators, the parent company and the address chosen
 * for invoices are never part of what a buyer sends or reads there. A company whose orders were
 * left without VAT keeps its identifiers.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/companies',
            processor: CompanyAdminWriteProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/companies',
        ),
        new Get(
            uriTemplate: '/admin/companies/{id}',
        ),
        new Put(
            uriTemplate: '/admin/companies/{id}',
            processor: CompanyAdminWriteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/companies/{id}',
            processor: CompanyAdminWriteProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/companies/{id}',
            processor: CompanyAdminDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/account/company',
            security: 'is_granted("'.CompanyVoter::MEMBER.'")',
            provider: CurrentCompanyProvider::class,
        ),
        new Patch(
            uriTemplate: '/front/account/company',
            security: 'is_granted("'.CompanyVoter::PERMISSION.'", "'.CompanyPermission::COMPANY_EDIT.'")',
            read: true,
            provider: CurrentCompanyProvider::class,
            processor: CompanyFrontUpdateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'name' => 'partial',
        'siret' => 'exact',
        'vatNumber' => 'exact',
        'parentId' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'active',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'name',
        'createdAt',
    ],
)]
class Company implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:company:read';
    public const GROUP_ADMIN_WRITE = 'admin:company:write';
    public const GROUP_FRONT_READ = 'front:company:read';
    public const GROUP_FRONT_WRITE = 'front:company:write';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE])]
    #[Length(max: 255, groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE])]
    public ?string $name = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[Length(max: 20, groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE])]
    public ?string $siret = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[Length(max: 20, groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE])]
    public ?string $vatNumber = null;

    /** The company this one belongs to: kept and shown, with no effect on what a buyer may do. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $parentId = null;

    /** A suspended company keeps its buyers and its history and places no order. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?bool $active = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[Length(max: 255, groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE])]
    #[Email(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE])]
    public ?string $notificationEmail = null;

    /** A note for the shop administrators: never shown to the company. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $internalNote = null;

    /** An address shared with the company, which invoices are made out to. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $billingAddressId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): self
    {
        $this->siret = $siret;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): self
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getNotificationEmail(): ?string
    {
        return $this->notificationEmail;
    }

    public function setNotificationEmail(?string $notificationEmail): self
    {
        $this->notificationEmail = $notificationEmail;

        return $this;
    }

    public function getInternalNote(): ?string
    {
        return $this->internalNote;
    }

    public function setInternalNote(?string $internalNote): self
    {
        $this->internalNote = $internalNote;

        return $this;
    }

    public function getBillingAddressId(): ?int
    {
        return $this->billingAddressId;
    }

    public function setBillingAddressId(?int $billingAddressId): self
    {
        $this->billingAddressId = $billingAddressId;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CompanyTableMap();
    }
}
