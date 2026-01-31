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

namespace Thelia\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;

class CustomerFacadeTest extends TestCase
{
    public function testCustomerRegisterDTOToArray(): void
    {
        $dto = new CustomerRegisterDTO(
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            email: 'john.doe@example.com',
            password: 'secret123',
            title: 1,
            langId: 2,
            sponsor: 'SPONSOR123',
            ref: 'REF456',
            discount: 10.5,
            forceEmailUpdate: true,
            enabled: true,
            reseller: true,
        );

        $array = $dto->toArray();

        $this->assertSame(1, $array['id']);
        $this->assertSame('John', $array['firstname']);
        $this->assertSame('Doe', $array['lastname']);
        $this->assertSame('john.doe@example.com', $array['email']);
        $this->assertSame('secret123', $array['password']);
        $this->assertSame(1, $array['title']);
        $this->assertSame(2, $array['langId']);
        $this->assertSame('SPONSOR123', $array['sponsor']);
        $this->assertSame('REF456', $array['ref']);
        $this->assertSame(10.5, $array['discount']);
        $this->assertTrue($array['forceEmailUpdate']);
        $this->assertTrue($array['enabled']);
        $this->assertTrue($array['reseller']);
    }

    public function testCustomerRegisterDTODefaultValues(): void
    {
        $dto = new CustomerRegisterDTO();

        $this->assertNull($dto->getId());
        $this->assertNull($dto->getFirstname());
        $this->assertNull($dto->getLastname());
        $this->assertNull($dto->getEmail());
        $this->assertNull($dto->getPassword());
        $this->assertNull($dto->getTitle());
        $this->assertNull($dto->getLangId());
        $this->assertNull($dto->getSponsor());
        $this->assertNull($dto->getRef());
        $this->assertNull($dto->getDiscount());
        $this->assertFalse($dto->isForceEmailUpdate());
        $this->assertFalse($dto->isEnabled());
        $this->assertFalse($dto->isReseller());
    }

    public function testCustomerRegisterDTOGetters(): void
    {
        $dto = new CustomerRegisterDTO(
            id: 5,
            firstname: 'Jane',
            lastname: 'Smith',
            email: 'jane@example.com',
            password: 'pass',
            title: 2,
            langId: 1,
            sponsor: 'SP',
            ref: 'RF',
            discount: 5.0,
            forceEmailUpdate: false,
            enabled: true,
            reseller: false,
        );

        $this->assertSame(5, $dto->getId());
        $this->assertSame('Jane', $dto->getFirstname());
        $this->assertSame('Smith', $dto->getLastname());
        $this->assertSame('jane@example.com', $dto->getEmail());
        $this->assertSame('pass', $dto->getPassword());
        $this->assertSame(2, $dto->getTitle());
        $this->assertSame(1, $dto->getLangId());
        $this->assertSame('SP', $dto->getSponsor());
        $this->assertSame('RF', $dto->getRef());
        $this->assertSame(5.0, $dto->getDiscount());
        $this->assertFalse($dto->isForceEmailUpdate());
        $this->assertTrue($dto->isEnabled());
        $this->assertFalse($dto->isReseller());
    }

    public function testCustomerRegisterDTOMinimal(): void
    {
        $dto = new CustomerRegisterDTO(
            firstname: 'Bob',
            lastname: 'Builder',
            email: 'bob@example.com',
            password: 'secure',
        );

        $array = $dto->toArray();

        $this->assertNull($array['id']);
        $this->assertSame('Bob', $array['firstname']);
        $this->assertSame('Builder', $array['lastname']);
        $this->assertSame('bob@example.com', $array['email']);
        $this->assertSame('secure', $array['password']);
        $this->assertFalse($array['forceEmailUpdate']);
        $this->assertFalse($array['enabled']);
        $this->assertFalse($array['reseller']);
    }
}
