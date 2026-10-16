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

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Model\CustomerPaymentMethod;

/**
 * An option of the "stored_methods" group: one of the buyer's stored payment methods,
 * or the choice of a new one. What the buyer recognizes the method by, never the
 * reference the provider keeps it under.
 *
 * The identifier is what `POST /front/account/checkout/{cartId}/payment_module` takes
 * as `customerPaymentMethodId`; the option of a new method carries none.
 */
class PaymentModuleStoredMethodOption extends PaymentModuleOption
{
    public const NEW_METHOD_CODE = 'new_method';
    public const STORED_METHOD_CODE_PREFIX = 'stored_method_';

    #[ApiProperty(description: 'Identifier of the stored payment method, null for the option of a new one', example: 12)]
    #[Groups([PaymentModule::GROUP_FRONT_READ])]
    private ?int $customerPaymentMethodId = null;

    #[ApiProperty(description: 'Last day the provider accepts the stored method (Y-m-d), null when it does not expire', example: '2030-12-31')]
    #[Groups([PaymentModule::GROUP_FRONT_READ])]
    private ?string $expiresAt = null;

    #[ApiProperty(description: 'Whether this is the method the buyer asked to be offered first', example: true)]
    #[Groups([PaymentModule::GROUP_FRONT_READ])]
    private bool $default = false;

    public static function ofStoredMethod(CustomerPaymentMethod $method): self
    {
        $option = new self();
        $option->setCode(self::STORED_METHOD_CODE_PREFIX.(int) $method->getId());
        $option->setTitle((string) $method->getLabel());
        $option->customerPaymentMethodId = $method->getId();
        $option->expiresAt = $method->getExpiresAt('Y-m-d');
        $option->default = true === $method->getIsDefault();

        return $option;
    }

    public static function ofANewMethod(string $title): self
    {
        $option = new self();
        $option->setCode(self::NEW_METHOD_CODE);
        $option->setTitle($title);

        return $option;
    }

    public function getCustomerPaymentMethodId(): ?int
    {
        return $this->customerPaymentMethodId;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }
}
