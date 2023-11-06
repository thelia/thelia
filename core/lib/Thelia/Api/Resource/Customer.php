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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/customers'
        ),
        new GetCollection(
            uriTemplate: '/admin/customers'
        ),
        new Get(
            uriTemplate: '/admin/customers/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/customers/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/customers/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'ref',
        'firstname',
        'lastname',
    ]
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'createdAt',
    ]
)]
class Customer implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_READ = 'customer:read';
    public const GROUP_READ_SINGLE = 'customer:read:single';
    public const GROUP_WRITE = 'customer:write';

    #[Groups([self::GROUP_READ, Address::GROUP_READ_SINGLE, Order::GROUP_READ, Cart::GROUP_READ_SINGLE,Order::GROUP_WRITE])]
    public ?int $id = null;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE, Address::GROUP_READ_SINGLE])]
    #[Column(propelSetter: 'setTitleId')]
    public CustomerTitle $customerTitle;

    #[Relation(targetResource: Lang::class, relationAlias: 'LangModel')]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?Lang $lang;

    #[Groups([self::GROUP_READ, Address::GROUP_READ_SINGLE, Order::GROUP_READ])]
    public ?string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Address::GROUP_READ_SINGLE, Order::GROUP_READ, Order::GROUP_READ_SINGLE, Cart::GROUP_READ_SINGLE])]
    public string $firstname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Order::GROUP_READ, Order::GROUP_READ_SINGLE, Cart::GROUP_READ_SINGLE])]
    public string $lastname;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE, Order::GROUP_READ, Order::GROUP_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_WRITE])]
    #[Email(groups: [self::GROUP_WRITE])]
    public ?string $email;

    #[Groups([self::GROUP_WRITE])]
    #[NotBlank(groups: [self::GROUP_WRITE])]
    public ?string $password;

    public ?string $algo;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?bool $reseller;

    public ?string $sponsor;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?float $discount;

    public ?string $rememberMeToken;

    public ?string $rememberMeSerial;

    public ?bool $enable;

    public ?string $confirmationToken;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    public ?int $version;

    public ?\DateTime $versionCreatedAt;

    public ?string $versionCreatedBy;

    #[Relation(targetResource: Address::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public array $addresses;

    public function __construct()
    {
        $this->addresses = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCustomerTitle(): CustomerTitle
    {
        return $this->customerTitle;
    }

    public function setCustomerTitle(CustomerTitle $customerTitle): self
    {
        $this->customerTitle = $customerTitle;

        return $this;
    }

    public function getLang(): ?Lang
    {
        return $this->lang;
    }

    public function setLang(?Lang $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getAlgo(): ?string
    {
        return $this->algo;
    }

    public function setAlgo(?string $algo): self
    {
        $this->algo = $algo;

        return $this;
    }

    public function getReseller(): ?bool
    {
        return $this->reseller;
    }

    public function setReseller(?bool $reseller): self
    {
        $this->reseller = $reseller;

        return $this;
    }

    public function getSponsor(): ?string
    {
        return $this->sponsor;
    }

    public function setSponsor(?string $sponsor): self
    {
        $this->sponsor = $sponsor;

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

    public function getRememberMeToken(): ?string
    {
        return $this->rememberMeToken;
    }

    public function setRememberMeToken(?string $rememberMeToken): self
    {
        $this->rememberMeToken = $rememberMeToken;

        return $this;
    }

    public function getRememberMeSerial(): ?string
    {
        return $this->rememberMeSerial;
    }

    public function setRememberMeSerial(?string $rememberMeSerial): self
    {
        $this->rememberMeSerial = $rememberMeSerial;

        return $this;
    }

    public function getEnable(): ?bool
    {
        return $this->enable;
    }

    public function setEnable(?bool $enable): self
    {
        $this->enable = $enable;

        return $this;
    }

    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(?string $confirmationToken): self
    {
        $this->confirmationToken = $confirmationToken;

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

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(?int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getVersionCreatedAt(): ?\DateTime
    {
        return $this->versionCreatedAt;
    }

    public function setVersionCreatedAt(?\DateTime $versionCreatedAt): self
    {
        $this->versionCreatedAt = $versionCreatedAt;

        return $this;
    }

    public function getVersionCreatedBy(): ?string
    {
        return $this->versionCreatedBy;
    }

    public function setVersionCreatedBy(?string $versionCreatedBy): self
    {
        $this->versionCreatedBy = $versionCreatedBy;

        return $this;
    }

    public function getAddresses(): array
    {
        return $this->addresses;
    }

    public function setAddresses(array $addresses): self
    {
        $this->addresses = $addresses;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CustomerTableMap();
    }


    #[Callback(groups: [Customer::GROUP_WRITE])]
    public function verifyPasswordLength(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();
        if (isset($resource->password) && strlen($resource->password) < ConfigQuery::read('password.length', 4)) {
            $context->addViolation(Translator::getInstance()->trans('The password size is too small.',[],null,'en_US'));
        }
    }

    #[Callback(groups: [Customer::GROUP_WRITE])]
    public function verifyExistingEmail(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();
        $customer = CustomerQuery::getCustomerByEmail($resource->email);
        if ($customer && $customer->getId() !== $this->getId()) {
            $context->addViolation(Translator::getInstance()->trans('This email already exists.',[],null,'en_US'));
        }
    }
}
