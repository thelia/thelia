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
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Security\CustomerGroupScopeVoter;
use Thelia\Api\Security\SaleUnitScopeVoter;
use Thelia\Api\State\Processor\CustomerGroupProcessor;
use Thelia\Api\State\Processor\CustomerGroupRegistrationLinkProcessor;
use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupHasNoRegistrationLinkException;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupProtectedException;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupPaymentModuleQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Map\CustomerGroupTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * A segment of customers priced and served alike: its own discount, its own
 * tax display, its own prices, the payment modules it may use and the
 * categories it may see. Exactly one group is the default one, which a new
 * account and a visitor belong to.
 *
 * `paymentModules` and `categories` are lists of IRIs, read on the single item
 * and written with the group. Left out of a write they stay as they are, an
 * empty list clears them and lifts the restriction. They are the group's own allow
 * lists, read only when the group is restricted (`restrictsPaymentModules`,
 * `restrictsCategories`, read only, set by the write of the list): a restricted group
 * may pay with the modules it lists and browse the categories it lists, none at all
 * when the list is empty because what it listed was deleted, and an unrestricted one
 * has no limit; a list never opens nor closes anything to another group.
 *
 * The administration opens, regenerates and closes the registration link of a group (`registrationToken`,
 * read only): an account that registers with the token of the link joins the group instead of the default
 * one. The token reaches no storefront response.
 *
 * Writing a group (its discount, its prices, its links) changes what every sale unit sells: an
 * administrator restricted to a sale unit may read the groups and may not write them.
 *
 * The front only reads the group of the customer, through the customer itself
 * (code and title): the group resource does not disclose its discount, its prices
 * or its restrictions to the storefront. What a customer does see of the discount
 * is the snapshot its own order carries (`Order`: group id, code, discount rate and
 * taxed-price display, as they were when the order was placed).
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/customer_groups',
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            processor: CustomerGroupProcessor::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            exceptionToStatus: [DefaultCustomerGroupProtectedException::class => 409],
        ),
        new GetCollection(
            uriTemplate: '/admin/customer_groups',
        ),
        new Get(
            uriTemplate: '/admin/customer_groups/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/customer_groups/{id}',
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            processor: CustomerGroupProcessor::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            exceptionToStatus: [DefaultCustomerGroupProtectedException::class => 409],
        ),
        new Patch(
            uriTemplate: '/admin/customer_groups/{id}',
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            processor: CustomerGroupProcessor::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            exceptionToStatus: [DefaultCustomerGroupProtectedException::class => 409],
        ),
        new Delete(
            uriTemplate: '/admin/customer_groups/{id}',
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            exceptionToStatus: [DefaultCustomerGroupProtectedException::class => 409],
        ),
        // The registration link is managed by these three operations and by nothing else: the token is
        // never written by a PUT or a PATCH. They change the group, so they answer to the right to update it.
        new Post(
            uriTemplate: '/admin/customer_groups/{id}/registration_link',
            status: 200,
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            read: true,
            deserialize: false,
            validate: false,
            processor: CustomerGroupRegistrationLinkProcessor::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            exceptionToStatus: [DefaultCustomerGroupHasNoRegistrationLinkException::class => 409],
            extraProperties: ['admin_access' => AccessManager::UPDATE, CustomerGroupRegistrationLinkProcessor::ACTION => CustomerGroupRegistrationLinkProcessor::OPEN],
        ),
        new Post(
            uriTemplate: '/admin/customer_groups/{id}/registration_link/regenerate',
            status: 200,
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            read: true,
            deserialize: false,
            validate: false,
            processor: CustomerGroupRegistrationLinkProcessor::class,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            exceptionToStatus: [DefaultCustomerGroupHasNoRegistrationLinkException::class => 409],
            extraProperties: ['admin_access' => AccessManager::UPDATE, CustomerGroupRegistrationLinkProcessor::ACTION => CustomerGroupRegistrationLinkProcessor::REGENERATE],
        ),
        new Delete(
            uriTemplate: '/admin/customer_groups/{id}/registration_link',
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
            processor: CustomerGroupRegistrationLinkProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE, CustomerGroupRegistrationLinkProcessor::ACTION => CustomerGroupRegistrationLinkProcessor::CLOSE],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        // The customer reads the group it belongs to and no other: the IRI the
        // customer resource hands out must lead somewhere on the front.
        new Get(
            uriTemplate: '/front/customer_groups/{id}',
            security: 'is_granted("ROLE_CUSTOMER") and is_granted("'.CustomerGroupScopeVoter::SCOPE.'", object)',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'code' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'isDefault',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
class CustomerGroup extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:customer_group:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:customer_group:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:customer_group:write';
    public const GROUP_FRONT_READ = 'front:customer_group:read';

    public const PAYMENT_MODULE_IRI_PREFIX = '/api/admin/modules/';
    public const CATEGORY_IRI_PREFIX = '/api/admin/categories/';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Customer::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Customer::GROUP_FRONT_READ_SINGLE,
    ])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Length(max: 64, groups: [self::GROUP_ADMIN_WRITE])]
    public ?string $code = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $isDefault = null;

    /**
     * Percentage, applied before the discount of the customer.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[Range(min: 0, max: 100, groups: [self::GROUP_ADMIN_WRITE])]
    public ?float $discount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $displayTaxedPrice = null;

    /**
     * Whether the group may browse only the categories it lists, an empty list included. Read
     * only: the write of the `categories` list is the one thing that sets it.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $restrictsCategories = null;

    /**
     * Whether the group may pay only with the modules it lists, an empty list included. Read
     * only: the write of the `paymentModules` list is the one thing that sets it.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $restrictsPaymentModules = null;

    /**
     * The secret of the registration link of the group, null when the link is closed. Read only,
     * and only by the administration on the single item: it is opened, regenerated and closed by
     * the `registration_link` operations, never written with the group, and the storefront never
     * sees it.
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?string $registrationToken = null;

    /**
     * IRIs of the payment modules the group may pay with; read only when `restrictsPaymentModules` is true.
     *
     * @var list<string>|null
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public ?array $paymentModules = null;

    /**
     * IRIs of the categories the group may browse, sub-trees included; read only when `restrictsCategories` is true.
     *
     * @var list<string>|null
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public ?array $categories = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Customer::GROUP_FRONT_READ_SINGLE,
    ])]
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getDisplayTaxedPrice(): ?bool
    {
        return $this->displayTaxedPrice;
    }

    public function setDisplayTaxedPrice(?bool $displayTaxedPrice): self
    {
        $this->displayTaxedPrice = $displayTaxedPrice;

        return $this;
    }

    public function getRestrictsCategories(): ?bool
    {
        return $this->restrictsCategories;
    }

    public function setRestrictsCategories(?bool $restrictsCategories): self
    {
        $this->restrictsCategories = $restrictsCategories;

        return $this;
    }

    public function getRestrictsPaymentModules(): ?bool
    {
        return $this->restrictsPaymentModules;
    }

    public function setRestrictsPaymentModules(?bool $restrictsPaymentModules): self
    {
        $this->restrictsPaymentModules = $restrictsPaymentModules;

        return $this;
    }

    public function getRegistrationToken(): ?string
    {
        return $this->registrationToken;
    }

    public function setRegistrationToken(?string $registrationToken): self
    {
        $this->registrationToken = $registrationToken;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getPaymentModules(): ?array
    {
        return $this->paymentModules;
    }

    /**
     * @param list<string>|null $paymentModules
     */
    public function setPaymentModules(?array $paymentModules): self
    {
        $this->paymentModules = $paymentModules;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getCategories(): ?array
    {
        return $this->categories;
    }

    /**
     * @param list<string>|null $categories
     */
    public function setCategories(?array $categories): self
    {
        $this->categories = $categories;

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

    /**
     * Ids of the payment modules written, or null when the write leaves them alone.
     *
     * @return list<int>|null
     */
    #[Ignore]
    public function getPaymentModuleIds(): ?array
    {
        return self::idsOf($this->paymentModules, self::PAYMENT_MODULE_IRI_PREFIX);
    }

    /**
     * Ids of the categories written, or null when the write leaves them alone.
     *
     * @return list<int>|null
     */
    #[Ignore]
    public function getCategoryIds(): ?array
    {
        return self::idsOf($this->categories, self::CATEGORY_IRI_PREFIX);
    }

    /**
     * The links are read back from the link tables once the group is transformed, and only
     * when the item is read whole: the group is also embedded in every customer of a list,
     * where two reads a row would be paid for nothing.
     */
    public function afterModelToResource(array $context): void
    {
        $groups = $context['groups'] ?? [];

        if (!\in_array(self::GROUP_ADMIN_READ_SINGLE, \is_array($groups) ? $groups : [$groups], true)) {
            return;
        }

        $this->readLinks();
    }

    /**
     * Reads the payment modules and the categories the group is restricted to, and whether it
     * is, whatever the context: the processor calls it once the links are written.
     */
    public function readLinks(): void
    {
        if (null === $this->id) {
            return;
        }

        $flags = CustomerGroupQuery::create()
            ->filterById($this->id)
            ->select(['RestrictsCategories', 'RestrictsPaymentModules'])
            ->findOne();

        if (\is_array($flags)) {
            $this->restrictsCategories = 1 === (int) $flags['RestrictsCategories'];
            $this->restrictsPaymentModules = 1 === (int) $flags['RestrictsPaymentModules'];
        }

        $this->paymentModules = array_map(
            static fn (int|string $moduleId): string => self::PAYMENT_MODULE_IRI_PREFIX.$moduleId,
            CustomerGroupPaymentModuleQuery::create()
                ->filterByCustomerGroupId($this->id)
                ->orderByModuleId()
                ->select('ModuleId')
                ->find()
                ->toArray(),
        );
        $this->categories = array_map(
            static fn (int|string $categoryId): string => self::CATEGORY_IRI_PREFIX.$categoryId,
            CustomerGroupCategoryQuery::create()
                ->filterByCustomerGroupId($this->id)
                ->orderByCategoryId()
                ->select('CategoryId')
                ->find()
                ->toArray(),
        );
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CustomerGroupTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return CustomerGroupI18n::class;
    }

    /**
     * The code is unique: a duplicate is a violation on the field, not the failed
     * write of the unique index.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateCodeUniqueness(ExecutionContextInterface $context): void
    {
        if (null === $this->code || '' === $this->code) {
            return;
        }

        $query = CustomerGroupQuery::create()->filterByCode($this->code);

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This customer group code already exists.')
                ->atPath('code')
                ->addViolation();
        }
    }

    /**
     * Each link is the IRI of a payment module that exists, or of a category that
     * exists: nothing else is stored, and nothing is written when one is wrong.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateLinks(ExecutionContextInterface $context): void
    {
        $this->validateLinkList($context, 'paymentModules', $this->paymentModules, self::PAYMENT_MODULE_IRI_PREFIX, static fn (array $ids): int => ModuleQuery::create()->filterById($ids)->filterByType(BaseModule::PAYMENT_MODULE_TYPE)->count());
        $this->validateLinkList($context, 'categories', $this->categories, self::CATEGORY_IRI_PREFIX, static fn (array $ids): int => CategoryQuery::create()->filterById($ids)->count());
    }

    /**
     * @param list<mixed>|null         $iris
     * @param callable(list<int>): int $countExisting
     */
    private function validateLinkList(ExecutionContextInterface $context, string $path, ?array $iris, string $prefix, callable $countExisting): void
    {
        if (null === $iris) {
            return;
        }

        $ids = self::idsOf($iris, $prefix);

        if (null === $ids) {
            $context->buildViolation(\sprintf('Each element must be an IRI of the form "%s{id}".', $prefix))
                ->atPath($path)
                ->addViolation();

            return;
        }

        if ($countExisting($ids) !== \count($ids)) {
            $context->buildViolation('One of the linked elements does not exist or is not allowed here.')
                ->atPath($path)
                ->addViolation();
        }
    }

    /**
     * @param list<mixed>|null $iris
     *
     * @return list<int>|null the distinct ids, null when there is nothing to write
     *                        or an element is not an IRI under the prefix
     */
    private static function idsOf(?array $iris, string $prefix): ?array
    {
        if (null === $iris) {
            return null;
        }

        $ids = [];

        foreach ($iris as $iri) {
            if (!\is_string($iri) || 1 !== preg_match('#^'.preg_quote($prefix, '#').'([1-9]\d*)$#', $iri, $matches)) {
                return null;
            }

            $ids[(int) $matches[1]] = (int) $matches[1];
        }

        return array_values($ids);
    }
}
