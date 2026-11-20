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
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Security\CompanyVoter;
use Thelia\Api\State\Processor\CompanyCustomerAdminCreateProcessor;
use Thelia\Api\State\Processor\CompanyCustomerAdminDetachProcessor;
use Thelia\Api\State\Processor\CompanyCustomerAdminUpdateProcessor;
use Thelia\Api\State\Processor\CompanyCustomerFrontDetachProcessor;
use Thelia\Api\State\Processor\CompanyCustomerFrontUpdateProcessor;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Model\CompanyCustomer as CompanyCustomerModel;
use Thelia\Model\CompanyRole as CompanyRoleModel;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CompanyCustomerTableMap;

/**
 * What a customer is within a company: the role they hold and the limits they spend within.
 *
 * Administration attaches an account to a company, changes its role and limits and detaches it,
 * under the right `admin.company`: the account is named by its id, or by its e-mail address, a
 * lookup that is rate limited since it tells whether an address holds an account.
 *
 * A buyer who may manage the buyers (`buyer.manage`) reads the buyers of their own company, changes
 * a role or the limits of one of them, and detaches one, never beyond what their own role holds
 * (the role goes no further than theirs, the limits are no higher than theirs). Another company's
 * buyers read as absent. Neither the company a buyer belongs to, nor their role, nor their limits
 * are ever part of what the customer resource of the buyer reads or writes about themselves.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/company_customers',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_CREATE]],
            processor: CompanyCustomerAdminCreateProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/company_customers',
        ),
        new Get(
            uriTemplate: '/admin/company_customers/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/company_customers/{id}',
            processor: CompanyCustomerAdminUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/company_customers/{id}',
            processor: CompanyCustomerAdminDetachProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/company/customers',
            security: self::FRONT_SECURITY,
        ),
        new Get(
            uriTemplate: '/front/account/company/customers/{id}',
            security: self::FRONT_SECURITY,
        ),
        new Patch(
            uriTemplate: '/front/account/company/customers/{id}',
            security: self::FRONT_SECURITY,
            processor: CompanyCustomerFrontUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/front/account/company/customers/{id}',
            security: self::FRONT_SECURITY,
            processor: CompanyCustomerFrontDetachProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'companyId' => 'exact',
        'customerId' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'createdAt',
    ],
)]
class CompanyCustomer implements PropelResourceInterface, CollectionPreloadableInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:company_customer:read';
    public const GROUP_ADMIN_WRITE = 'admin:company_customer:write';
    public const GROUP_ADMIN_CREATE = 'admin:company_customer:create';
    public const GROUP_FRONT_READ = 'front:company_customer:read';
    public const GROUP_FRONT_WRITE = 'front:company_customer:write';

    public const FRONT_SECURITY = 'is_granted("'.CompanyVoter::PERMISSION.'", "'.CompanyPermission::BUYER_MANAGE.'")';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_CREATE])]
    public ?int $companyId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_CREATE, self::GROUP_FRONT_READ])]
    public ?int $customerId = null;

    /** The e-mail address of the account to attach, when its id is not known. Read nowhere. */
    #[Groups([self::GROUP_ADMIN_CREATE])]
    public ?string $customerEmail = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $firstname = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $lastname = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $email = null;

    /** The code of the role: one every company shares, or one of this company. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_CREATE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $roleCode = null;

    /** The most the buyer may spend on one order, in the default currency, as a decimal string; null for no limit. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_CREATE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $orderLimit = null;

    /** The most the buyer may spend over `period`, in the default currency, as a decimal string; null for no limit. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_CREATE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $periodLimit = null;

    /** week, month or year. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_CREATE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $period = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

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

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getRoleCode(): ?string
    {
        return $this->roleCode;
    }

    public function setRoleCode(?string $roleCode): self
    {
        $this->roleCode = $roleCode;

        return $this;
    }

    public function getOrderLimit(): ?string
    {
        return $this->orderLimit;
    }

    public function setOrderLimit(?string $orderLimit): self
    {
        $this->orderLimit = $orderLimit;

        return $this;
    }

    public function getPeriodLimit(): ?string
    {
        return $this->periodLimit;
    }

    public function setPeriodLimit(?string $periodLimit): self
    {
        $this->periodLimit = $periodLimit;

        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): self
    {
        $this->period = $period;

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

    public function afterModelToResource(array $context): void
    {
        $model = $this->getPropelModel();

        // A page reads the roles and the names of all its buyers at once, in preloadCollection().
        if (!$model instanceof CompanyCustomerModel || ($context['operation'] ?? null) instanceof CollectionOperationInterface) {
            return;
        }

        /** @var CompanyRoleModel|null $role */
        $role = $model->getCompanyRole();
        /** @var CustomerModel|null $customer */
        $customer = $model->getCustomer();
        $this->roleCode = $role?->getCode();
        $this->firstname = $customer?->getFirstname();
        $this->lastname = $customer?->getLastname();
        $this->email = $customer?->getEmail();
    }

    /**
     * One read of the roles and one of the accounts for the page, where each buyer would read its own.
     */
    public static function preloadCollection(array $resources): void
    {
        $roleIds = [];
        $customerIds = [];

        foreach ($resources as $resource) {
            $model = $resource instanceof self ? $resource->getPropelModel() : null;

            if ($model instanceof CompanyCustomerModel) {
                $roleIds[] = (int) $model->getCompanyRoleId();
                $customerIds[] = (int) $model->getCustomerId();
            }
        }

        if ([] === $roleIds) {
            return;
        }

        $codes = array_column(
            CompanyRoleQuery::create()->filterById(array_values(array_unique($roleIds)))->select(['Id', 'Code'])->find()->getData(),
            'Code',
            'Id',
        );
        $accounts = [];

        foreach (CustomerQuery::create()->filterById(array_values(array_unique($customerIds)))->select(['Id', 'Firstname', 'Lastname', 'Email'])->find()->getData() as $row) {
            $accounts[(int) $row['Id']] = $row;
        }

        foreach ($resources as $resource) {
            $model = $resource instanceof self ? $resource->getPropelModel() : null;

            if (!$model instanceof CompanyCustomerModel) {
                continue;
            }

            $resource->roleCode = $codes[(int) $model->getCompanyRoleId()] ?? null;
            $account = $accounts[(int) $model->getCustomerId()] ?? null;
            $resource->firstname = $account['Firstname'] ?? null;
            $resource->lastname = $account['Lastname'] ?? null;
            $resource->email = $account['Email'] ?? null;
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CompanyCustomerTableMap();
    }
}
