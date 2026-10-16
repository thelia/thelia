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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\CustomerPaymentMethodForgetProcessor;
use Thelia\Api\State\Processor\CustomerPaymentMethodSetDefaultProcessor;
use Thelia\Model\CustomerPaymentMethod as CustomerPaymentMethodModel;
use Thelia\Model\Lang;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;
use Thelia\Model\ModuleI18nQuery;
use Thelia\Model\ModuleQuery;

/**
 * A payment method a customer asked the shop to keep, exposed without its
 * provider token: the token has no group here and is never returned, on a
 * success response or an error one.
 *
 * The front operations ask for a customer in their `security:` expression, on
 * top of the firewall rule on /front/account, and are scoped to that customer
 * by CustomerPaymentMethodOwnershipExtension rather than by comparing the owner
 * in the expression: another customer's id then reads as "not found", the same
 * answer a missing id gives, instead of "forbidden" — an attacker probing
 * sequential ids learns nothing about which ones exist.
 *
 * There is no Post: a module registers a method through
 * CustomerPaymentMethodService::register() when its provider hands one
 * back, never the customer directly.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/customer_payment_methods',
        ),
        new Get(
            uriTemplate: '/admin/customer_payment_methods/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/payment_methods',
            security: self::FRONT_SECURITY,
        ),
        new Get(
            uriTemplate: '/front/account/payment_methods/{id}',
            security: self::FRONT_SECURITY,
        ),
        new Patch(
            uriTemplate: '/front/account/payment_methods/{id}',
            security: self::FRONT_SECURITY,
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE_DEFAULT]],
            processor: CustomerPaymentMethodSetDefaultProcessor::class,
        ),
        new Delete(
            uriTemplate: '/front/account/payment_methods/{id}',
            security: self::FRONT_SECURITY,
            processor: CustomerPaymentMethodForgetProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'customer.id',
    ],
)]
class CustomerPaymentMethod implements PropelResourceInterface, CollectionPreloadableInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:customer_payment_method:read';
    public const GROUP_FRONT_READ = 'front:customer_payment_method:read';
    public const GROUP_FRONT_WRITE_DEFAULT = 'front:customer_payment_method:write:default';

    public const FRONT_SECURITY = 'is_granted("ROLE_CUSTOMER")';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $label = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $expiresAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE_DEFAULT])]
    public ?bool $isDefault = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    /**
     * The module is named by its code and its title, and not linked as a relation: the
     * Module resource has admin operations only, so its IRI would send the front to an
     * endpoint it cannot reach, and the code identifies the module for the back-office
     * as well. Read from the module directly rather than frozen at registration: unlike
     * an order, a stored method has no invoice to keep a past name consistent with, so
     * showing the module's current identity is what the customer expects to see.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $paymentModuleCode = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $paymentModuleTitle = null;

    // Admin only: the front is scoped to its owner by
    // CustomerPaymentMethodOwnershipExtension and never returns it, so a front read
    // does not build it either. The `customer.id` filter works on the column.
    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public Customer $customer;

    // The locale the title of the module is read in, kept for preloadCollection().
    private ?string $paymentModuleLocale = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPaymentModuleCode(): ?string
    {
        return $this->paymentModuleCode;
    }

    public function setPaymentModuleCode(?string $paymentModuleCode): self
    {
        $this->paymentModuleCode = $paymentModuleCode;

        return $this;
    }

    public function getPaymentModuleTitle(): ?string
    {
        return $this->paymentModuleTitle;
    }

    public function setPaymentModuleTitle(?string $paymentModuleTitle): self
    {
        $this->paymentModuleTitle = $paymentModuleTitle;

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function afterModelToResource(array $context): void
    {
        $model = $this->getPropelModel();

        if (!$model instanceof CustomerPaymentMethodModel) {
            return;
        }

        $this->paymentModuleLocale = $context['filters']['locale'] ?? Lang::getDefaultLanguage()->getLocale();

        // A page reads the modules of all its methods at once, in preloadCollection().
        if (($context['operation'] ?? null) instanceof CollectionOperationInterface) {
            return;
        }

        $module = $model->getPaymentModule();

        $this->paymentModuleCode = $module->getCode();
        $this->paymentModuleTitle = $module->setLocale($this->paymentModuleLocale)->getTitle();
    }

    /**
     * One read of the modules and one of their titles for the page, where each method
     * would read its own module and then its title.
     */
    public static function preloadCollection(array $resources): void
    {
        $moduleIdsByLocale = [];

        foreach ($resources as $resource) {
            $model = $resource->getPropelModel();

            if (!$resource instanceof self || !$model instanceof CustomerPaymentMethodModel || null === $resource->paymentModuleLocale) {
                continue;
            }

            $moduleIdsByLocale[$resource->paymentModuleLocale][] = (int) $model->getPaymentModuleId();
        }

        foreach ($moduleIdsByLocale as $locale => $moduleIds) {
            $moduleIds = array_values(array_unique($moduleIds));
            $codeRows = ModuleQuery::create()
                ->filterById($moduleIds)
                ->select(['Id', 'Code'])
                ->find()
                ->getData();
            $titleRows = ModuleI18nQuery::create()
                ->filterById($moduleIds)
                ->filterByLocale($locale)
                ->select(['Id', 'Title'])
                ->find()
                ->getData();
            $codes = array_column($codeRows, 'Code', 'Id');
            $titles = array_column($titleRows, 'Title', 'Id');

            foreach ($resources as $resource) {
                $model = $resource->getPropelModel();

                if (!$resource instanceof self || !$model instanceof CustomerPaymentMethodModel || $locale !== $resource->paymentModuleLocale) {
                    continue;
                }

                $moduleId = (int) $model->getPaymentModuleId();
                $resource->paymentModuleCode = $codes[$moduleId] ?? null;
                $resource->paymentModuleTitle = $titles[$moduleId] ?? null;
            }
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CustomerPaymentMethodTableMap();
    }
}
