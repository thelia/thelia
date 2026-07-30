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

namespace Thelia\Tests\Integration\Domain\Customer;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Service\CustomerUpdateService;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CustomerUpdateServiceTest extends IntegrationTestCase
{
    private CustomerUpdateService $service;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerUpdateService(
            $this->getService(EventDispatcherInterface::class),
        );
        $this->factory = $this->createFixtureFactory();
    }

    public function testUpdateCustomerFromMinimalDtoUpdatesNamesAndKeepsReseller(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->service->updateCustomer(
            new CustomerRegisterDTO(
                firstname: 'Updated',
                lastname: 'Profile',
                email: $customer->getEmail(),
            ),
            $customer,
        );

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertSame('Updated', $reloaded->getFirstname());
        self::assertSame('Profile', $reloaded->getLastname());
        self::assertSame(0, $reloaded->getReseller());
    }

    public function testUpdateCustomerWithResellerDtoPersistsResellerFlag(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->service->updateCustomer(
            new CustomerRegisterDTO(
                firstname: $customer->getFirstname(),
                lastname: $customer->getLastname(),
                email: $customer->getEmail(),
                reseller: true,
            ),
            $customer,
        );

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertSame(1, $reloaded->getReseller());
    }
}
